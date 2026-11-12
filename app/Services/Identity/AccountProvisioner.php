<?php

namespace App\Services\Identity;

use App\Models\Organization;
use App\Models\Role;
use App\Models\Unit;
use App\Models\User;
use App\Services\Support\AuditLogger;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Impor registry -> database (idempoten). Kata sandi sementara hanya dikembalikan
 * di memori kepada pemanggil; tidak pernah ditulis ke log/terminal oleh layanan ini.
 */
class AccountProvisioner
{
    public const CREATED = 'CREATED';
    public const WOULD_CREATE = 'DRY_RUN_WOULD_CREATE';
    public const EXISTS = 'SKIPPED_ALREADY_PROVISIONED';
    public const NOT_APPROVED = 'SKIPPED_NOT_APPROVED';
    public const INACTIVE = 'SKIPPED_INACTIVE_STATE';

    public function __construct(
        private RegistryStore $registry,
        private RegistryValidator $validator,
        private AuditLogger $audit,
    ) {
    }

    /**
     * @return array{errors: array, results: array<int,array>, issued: array<string,string>}
     */
    public function provisionAll(bool $dryRun = false, ?string $only = null, ?array $actor = null): array
    {
        $entries = $this->registry->approved();
        $errors = $this->validator->validate($entries);
        if ($errors) {
            $this->audit->log('REGISTRY_IMPORT_REJECTED', ['type' => 'Registry', 'id' => 'approved-accounts'], ['error_entries' => count($errors)], null, 'DENIED', $actor ?? ['type' => 'SYSTEM', 'label' => 'cli']);
            return ['errors' => $errors, 'results' => [], 'issued' => []];
        }

        $results = [];
        $issued = [];
        foreach ($entries as $entry) {
            if ($only && $entry['trace_id'] !== $only) {
                continue;
            }
            $r = $this->provisionEntry($entry, $dryRun, $actor);
            $results[] = $r['result'];
            if (isset($r['password'])) {
                $issued[$entry['trace_id']] = $r['password'];
            }
        }

        return ['errors' => [], 'results' => $results, 'issued' => $issued];
    }

    /** @return array{result: array, password?: string} */
    public function provisionEntry(array $entry, bool $dryRun = false, ?array $actor = null): array
    {
        $actor ??= AccountLifecycle::currentActor();
        $tid = $entry['trace_id'];
        $roleName = $entry['role'] ?? $entry['approved_role'] ?? '';
        $base = ['trace_id' => $tid, 'role' => $roleName, 'warnings' => []];

        if (($entry['approval_status'] ?? '') !== 'APPROVED') {
            return ['result' => $base + ['status' => self::NOT_APPROVED]];
        }
        if (User::where('trace_id', $tid)->exists()) {
            return ['result' => $base + ['status' => self::EXISTS]];
        }
        if (! in_array($entry['account_status'], ['PROVISIONED', 'ACTIVE'], true)) {
            return ['result' => $base + ['status' => self::INACTIVE]];
        }

        // Peringatan duplikasi identitas (nama + organisasi) dengan Trace-ID berbeda.
        $dup = User::where('name', $entry['full_name'])
            ->whereHas('organization', fn ($q) => $q->where('name', $entry['organization']))
            ->whereNotIn('account_status', ['REVOKED'])->exists();
        if ($dup) {
            $base['warnings'][] = 'Sudah ada akun aktif dengan nama dan organisasi yang sama (Trace-ID berbeda).';
        }

        if ($dryRun) {
            return ['result' => $base + ['status' => self::WOULD_CREATE]];
        }

        $password = TraceIdGenerator::temporaryPassword();

        try {
            DB::transaction(function () use ($entry, $password, $actor, $roleName) {
                $role = Role::where('name', $roleName)->first();
                if (! $role) {
                    throw new RuntimeException('Peran belum tersedia di database. Jalankan seeder RBAC (php artisan db:seed --class=RbacSeeder).');
                }
                $org = Organization::firstOrCreate(['name' => $entry['organization']], ['status' => 'ACTIVE']);
                $unit = ! empty($entry['unit'])
                    ? Unit::firstOrCreate(['organization_id' => $org->id, 'name' => $entry['unit']], ['status' => 'ACTIVE'])
                    : null;

                $user = User::create([
                    'trace_id' => $entry['trace_id'],
                    'name' => $entry['full_name'],
                    'password' => $password,
                    'role_id' => $role->id,
                    'organization_id' => $org->id,
                    'unit_id' => $unit?->id,
                    'position' => $entry['position'] ?? null,
                    'account_status' => 'PROVISIONED',
                    'approval_status' => 'APPROVED',
                    'must_change_password' => true,
                    'expires_at' => $entry['expires_at'] ?? null,
                ]);

                AccountLifecycle::recordTransition('ACCOUNT', $user->trace_id, null, 'PROVISIONED', $actor, 'Diprovisioning dari registry.');
                $this->audit->log('TRACE_ID_PROVISIONED', $user, [
                    'trace_id' => $user->trace_id, 'role' => $roleName, 'approved_by' => $entry['approved_by'] ?? null,
                ], null, 'SUCCESS', $actor);
            });
        } catch (QueryException $e) {
            // Balapan: Trace-ID dibuat proses lain di antara pemeriksaan dan penyisipan.
            if (User::where('trace_id', $tid)->exists()) {
                return ['result' => $base + ['status' => self::EXISTS]];
            }
            throw $e;
        }

        try {
            $this->registry->markProvisioned($tid, now()->toIso8601String());
        } catch (\Throwable) {
            // Best-effort sync; DB tetap menjadi sumber otoritatif.
        }

        return ['result' => $base + ['status' => self::CREATED], 'password' => $password];
    }
}
