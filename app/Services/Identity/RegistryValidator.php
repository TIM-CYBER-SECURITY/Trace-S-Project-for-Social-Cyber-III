<?php

namespace App\Services\Identity;

use RuntimeException;

/**
 * Validator registry yang MEMBACA berkas JSON Schema (provisioning/schema) sebagai
 * sumber kebenaran: required, type, enum, pattern, minLength, format date-time,
 * additionalProperties. Ditambah aturan lintas-entri (duplikat, persetujuan).
 */
class RegistryValidator
{
    private array $schema;

    public function __construct(private RegistryStore $store)
    {
        $path = $store->schemaPath();
        if (! is_file($path)) {
            throw new RuntimeException('JSON Schema registry tidak ditemukan: ' . $path);
        }
        $this->schema = json_decode(file_get_contents($path), true) ?: [];
    }

    /**
     * @return array<string,string[]> kunci = "#index (TRACE-ID)", nilai = daftar pesan
     */
    public function validate(array $entries): array
    {
        $errors = [];
        $seen = [];

        foreach ($entries as $i => $entry) {
            $label = '#' . ($i + 1) . ' (' . (is_array($entry) ? ($entry['trace_id'] ?? 'tanpa trace_id') : 'bukan objek') . ')';
            if (! is_array($entry) || array_is_list($entry) && $entry !== []) {
                $errors[$label][] = 'Entri harus berupa objek JSON.';
                continue;
            }

            foreach ($this->validateAgainstSchema($entry) as $msg) {
                $errors[$label][] = $msg;
            }

            $tid = $entry['trace_id'] ?? null;
            if (is_string($tid)) {
                if (isset($seen[$tid])) {
                    $errors[$label][] = "Trace-ID duplikat dengan entri #{$seen[$tid]}.";
                }
                $seen[$tid] = $i + 1;
            }

            if (($entry['approval_status'] ?? null) === 'APPROVED') {
                if (empty($entry['approved_by'])) {
                    $errors[$label][] = 'approved_by wajib diisi untuk entri APPROVED.';
                }
                if (empty($entry['approved_at'])) {
                    $errors[$label][] = 'approved_at wajib diisi untuk entri APPROVED.';
                }
            }
            if (($entry['approval_status'] ?? null) === 'REJECTED'
                && in_array($entry['account_status'] ?? null, ['PROVISIONED', 'ACTIVE'], true)) {
                $errors[$label][] = 'Entri REJECTED tidak boleh berstatus PROVISIONED/ACTIVE.';
            }
        }

        return $errors;
    }

    /** @return string[] */
    private function validateAgainstSchema(array $entry): array
    {
        $msgs = [];
        $props = $this->schema['properties'] ?? [];

        foreach ($this->schema['required'] ?? [] as $req) {
            if (! array_key_exists($req, $entry)) {
                $msgs[] = "Properti wajib hilang: {$req}.";
            }
        }

        if (($this->schema['additionalProperties'] ?? true) === false) {
            foreach (array_keys($entry) as $k) {
                if (! isset($props[$k])) {
                    $msgs[] = "Properti tidak dikenal: {$k}.";
                }
            }
        }

        foreach ($entry as $k => $v) {
            if (! isset($props[$k])) {
                continue;
            }
            foreach ($this->checkValue($k, $v, $props[$k]) as $m) {
                $msgs[] = $m;
            }
        }

        return $msgs;
    }

    /** @return string[] */
    private function checkValue(string $key, mixed $value, array $rule): array
    {
        $msgs = [];
        $types = (array) ($rule['type'] ?? []);

        if ($types) {
            $ok = false;
            foreach ($types as $t) {
                $ok = $ok || match ($t) {
                    'string' => is_string($value),
                    'null' => $value === null,
                    'integer' => is_int($value),
                    'boolean' => is_bool($value),
                    default => true,
                };
            }
            if (! $ok) {
                return ["{$key}: tipe data tidak valid (diharapkan " . implode('|', $types) . ').'];
            }
        }

        if (! is_string($value)) {
            return $msgs;
        }

        if (isset($rule['enum']) && ! in_array($value, $rule['enum'], true)) {
            $msgs[] = "{$key}: nilai '{$value}' tidak diizinkan (" . implode(', ', $rule['enum']) . ').';
        }
        if (isset($rule['pattern']) && ! preg_match('/' . str_replace('/', '\/', $rule['pattern']) . '/', $value)) {
            $msgs[] = "{$key}: format tidak sesuai pola {$rule['pattern']}.";
        }
        if (isset($rule['minLength']) && mb_strlen($value) < (int) $rule['minLength']) {
            $msgs[] = "{$key}: minimal {$rule['minLength']} karakter.";
        }
        if (($rule['format'] ?? null) === 'date-time' && ! self::isDateTime($value)) {
            $msgs[] = "{$key}: bukan date-time RFC 3339 yang valid.";
        }

        return $msgs;
    }

    public static function isDateTime(string $value): bool
    {
        if (! preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(\.\d+)?(Z|[+-]\d{2}:\d{2})$/', $value)) {
            return false;
        }
        try {
            new \DateTimeImmutable($value);
            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}
