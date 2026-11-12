<?php

namespace App\Services\Identity;

use App\Models\User;

class TraceIdGenerator
{
    // Tanpa karakter ambigu (0/O, 1/I).
    private const ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    public function __construct(private RegistryStore $registry)
    {
    }

    public function generate(): string
    {
        for ($i = 0; $i < 50; $i++) {
            $id = 'TRS-' . $this->chunk() . '-' . $this->chunk();
            if (! User::where('trace_id', $id)->exists()
                && ! $this->registry->traceIdExists($id)) {
                return $id;
            }
        }
        throw new \RuntimeException('Gagal menghasilkan Trace-ID unik.');
    }

    public static function isValid(string $id): bool
    {
        return (bool) preg_match('/^TRS-[A-Z0-9]{4}-[A-Z0-9]{4}$/', $id);
    }

    private function chunk(): string
    {
        $s = '';
        for ($i = 0; $i < 4; $i++) {
            $s .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
        }
        return $s;
    }

    /** Kata sandi sementara acak berbasis CSPRNG (tanpa karakter ambigu). */
    public static function temporaryPassword(?int $length = null): string
    {
        $length ??= (int) config('trace.provisioning.password_length', 16);
        $groups = ['ABCDEFGHJKLMNPQRSTUVWXYZ', 'abcdefghijkmnpqrstuvwxyz', '23456789', '!@#$%*?-'];
        $pool = implode('', $groups);
        $chars = [];
        foreach ($groups as $g) {
            $chars[] = $g[random_int(0, strlen($g) - 1)];
        }
        while (count($chars) < $length) {
            $chars[] = $pool[random_int(0, strlen($pool) - 1)];
        }
        for ($i = count($chars) - 1; $i > 0; $i--) {
            $j = random_int(0, $i);
            [$chars[$i], $chars[$j]] = [$chars[$j], $chars[$i]];
        }
        return implode('', $chars);
    }
}
