<?php

namespace App\Services\Support;

use App\Models\PlatformOwner;
use App\Models\SystemLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Audit log append-only dengan rantai hash (tamper-evident).
 * - Penegakan append-only: trigger database + guard model.
 * - Setiap baris menyimpan hash baris sebelumnya; `trace:audit-verify` memverifikasi rantai.
 * - Metadata disaring: kunci yang menyerupai kata sandi/token/rahasia tidak pernah disimpan.
 */
class AuditLogger
{
    private const SENSITIVE = '/pass(word)?|token|secret|credential|authorization|api[_-]?key|cookie/i';

    public function log(
        string $action,
        Model|array|null $target = null,
        array $meta = [],
        ?int $userId = null,
        string $result = 'SUCCESS',
        ?array $actor = null,
    ): SystemLog {
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

        [$actorType, $actorLabel, $resolvedUserId] = $this->resolveActor($actor, $userId);
        $meta = $this->sanitize($meta);

        return DB::transaction(function () use ($action, $result, $type, $id, $req, $meta, $actorType, $actorLabel, $resolvedUserId) {
            $prev = SystemLog::query()->orderByDesc('id')->value('entry_hash') ?? '';

            $payload = [
                'user_id' => $resolvedUserId,
                'actor_type' => $actorType,
                'actor_label' => $actorLabel,
                'action' => $action,
                'result' => $result,
                'target_type' => $type,
                'target_id' => $id,
                'ip' => $req?->ip(),
                'user_agent' => $req ? mb_substr((string) $req->userAgent(), 0, 250) : null,
                'metadata' => $meta ?: null,
                'created_at' => now(),
            ];
            $payload['prev_hash'] = $prev;
            $payload['entry_hash'] = self::hashEntry($prev, $payload);

            return SystemLog::create($payload);
        });
    }

    /** Hash deterministik atas bidang inti (dipakai saat menulis dan memverifikasi). */
    public static function hashEntry(string $prev, array $row): string
    {
        $meta = $row['metadata'] ?? null;
        $canonical = json_encode([
            $prev,
            $row['user_id'] ?? null,
            $row['actor_type'] ?? null,
            $row['actor_label'] ?? null,
            $row['action'] ?? null,
            $row['result'] ?? null,
            $row['target_type'] ?? null,
            $row['target_id'] ?? null,
            $meta === null ? null : self::ksortRecursive($meta),
            ($row['created_at'] instanceof \DateTimeInterface) ? $row['created_at']->format('Y-m-d H:i:s') : (string) ($row['created_at'] ?? ''),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return hash('sha256', (string) $canonical);
    }

    public static function ksortRecursive(mixed $value): mixed
    {
        if (is_array($value)) {
            ksort($value);
            return array_map([self::class, 'ksortRecursive'], $value);
        }
        return $value;
    }

    private function resolveActor(?array $actor, ?int $userId): array
    {
        if ($actor) {
            return [$actor['type'] ?? 'SYSTEM', $actor['label'] ?? null, $actor['user_id'] ?? $userId];
        }

        $owner = Auth::guard('platform')->user();
        if ($owner instanceof PlatformOwner && $userId === null) {
            return ['OWNER', $owner->owner_id, null];
        }

        $user = $userId ? User::find($userId) : Auth::guard('web')->user();
        if ($user instanceof User) {
            return ['USER', $user->trace_id ?? ('user#' . $user->id), $user->id];
        }

        return [request() ? 'ANON' : 'SYSTEM', null, null];
    }

    private function sanitize(array $meta): array
    {
        $clean = [];
        foreach ($meta as $k => $v) {
            if (is_string($k) && preg_match(self::SENSITIVE, $k)) {
                continue;
            }
            $clean[$k] = is_array($v) ? $this->sanitize($v) : $v;
        }
        return $clean;
    }
}
