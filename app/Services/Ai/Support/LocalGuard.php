<?php

namespace App\Services\Ai\Support;

use App\Services\Ai\Exceptions\AiUnavailableException;

/**
 * Menegakkan AI_REQUIRE_LOCAL: AI Server harus berada di localhost / intranet.
 * Tidak ada fallback otomatis dari lokal ke cloud.
 */
class LocalGuard
{
    public static function isLocalHost(string $host): bool
    {
        $host = strtolower(trim($host, '[]'));
        if ($host === '' ) {
            return false;
        }
        if ($host === 'localhost' || str_ends_with($host, '.local') || str_ends_with($host, '.lan')
            || str_ends_with($host, '.internal') || str_ends_with($host, '.intra')) {
            return true;
        }
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return !filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
        }
        // Nama host tanpa titik dianggap nama intranet
        return !str_contains($host, '.');
    }

    public static function assertLocal(?string $baseUrl): void
    {
        if (!config('trace.ai.require_local')) {
            return;
        }
        $host = parse_url((string) $baseUrl, PHP_URL_HOST) ?: '';
        if (!self::isLocalHost($host)) {
            throw new AiUnavailableException(
                'AI_REQUIRE_LOCAL aktif: endpoint AI bukan alamat lokal/intranet (' . ($host ?: 'kosong') . ').',
                'ERROR'
            );
        }
    }
}
