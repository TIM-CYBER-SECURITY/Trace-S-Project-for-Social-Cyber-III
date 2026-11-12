<?php

namespace App\Services\Identity;

use RuntimeException;

/**
 * Akses ke registry Trace-ID eksternal (berkas JSON privat di luar web root).
 * Registry BUKAN basis data autentikasi: hanya catatan provisioning.
 * Tidak pernah menyimpan kata sandi, hash, rahasia pemulihan, atau token sesi.
 */
class RegistryStore
{
    public static function resolve(?string $path, string $fallback): string
    {
        $path = $path ?: $fallback;
        $isAbsolute = (bool) preg_match('~^([A-Za-z]:[\\\\/]|[\\\\/])~', $path);
        return rtrim($isAbsolute ? $path : base_path($path), '\\/');
    }

    public function base(): string
    {
        return self::resolve(config('trace.provisioning.path'), base_path('provisioning'));
    }

    public function registryDir(): string
    {
        return self::resolve(config('trace.provisioning.registry'), $this->base() . '/registry');
    }

    public function issuedDir(): string
    {
        return self::resolve(config('trace.provisioning.issued_path'), $this->base() . '/issued');
    }

    public function schemaPath(): string
    {
        // Skema adalah artefak repositori (bukan data privat); selalu dari direktori aplikasi.
        return base_path('provisioning/schema/trace-id-account.schema.json');
    }

    public function approvedFile(): string { return $this->registryDir() . '/approved-accounts.json'; }
    public function pendingFile(): string { return $this->registryDir() . '/pending-accounts.json'; }

    /** @return array<int,array> */
    public function read(string $file): array
    {
        if (! is_file($file)) {
            return [];
        }
        $raw = file_get_contents($file);
        if ($raw === false || trim($raw) === '') {
            return [];
        }
        $data = json_decode($raw, true);
        if (! is_array($data)) {
            throw new RuntimeException('Registry bukan JSON yang valid: ' . basename($file) . ' (' . json_last_error_msg() . ')');
        }
        $accounts = array_is_list($data) ? $data : ($data['accounts'] ?? null);
        if (! is_array($accounts)) {
            throw new RuntimeException('Registry harus berisi objek {"accounts": [...]} atau array entri.');
        }
        return array_values($accounts);
    }

    public function approved(): array { return $this->read($this->approvedFile()); }

    /** Menulis seluruh daftar secara atomik (tmp + rename) dengan kunci berkas. */
    public function write(string $file, array $accounts): void
    {
        $dir = dirname($file);
        if (! is_dir($dir) && ! @mkdir($dir, 0750, true) && ! is_dir($dir)) {
            throw new RuntimeException('Tidak dapat membuat direktori registry.');
        }
        $json = json_encode(['accounts' => array_values($accounts)], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $tmp = $file . '.' . bin2hex(random_bytes(4)) . '.tmp';
        if (file_put_contents($tmp, $json . PHP_EOL, LOCK_EX) === false) {
            throw new RuntimeException('Gagal menulis registry sementara.');
        }
        @chmod($tmp, 0640);
        if (! @rename($tmp, $file)) {
            @unlink($tmp);
            throw new RuntimeException('Gagal memperbarui registry.');
        }
    }

    public function append(string $file, array $entry): void
    {
        $lock = fopen($this->registryDirEnsure() . '/.lock', 'c');
        flock($lock, LOCK_EX);
        try {
            $accounts = $this->read($file);
            foreach ($accounts as $a) {
                if (($a['trace_id'] ?? null) === ($entry['trace_id'] ?? '')) {
                    throw new RuntimeException('Trace-ID sudah ada di registry: ' . $entry['trace_id']);
                }
            }
            $accounts[] = $entry;
            $this->write($file, $accounts);
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /** Menyelaraskan account_status entri registry (best-effort; DB tetap otoritatif). */
    public function setAccountStatus(string $traceId, string $status): bool
    {
        $file = $this->approvedFile();
        if (! is_file($file)) {
            return false;
        }
        $accounts = $this->read($file);
        $changed = false;
        foreach ($accounts as &$a) {
            if (($a['trace_id'] ?? null) === $traceId && ($a['account_status'] ?? null) !== $status) {
                $a['account_status'] = $status;
                $changed = true;
            }
        }
        unset($a);
        if ($changed) {
            $this->write($file, $accounts);
        }
        return $changed;
    }

    public function traceIdExists(string $traceId): bool
    {
        foreach ([$this->approvedFile(), $this->pendingFile()] as $f) {
            try {
                foreach ($this->read($f) as $a) {
                    if (($a['trace_id'] ?? null) === $traceId) {
                        return true;
                    }
                }
            } catch (\Throwable) {
            }
        }
        return false;
    }

    private function registryDirEnsure(): string
    {
        $dir = $this->registryDir();
        if (! is_dir($dir)) {
            @mkdir($dir, 0750, true);
        }
        return $dir;
    }
}
