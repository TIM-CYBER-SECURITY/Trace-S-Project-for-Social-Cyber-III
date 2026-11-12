<?php

namespace App\Services\Records;

use App\Models\InformationRecord;
use App\Models\RecordReview;
use App\Models\RecordVersion;
use App\Models\User;
use App\Services\Support\AppSettings;
use App\Services\Support\AuditLogger;
use App\Services\Support\Notifier;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Alur informasi: DRAFT -> SUBMITTED -> UNDER REVIEW -> APPROVED | REJECTED | REVISION REQUIRED -> ARCHIVED.
 * Setiap perubahan konten menghasilkan versi baru (tidak menimpa versi yang disetujui).
 */
class RecordService
{
    public const EDITABLE = ['DRAFT', 'REVISION REQUIRED', 'APPROVED'];

    public function __construct(private AuditLogger $audit, private Notifier $notifier, private AppSettings $settings)
    {
    }

    /** Lingkup visibilitas: organisasi sendiri; Operator hanya milik sendiri + yang telah disetujui. */
    public function visibleTo(User $user): Builder
    {
        $q = InformationRecord::query()->where('organization_id', $user->organization_id ?? 0);
        if (! $user->isAdministrator()) {
            $q->where(fn ($w) => $w->where('created_by', $user->id)->orWhere('status', 'APPROVED'));
        }
        return $q;
    }

    public function canView(User $user, InformationRecord $r): bool
    {
        return $user->hasPermission('info.view') && $this->visibleTo($user)->whereKey($r->id)->exists();
    }

    public function canEdit(User $user, InformationRecord $r): bool
    {
        return $user->hasPermission('info.update')
            && $r->organization_id === $user->organization_id
            && in_array($r->status, self::EDITABLE, true)
            && ($user->isAdministrator() || $r->created_by === $user->id);
    }

    public function create(User $user, array $d): InformationRecord
    {
        if (! $user->organization_id) {
            throw new InvalidArgumentException('Akun belum terikat organisasi.');
        }

        return DB::transaction(function () use ($user, $d) {
            $r = InformationRecord::create([
                'record_code' => 'TMP-' . Str::random(12),
                'title' => $d['title'], 'description' => $d['description'] ?? null, 'category' => $d['category'],
                'status' => 'DRAFT', 'organization_id' => $user->organization_id, 'created_by' => $user->id,
                'current_version' => 1,
            ]);
            $r->update(['record_code' => sprintf('INF-%s-%06d', now()->format('Y'), $r->id)]);
            $this->addVersion($r, $user, 1, $d, $d['change_reason'] ?? 'Draft awal');
            $this->audit->log('RECORD_CREATED', $r, ['code' => $r->record_code], $user->id);
            return $r;
        });
    }

    public function update(User $user, InformationRecord $r, array $d): InformationRecord
    {
        if (! $this->canEdit($user, $r)) {
            throw new InvalidArgumentException('Rekaman tidak dapat diubah pada status ini.');
        }

        return DB::transaction(function () use ($user, $r, $d) {
            $fromApproved = $r->status === 'APPROVED';
            $n = $r->current_version + 1;
            $this->addVersion($r, $user, $n, $d, $d['change_reason'] ?? ($fromApproved ? 'Revisi atas versi yang disetujui' : 'Perubahan'));
            // Versi yang disetujui tetap tersimpan (approved_version); versi baru kembali ke DRAFT.
            $r->update([
                'title' => $d['title'], 'description' => $d['description'] ?? null, 'category' => $d['category'],
                'current_version' => $n, 'status' => $fromApproved ? 'DRAFT' : $r->status,
            ]);
            $this->audit->log('RECORD_REVISED', $r, ['code' => $r->record_code, 'version' => $n, 'from_approved' => $fromApproved], $user->id);
            return $r->refresh();
        });
    }

    public function submit(User $user, InformationRecord $r): void
    {
        if ($r->created_by !== $user->id || ! $user->hasPermission('info.submit')) {
            throw new InvalidArgumentException('Hanya pembuat rekaman yang dapat mengajukan.');
        }
        $this->assertStatus($r, ['DRAFT', 'REVISION REQUIRED']);

        $r->update(['status' => 'SUBMITTED', 'submitted_at' => now()]);
        $this->audit->log('RECORD_SUBMITTED', $r, ['code' => $r->record_code, 'version' => $r->current_version], $user->id);
        $this->notifier->notify($user, 'SUBMISSION', 'Pengajuan diterima', "{$r->record_code} (v{$r->current_version}) telah diajukan untuk review.", route('records.show', $r->record_code));
        $this->notifier->notify(
            $this->notifier->usersWithPermission('info.review', $r->organization_id)->where('id', '!=', $user->id),
            'SUBMISSION', 'Pengajuan baru menunggu review', "{$r->record_code}: {$r->title}", route('records.show', $r->record_code)
        );
    }

    public function startReview(User $admin, InformationRecord $r): void
    {
        $this->guardReviewer($admin, $r);
        $this->assertStatus($r, ['SUBMITTED']);
        $r->update(['status' => 'UNDER REVIEW', 'reviewed_by' => $admin->id]);
        $this->review($r, $admin, 'UNDER REVIEW', null);
        $this->audit->log('RECORD_UNDER_REVIEW', $r, ['code' => $r->record_code], $admin->id);
    }

    public function decide(User $admin, InformationRecord $r, string $decision, ?string $comment): void
    {
        if (! in_array($decision, ['APPROVED', 'REJECTED', 'REVISION REQUIRED'], true)) {
            throw new InvalidArgumentException('Keputusan tidak valid.');
        }
        if ($decision !== 'APPROVED' && blank($comment)) {
            throw new InvalidArgumentException('Komentar wajib untuk penolakan atau permintaan revisi.');
        }
        $this->guardReviewer($admin, $r);
        $this->assertStatus($r, ['SUBMITTED', 'UNDER REVIEW']);

        DB::transaction(function () use ($admin, $r, $decision, $comment) {
            $attrs = ['status' => $decision, 'reviewed_by' => $admin->id];
            if ($decision === 'APPROVED') {
                $attrs += ['approved_version' => $r->current_version, 'approved_at' => now()];
            }
            $r->update($attrs);
            $this->review($r, $admin, $decision, $comment);
            $this->audit->log('RECORD_' . str_replace(' ', '_', $decision), $r, ['code' => $r->record_code, 'version' => $r->current_version], $admin->id);
        });

        $creator = User::find($r->created_by);
        if ($creator) {
            $title = match ($decision) {
                'APPROVED' => 'Pengajuan disetujui',
                'REJECTED' => 'Pengajuan ditolak',
                default => 'Revisi diminta',
            };
            $this->notifier->notify($creator, 'SUBMISSION', $title, "{$r->record_code}: " . ($comment ?: 'Tanpa komentar.'), route('records.show', $r->record_code));
        }
    }

    public function archive(User $user, InformationRecord $r, string $reason): void
    {
        if (! $user->hasPermission('info.delete') || $r->organization_id !== $user->organization_id) {
            throw new InvalidArgumentException('Tidak berwenang mengarsipkan rekaman ini.');
        }
        $this->assertStatus($r, ['APPROVED', 'REJECTED']);
        $r->update(['status' => 'ARCHIVED', 'archived_at' => now()]);
        $this->audit->log('RECORD_ARCHIVED', $r, ['code' => $r->record_code, 'reason' => $reason], $user->id);
    }

    /** Hapus lunak: hanya rekaman ARCHIVED yang telah melewati masa retensi. */
    public function destroy(User $user, InformationRecord $r, string $reason): void
    {
        if (! $user->hasPermission('info.delete') || $r->organization_id !== $user->organization_id) {
            $this->audit->log('RECORD_DELETE_DENIED', $r, ['code' => $r->record_code], $user->id, 'DENIED');
            throw new InvalidArgumentException('Tidak berwenang menghapus rekaman ini.');
        }
        $this->assertStatus($r, ['ARCHIVED']);
        $days = (int) $this->settings->get('record_retention_days', 90);
        if ($r->archived_at && $r->archived_at->diffInDays(now(), false) < $days) {
            throw new InvalidArgumentException("Masa retensi {$days} hari belum terpenuhi sejak pengarsipan.");
        }
        $this->audit->log('RECORD_DELETED', $r, ['code' => $r->record_code, 'reason' => $reason, 'soft' => true], $user->id);
        $r->delete();
    }

    private function addVersion(InformationRecord $r, User $user, int $n, array $d, ?string $reason): void
    {
        RecordVersion::create([
            'record_id' => $r->id, 'version_number' => $n, 'title' => $d['title'], 'description' => $d['description'] ?? null,
            'category' => $d['category'], 'content' => $d['content'], 'changed_by' => $user->id,
            'change_reason' => $reason ? mb_substr($reason, 0, 250) : null,
        ]);
    }

    private function review(InformationRecord $r, User $admin, string $decision, ?string $comment): void
    {
        RecordReview::create([
            'record_id' => $r->id, 'version_number' => $r->current_version, 'reviewer_id' => $admin->id,
            'decision' => $decision, 'comment' => $comment,
        ]);
    }

    private function guardReviewer(User $admin, InformationRecord $r): void
    {
        if (! $admin->hasPermission('info.review') || ! $admin->hasPermission('info.approve') || $r->organization_id !== $admin->organization_id) {
            $this->audit->log('RECORD_REVIEW_DENIED', $r, ['code' => $r->record_code], $admin->id, 'DENIED');
            throw new InvalidArgumentException('Tidak berwenang mereview rekaman ini.');
        }
        if ($r->created_by === $admin->id) {
            throw new InvalidArgumentException('Pemisahan tugas: Anda tidak dapat mereview/menyetujui pengajuan sendiri.');
        }
    }

    private function assertStatus(InformationRecord $r, array $allowed): void
    {
        if (! in_array($r->status, $allowed, true)) {
            throw new InvalidArgumentException("Aksi tidak diizinkan pada status {$r->status}.");
        }
    }
}
