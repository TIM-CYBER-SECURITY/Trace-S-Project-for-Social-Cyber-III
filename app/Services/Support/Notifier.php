<?php

namespace App\Services\Support;

use App\Models\AppNotification;
use App\Models\User;
use Illuminate\Support\Collection;

/** Notifikasi aplikasi nyata: hanya dibuat oleh peristiwa aktual. */
class Notifier
{
    public function notify(User|Collection|array $recipients, string $type, string $title, string $message, ?string $link = null): void
    {
        $list = $recipients instanceof User ? [$recipients] : $recipients;
        foreach ($list as $u) {
            AppNotification::create([
                'recipient_id' => $u->id,
                'notification_type' => $type,
                'title' => mb_substr($title, 0, 160),
                'message' => $message,
                'link' => $link,
                'created_at' => now(),
            ]);
        }
    }

    /** Pengguna aktif ber-izin tertentu pada organisasi yang sama (mis. info.review). */
    public function usersWithPermission(string $permissionKey, ?int $organizationId = null): Collection
    {
        return User::with('roleRef')
            ->where('account_status', 'ACTIVE')
            ->when($organizationId, fn ($q) => $q->where('organization_id', $organizationId))
            ->get()
            ->filter(fn (User $u) => $u->hasPermission($permissionKey))
            ->values();
    }
}
