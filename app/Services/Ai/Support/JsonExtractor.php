<?php

namespace App\Services\Ai\Support;

class JsonExtractor
{
    /** Ambil objek JSON dari keluaran model (toleran terhadap pagar kode / teks sisa). */
    public static function extract(string $raw): ?array
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }
        $raw = preg_replace('/^```(?:json)?\s*|\s*```$/i', '', $raw);

        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        $start = strpos($raw, '{');
        $end = strrpos($raw, '}');
        if ($start !== false && $end !== false && $end > $start) {
            $decoded = json_decode(substr($raw, $start, $end - $start + 1), true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }
        return null;
    }
}
