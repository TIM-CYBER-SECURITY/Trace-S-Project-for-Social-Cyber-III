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
    /** key => [label, tipe, default, aturan validasi] — ditampilkan pada UI System Settings */
    public const DEFINITIONS = [
        'classification_label' => ['Label klasifikasi (banner)', 'string', null, 'nullable|string|max:80'],
        'system_notice' => ['Pengumuman sistem (opsional)', 'string', '', 'nullable|string|max:200'],
        'record_retention_days' => ['Masa retensi arsip sebelum boleh dihapus (hari)', 'int', 90, 'required|integer|min:0|max:3650'],
    ];

    /** Kunci konfigurasi AI internal (dikelola eksklusif oleh AiManagementController & AiManager) */
    public const INTERNAL_KEYS = [
        'ai.driver',
        'ai.base_url',
        'ai.model',
        'ai.embedding_model',
        'ai.timeout',
        'ai.allow_external',
        'ai.api_key_enc',
        'ai.engine_mode',
        'ai.cloud_provider',
        'ai.cloud_timeout',
        'ai.google_model',
        'ai.google_api_key_enc',
        'ai.exa_model',
        'ai.exa_api_key_enc',
        'ai.exa_search_enabled',
    ];

    private ?array $cache = null;

    public function flushCache(): void
    {
        $this->cache = null;
    }

    private function all(): array
    {
        if ($this->cache === null) {
            try {
                $this->cache = Schema::hasTable('app_settings')
                    ? DB::table('app_settings')->pluck('value', 'key')->all()
                    : [];
            } catch (\Throwable) {
                $this->cache = [];
            }
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

    public function has(string $key): bool
    {
        $all = $this->all();
        return array_key_exists($key, $all) && $all[$key] !== null && $all[$key] !== '';
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

    /** Simpan kunci internal (mis. ai.*); rahasia tidak pernah dicatat dalam audit metadata. */
    public function setInternal(string $key, ?string $value, ?User $by = null): void
    {
        if (! in_array($key, self::INTERNAL_KEYS, true)) {
            throw new \InvalidArgumentException("Kunci internal tidak dikenal: {$key}");
        }
        DB::table('app_settings')->updateOrInsert(['key' => $key], [
            'value' => $value,
            'updated_by' => $by?->id,
            'updated_at' => now(),
        ]);
        $this->cache = null;
    }
}
