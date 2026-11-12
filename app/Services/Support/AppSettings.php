<?php

namespace App\Services\Support;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Pengaturan aplikasi yang dapat diubah Administrator saat runtime (dengan audit).
 * Hanya kunci dalam allowlist yang diterima; nilai disimpan di database, bukan di kode.
 */
class AppSettings
{
    /** key => [label, tipe, default, aturan validasi] */
    public const DEFINITIONS = [
        'classification_label' => ['Label klasifikasi (banner)', 'string', null, 'nullable|string|max:80'],
        'system_notice' => ['Pengumuman sistem (opsional)', 'string', '', 'nullable|string|max:200'],
        'record_retention_days' => ['Masa retensi arsip sebelum boleh dihapus (hari)', 'int', 90, 'required|integer|min:0|max:3650'],
    ];

    private ?array $cache = null;

    private function all(): array
    {
        if ($this->cache === null) {
            $this->cache = Schema::hasTable('app_settings')
                ? DB::table('app_settings')->pluck('value', 'key')->all()
                : [];
        }
        return $this->cache;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $all = $this->all();
        if (array_key_exists($key, $all) && $all[$key] !== null) {
            return $all[$key];
        }
        $def = self::DEFINITIONS[$key][2] ?? null;
        if ($key === 'classification_label') {
            return config('trace.classification_label');
        }
        return $def ?? $default;
    }

    public function set(string $key, mixed $value, ?User $by, AuditLogger $audit): void
    {
        if (! isset(self::DEFINITIONS[$key])) {
            throw new \InvalidArgumentException("Pengaturan tidak dikenal: {$key}");
        }
        $old = $this->get($key);
        DB::table('app_settings')->updateOrInsert(['key' => $key], [
            'value' => $value === null ? null : (string) $value,
            'updated_by' => $by?->id,
            'updated_at' => now(),
        ]);
        $this->cache = null;
        $audit->log('SETTING_CHANGED', ['type' => 'Setting', 'id' => $key], ['from' => $old, 'to' => $value], $by?->id);
    }
}
