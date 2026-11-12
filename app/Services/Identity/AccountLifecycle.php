<?php

namespace App\Services\Identity;

use App\Models\PlatformOwner;
use App\Models\RegistrationRequest;
use App\Models\StatusTransition;
use App\Models\User;
use App\Services\Support\AuditLogger;
use App\Services\Support\Notifier;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Satu-satunya jalur perubahan status akun. Setiap transisi:
 * validasi -> simpan -> riwayat -> audit -> sinkron registry/pendaftaran -> notifikasi.
 */
class AccountLifecycle
{
    /** Transisi yang diperbolehkan. REVOKED bersifat final. */
    private const ALLOWED = [
        'PROVISIONED' => ['ACTIVE', 'SUSPENDED', 'REVOKED'],
        'ACTIVE' => ['SUSPENDED', 'REVOKED'],
        'SUSPENDED' => ['ACTIVE', 'PROVISIONED', 'REVOKED'],
        'REVOKED' => [],
    ];

    public function __construct(
        private AuditLogger $audit,
        private RegistryStore $registry,
        private Notifier $notifier,
    ) {
    }

    public static function currentActor(): array
    {
        $owner = Auth::guard('platform')->user();
        if ($owner instanceof PlatformOwner) {
            return ['type' => 'OWNER', 'label' => $owner->owner_id];
        }
        $user = Auth::guard('web')->user();
        if ($user instanceof User) {
            return ['type' => 'USER', 'label' => $user->trace_id, 'user_id' => $user->id];
        }
        return ['type' => 'SYSTEM', 'label' => 'cli'];
    }

    public static function recordTransition(string $subjectType, string $subjectId, ?string $from, string $to, array $actor, ?string $note = null): void
    {
        StatusTransition::create([
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'from_status' => $from,
            'to_status' => $to,
            'actor_type' => $actor['type'] ?? 'SYSTEM',
            'actor_label' => $actor['label'] ?? null,
            'note' => $note ? mb_substr($note, 0, 500) : null,
            'created_at' => now(),
        ]);
    }

    public function transition(User $user, string $to, ?array $actor = null, ?string $note = null): User
    {
        $actor ??= self::currentActor();
        $from = $user->account_status;

        if (! in_array($to, self::ALLOWED[$from] ?? [], true)) {
            throw new InvalidArgumentException("Transisi akun {$from} -> {$to} tidak diizinkan.");
        }
        if ($user->account_status === 'SYSTEM') {
            throw new InvalidArgumentException('Akun sistem tidak dapat diubah.');
        }

        DB::transaction(function () use ($user, $from, $to, $actor, $note) {
            $user->update(['account_status' => $to]);
            self::recordTransition('ACCOUNT', $user->trace_id, $from, $to, $actor, $note);
            $this->audit->log('ACCOUNT_' . $to, $user, ['from' => $from, 'to' => $to, 'note' => $note], null, 'SUCCESS', $actor);
            $this->syncRegistration($user, $to, $actor);
        });

        if (in_array($to, ['SUSPENDED', 'REVOKED'], true)) {
            $this->revokeSessions($user);
        }
        try {
            $this->registry->setAccountStatus($user->trace_id, $to === 'SUSPENDED' || $to === 'REVOKED' || $to === 'ACTIVE' ? $to : 'PROVISIONED');
        } catch (\Throwable) {
            // Registry bersifat pelengkap; DB adalah sumber kebenaran.
        }

        $this->notifier->notify($user, 'ACCOUNT', 'Status akun: ' . $to, match ($to) {
            'SUSPENDED' => 'Akun Anda ditangguhkan. Hubungi administrator.',
            'REVOKED' => 'Akses akun Anda dicabut.',
            default => 'Akun Anda berstatus ' . $to . '.',
        });
        if ($to === 'SUSPENDED' && $user->organization_id) {
            $this->notifier->notify(
                $this->notifier->usersWithPermission('accounts.manage', $user->organization_id)->where('id', '!=', $user->id),
                'SECURITY',
                'Akun ditangguhkan',
                "Akun {$user->trace_id} ditangguhkan."
            );
        }

        return $user->refresh();
    }

    public function suspend(User $user, ?string $note = null, ?array $actor = null): User
    {
        return $this->transition($user, 'SUSPENDED', $actor, $note);
    }

    public function revoke(User $user, ?string $note = null, ?array $actor = null): User
    {
        return $this->transition($user, 'REVOKED', $actor, $note);
    }

    /** Pengaktifan kembali: kembali ke PROVISIONED bila sandi awal belum pernah diganti. */
    public function reactivate(User $user, ?string $note = null, ?array $actor = null): User
    {
        return $this->transition($user, $user->password_changed_at ? 'ACTIVE' : 'PROVISIONED', $actor, $note);
    }

    /** Dipanggil setelah penggantian kata sandi pertama. */
    public function markFirstLoginComplete(User $user): void
    {
        if ($user->account_status === 'PROVISIONED') {
            $this->transition($user, 'ACTIVE', ['type' => 'USER', 'label' => $user->trace_id, 'user_id' => $user->id], 'Kata sandi awal diganti.');
        }
    }

    /** Reset kredensial aman: sandi sementara baru, wajib ganti, semua sesi dicabut. */
    public function resetCredentials(User $user, ?array $actor = null): string
    {
        $actor ??= self::currentActor();
        if (in_array($user->account_status, ['REVOKED', 'SYSTEM'], true)) {
            throw new InvalidArgumentException('Kredensial akun ini tidak dapat direset.');
        }
        $temp = TraceIdGenerator::temporaryPassword();
        $user->update(['password' => $temp, 'must_change_password' => true]);
        $this->revokeSessions($user);
        $this->audit->log('CREDENTIAL_RESET', $user, ['trace_id' => $user->trace_id], null, 'SUCCESS', $actor);
        $this->notifier->notify($user, 'SECURITY', 'Kredensial direset', 'Kata sandi Anda direset oleh administrator. Anda wajib menggantinya saat login berikutnya.');
        return $temp;
    }

    public function revokeSessions(User $user): void
    {
        DB::table('sessions')->where('user_id', $user->id)->delete();
    }

    private function syncRegistration(User $user, string $to, array $actor): void
    {
        $map = ['ACTIVE' => 'ACTIVE', 'SUSPENDED' => 'SUSPENDED', 'REVOKED' => 'REVOKED'];
        if (! isset($map[$to])) {
            return;
        }
        $req = RegistrationRequest::where('trace_id', $user->trace_id)->first();
        if ($req && $req->approval_status !== $map[$to]) {
            $old = $req->approval_status;
            $req->update(['approval_status' => $map[$to]]);
            self::recordTransition('REGISTRATION', $req->request_reference, $old, $map[$to], $actor);
        }
    }

    public static function newReference(): string
    {
        return 'REG-' . now()->format('Y') . '-' . strtoupper(Str::random(6));
    }
}
