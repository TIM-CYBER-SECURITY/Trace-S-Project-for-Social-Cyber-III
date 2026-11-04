<?php

namespace App\Services\Support;

use App\Models\SystemLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Audit log append-only. Setiap operasi penting (analisis, pergantian model,
 * akses bukti, ekspor, login) dicatat dengan pengguna, IP, dan metadata.
 */
class AuditLogger
{
    public function log(string $action, Model|array|null $target = null, array $meta = [], ?int $userId = null): SystemLog
    {
        $req = request();
        $type = null;
        $id = null;
        if ($target instanceof Model) {
            $type = class_basename($target);
            $id = (string) $target->getKey();
        } elseif (is_array($target)) {
            $type = $target['type'] ?? null;
            $id = isset($target['id']) ? (string) $target['id'] : null;
        }

        return SystemLog::create([
            'user_id' => $userId ?? Auth::id(),
            'action' => $action,
            'target_type' => $type,
            'target_id' => $id,
            'ip' => $req?->ip(),
            'user_agent' => $req ? mb_substr((string) $req->userAgent(), 0, 250) : null,
            'metadata' => $meta ?: null,
        ]);
    }
}
