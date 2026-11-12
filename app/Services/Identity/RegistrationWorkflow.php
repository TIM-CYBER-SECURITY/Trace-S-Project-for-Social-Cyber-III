<?php

namespace App\Services\Identity;

use App\Models\Organization;
use App\Models\PlatformOwner;
use App\Models\RegistrationRequest;
use App\Services\Support\AuditLogger;
use App\Services\Support\Notifier;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use InvalidArgumentException;

/** Alur permohonan Trace-ID: pemohon -> review Owner -> registry -> provisioning. */
class RegistrationWorkflow
{
    public function __construct(
        private AuditLogger $audit,
        private RegistryStore $registry,
        private TraceIdGenerator $ids,
        private AccountProvisioner $provisioner,
        private RegistryValidator $validator,
        private Notifier $notifier,
    ) {
    }

    /** @return array{0: RegistrationRequest, 1: string} permohonan dan kode status (ditampilkan sekali) */
    public function submit(array $data): array
    {
        $statusCode = strtoupper(Str::random(10));

        $req = DB::transaction(function () use ($data, $statusCode) {
            $req = RegistrationRequest::create([
                'request_reference' => AccountLifecycle::newReference(),
                'status_code_hash' => hash('sha256', $statusCode),
                'full_name' => $data['full_name'],
                'identification_type' => $data['identification_type'],
                'identification_number' => $data['identification_number'],
                'organization' => $data['organization'],
                'unit' => $data['unit'] ?? null,
                'position' => $data['position'] ?? null,
                'contact_email' => $data['contact_email'],
                'contact_phone' => $data['contact_phone'] ?? null,
                'requested_role' => $data['requested_role'],
                'access_reason' => $data['access_reason'],
                'recommender_name' => $data['recommender_name'] ?? null,
                'recommender_position' => $data['recommender_position'] ?? null,
                'recommender_contact' => $data['recommender_contact'] ?? null,
                'verification_info' => $data['verification_info'] ?? null,
                'approval_status' => 'PENDING REVIEW',
            ]);
            $actor = ['type' => 'APPLICANT', 'label' => $req->request_reference];
            AccountLifecycle::recordTransition('REGISTRATION', $req->request_reference, null, 'PENDING REVIEW', $actor);
            // Audit tidak memuat data pribadi pemohon: hanya referensi dan peran yang diminta.
            $this->audit->log('REGISTRATION_SUBMITTED', ['type' => 'RegistrationRequest', 'id' => $req->request_reference], ['requested_role' => $req->requested_role], null, 'SUCCESS', $actor);
            return $req;
        });

        $org = Organization::where('name', $req->organization)->first();
        if ($org) {
            $this->notifier->notify(
                $this->notifier->usersWithPermission('registrations.view', $org->id),
                'REGISTRATION',
                'Permohonan Trace-ID diterima',
                "Permohonan {$req->request_reference} untuk organisasi Anda menunggu peninjauan Developer/Owner."
            );
        }

        return [$req, $statusCode];
    }

    public static function findByCredentials(string $reference, string $statusCode): ?RegistrationRequest
    {
        $req = RegistrationRequest::where('request_reference', strtoupper(trim($reference)))->first();
        $candidate = hash('sha256', strtoupper(trim($statusCode)));
        // Selalu bandingkan hash agar waktu respons tidak membocorkan keberadaan referensi.
        $stored = $req?->status_code_hash ?? str_repeat('0', 64);
        return ($req && hash_equals($stored, $candidate)) ? $req : null;
    }

    public function startReview(RegistrationRequest $req, PlatformOwner $owner): void
    {
        $this->guardState($req, ['PENDING REVIEW']);
        $this->move($req, 'UNDER REVIEW', $owner, 'REGISTRATION_UNDER_REVIEW');
    }

    /** Menyetujui: membuat Trace-ID dan menulis entri ke registry eksternal. */
    public function approve(RegistrationRequest $req, PlatformOwner $owner, string $approvedRole, ?string $notes = null): string
    {
        $this->guardState($req, ['PENDING REVIEW', 'UNDER REVIEW']);
        if (! in_array($approvedRole, ['ADMINISTRATOR', 'OPERATOR'], true)) {
            throw new InvalidArgumentException('Peran yang disetujui tidak valid.');
        }

        $traceId = $this->ids->generate();
        $entry = [
            'trace_id' => $traceId,
            'full_name' => $req->full_name,
            'organization' => $req->organization,
            'unit' => $req->unit,
            'position' => $req->position,
            'requested_role' => $req->requested_role,
            'approved_role' => $approvedRole,
            'approval_status' => 'APPROVED',
            'account_status' => 'PROVISIONED',
            'approved_by' => $owner->owner_id,
            'approved_at' => now()->toIso8601String(),
            'provisioned_at' => null,
            'expires_at' => null,
            'registration_reference' => $req->request_reference,
        ];
        $errors = $this->validator->validate([$entry]);
        if ($errors) {
            throw new InvalidArgumentException('Entri registry tidak valid: ' . json_encode($errors));
        }

        $this->registry->append($this->registry->approvedFile(), $entry);

        DB::transaction(function () use ($req, $owner, $approvedRole, $notes, $traceId) {
            $old = $req->approval_status;
            $req->update([
                'approval_status' => 'APPROVED', 'approved_role' => $approvedRole, 'trace_id' => $traceId,
                'review_notes' => $notes, 'reviewed_by' => $owner->id, 'reviewed_at' => now(),
            ]);
            $actor = ['type' => 'OWNER', 'label' => $owner->owner_id];
            AccountLifecycle::recordTransition('REGISTRATION', $req->request_reference, $old, 'APPROVED', $actor, $notes);
            $this->audit->log('REGISTRATION_APPROVED', ['type' => 'RegistrationRequest', 'id' => $req->request_reference], [
                'trace_id' => $traceId, 'requested_role' => $req->requested_role, 'approved_role' => $approvedRole,
            ], null, 'SUCCESS', $actor);
        });

        return $traceId;
    }

    public function reject(RegistrationRequest $req, PlatformOwner $owner, string $notes): void
    {
        $this->guardState($req, ['PENDING REVIEW', 'UNDER REVIEW', 'REVISION REQUIRED']);
        $this->move($req, 'REJECTED', $owner, 'REGISTRATION_REJECTED', $notes);
    }

    public function requestRevision(RegistrationRequest $req, PlatformOwner $owner, string $notes): void
    {
        $this->guardState($req, ['PENDING REVIEW', 'UNDER REVIEW']);
        $this->move($req, 'REVISION REQUIRED', $owner, 'REGISTRATION_REVISION_REQUESTED', $notes);
    }

    public function applicantRevise(RegistrationRequest $req, array $data): void
    {
        $this->guardState($req, ['REVISION REQUIRED']);
        $old = $req->approval_status;
        $req->update([
            'access_reason' => $data['access_reason'] ?? $req->access_reason,
            'verification_info' => $data['verification_info'] ?? $req->verification_info,
            'approval_status' => 'PENDING REVIEW',
        ]);
        $actor = ['type' => 'APPLICANT', 'label' => $req->request_reference];
        AccountLifecycle::recordTransition('REGISTRATION', $req->request_reference, $old, 'PENDING REVIEW', $actor, 'Revisi dikirim pemohon.');
        $this->audit->log('REGISTRATION_REVISED', ['type' => 'RegistrationRequest', 'id' => $req->request_reference], [], null, 'SUCCESS', $actor);
    }

    /**
     * Menjalankan provisioning untuk satu permohonan yang disetujui.
     * @return string|null kata sandi sementara (ditampilkan satu kali kepada Owner)
     */
    public function provision(RegistrationRequest $req, PlatformOwner $owner): ?string
    {
        $this->guardState($req, ['APPROVED']);
        $actor = ['type' => 'OWNER', 'label' => $owner->owner_id];
        $out = $this->provisioner->provisionAll(false, $req->trace_id, $actor);
        if ($out['errors']) {
            throw new InvalidArgumentException('Registry gagal validasi; provisioning dibatalkan.');
        }
        return $out['issued'][$req->trace_id] ?? null;
    }

    private function move(RegistrationRequest $req, string $to, PlatformOwner $owner, string $auditAction, ?string $notes = null): void
    {
        DB::transaction(function () use ($req, $to, $owner, $auditAction, $notes) {
            $old = $req->approval_status;
            $req->update([
                'approval_status' => $to,
                'review_notes' => $notes ?? $req->review_notes,
                'reviewed_by' => $owner->id,
                'reviewed_at' => now(),
            ]);
            $actor = ['type' => 'OWNER', 'label' => $owner->owner_id];
            AccountLifecycle::recordTransition('REGISTRATION', $req->request_reference, $old, $to, $actor, $notes);
            $this->audit->log($auditAction, ['type' => 'RegistrationRequest', 'id' => $req->request_reference], [], null, 'SUCCESS', $actor);
        });
    }

    private function guardState(RegistrationRequest $req, array $allowed): void
    {
        if (! in_array($req->approval_status, $allowed, true)) {
            throw new InvalidArgumentException("Status permohonan {$req->approval_status} tidak mengizinkan aksi ini.");
        }
    }
}
