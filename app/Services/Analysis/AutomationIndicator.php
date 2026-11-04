<?php

namespace App\Services\Analysis;

/**
 * Automation Indicator: indikator otomatisasi yang dapat dijelaskan, BUKAN
 * "bot probability". Hanya dinilai bila data laju posting dan umur akun
 * benar-benar tersedia; selain itu "TIDAK DINILAI".
 */
class AutomationIndicator
{
    /** @return array{level:string,basis:string} */
    public static function assess(?float $postRatePerHour, ?int $accountAgeDays): array
    {
        if ($postRatePerHour === null && $accountAgeDays === null) {
            return ['level' => 'TIDAK DINILAI', 'basis' => 'Data laju posting dan umur akun tidak tersedia pada dataset.'];
        }
        $reasons = [];
        $score = 0;
        if ($postRatePerHour !== null) {
            if ($postRatePerHour >= 30) {
                $score += 2;
                $reasons[] = "laju posting {$postRatePerHour}/jam sangat tinggi";
            } elseif ($postRatePerHour >= 10) {
                $score += 1;
                $reasons[] = "laju posting {$postRatePerHour}/jam di atas rata-rata";
            }
        }
        if ($accountAgeDays !== null && $accountAgeDays < 14 && ($postRatePerHour ?? 0) >= 5) {
            $score += 1;
            $reasons[] = "akun berusia {$accountAgeDays} hari dengan aktivitas tinggi";
        }
        $level = $score >= 3 ? 'TINGGI' : ($score >= 1 ? 'SEDANG' : 'RENDAH');
        $basis = $reasons ? ucfirst(implode('; ', $reasons)) . '. Indikator ini perlu ditinjau manusia, bukan bukti otomatisasi.'
            : 'Tidak ada indikator otomatisasi menonjol pada data yang tersedia.';
        return ['level' => $level, 'basis' => $basis];
    }
}
