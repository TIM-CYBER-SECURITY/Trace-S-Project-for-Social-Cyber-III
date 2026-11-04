<?php

namespace App\Services\Analysis;

/**
 * Numeric & Entity Consistency (Supplemental §5.2-D).
 * Ekstraksi angka, tanggal, dan entitas dengan aturan eksplisit; perubahan
 * angka/entitas penting ditandai secara khusus.
 */
class NumericEntityComparer
{
    private const MONTHS = [
        'januari' => 1, 'februari' => 2, 'maret' => 3, 'april' => 4, 'mei' => 5, 'juni' => 6, 'juli' => 7,
        'agustus' => 8, 'september' => 9, 'oktober' => 10, 'november' => 11, 'desember' => 12,
    ];

    private const UNIT_MULT = [
        'ribu' => 1_000, 'juta' => 1_000_000, 'miliar' => 1_000_000_000, 'milyar' => 1_000_000_000,
        'triliun' => 1_000_000_000_000, 'ratus' => 100,
    ];

    /** @return array<int,array{raw:string,value:float,kind:string,key:string}> */
    public function numbers(string $text): array
    {
        $out = [];
        $pattern = '/(Rp\.?\s*)?(\d{1,3}(?:\.\d{3})+|\d+(?:,\d+)?)\s*(%|persen|ribu|juta|miliar|milyar|triliun|ratus)?/iu';
        if (!preg_match_all($pattern, $text, $m, PREG_SET_ORDER)) {
            return [];
        }
        foreach ($m as $hit) {
            $rp = trim($hit[1] ?? '') !== '';
            $numStr = $hit[2];
            $unit = mb_strtolower($hit[3] ?? '', 'UTF-8');

            if (preg_match('/^\d{1,3}(\.\d{3})+$/', $numStr)) {
                $value = (float) str_replace('.', '', $numStr);
            } else {
                $value = (float) str_replace(',', '.', $numStr);
            }

            $kind = 'number';
            if ($unit === '%' || $unit === 'persen') {
                $kind = 'percent';
            } elseif ($rp) {
                $kind = 'currency';
                $value *= self::UNIT_MULT[$unit] ?? 1;
            } elseif ($unit !== '' && isset(self::UNIT_MULT[$unit])) {
                $value *= self::UNIT_MULT[$unit];
            } elseif ($value >= 1900 && $value <= 2100 && floor($value) == $value && $unit === '') {
                $kind = 'year';
            }

            $out[] = [
                'raw' => trim($hit[0]),
                'value' => $value,
                'kind' => $kind,
                'key' => $kind . ':' . rtrim(rtrim(number_format($value, 4, '.', ''), '0'), '.'),
            ];
        }
        return $out;
    }

    /** @return string[] tanggal ISO (Y-m-d) yang berhasil dikenali */
    public function dates(string $text): array
    {
        $dates = [];
        if (preg_match_all('/\b(\d{1,2})\s+(' . implode('|', array_keys(self::MONTHS)) . ')\s+(\d{4})\b/iu', $text, $m, PREG_SET_ORDER)) {
            foreach ($m as $h) {
                $dates[] = sprintf('%04d-%02d-%02d', $h[3], self::MONTHS[mb_strtolower($h[2], 'UTF-8')], $h[1]);
            }
        }
        if (preg_match_all('/\b(\d{4})-(\d{2})-(\d{2})\b/', $text, $m, PREG_SET_ORDER)) {
            foreach ($m as $h) {
                $dates[] = $h[0];
            }
        }
        return array_values(array_unique($dates));
    }

    /** @return string[] penanda waktu relatif (mis. "bulan depan") */
    public function temporalCues(string $text): array
    {
        return Lexicon::matchCues($text, ['bulan depan', 'minggu depan', 'tahun depan', 'besok', 'kemarin', 'hari ini', 'tahun lalu', 'bulan lalu', 'tahun anggaran']);
    }

    /** @return string[] entitas (nama diri & akronim), unik, apa adanya */
    public function entities(string $text): array
    {
        $found = [];
        foreach (TextNormalizer::sentences($text) ?: [$text] as $sentence) {
            // Akronim: MBG, KDMP, APBN
            if (preg_match_all('/\b\p{Lu}{2,}[\p{Lu}\d]*\b/u', $sentence, $m)) {
                foreach ($m[0] as $a) {
                    $found[mb_strtolower($a, 'UTF-8')] = $a;
                }
            }
            // Nama diri: kata berhuruf kapital bukan di awal kalimat
            $words = preg_split('/\s+/u', $sentence, -1, PREG_SPLIT_NO_EMPTY);
            $run = [];
            foreach ($words as $i => $w) {
                $clean = preg_replace('/^[^\p{L}\p{N}]+|[^\p{L}\p{N}]+$/u', '', $w);
                $isCap = $clean !== '' && preg_match('/^\p{Lu}\p{Ll}+$/u', $clean) && $i > 0
                    && !isset(self::MONTHS[mb_strtolower($clean, 'UTF-8')]);
                if ($isCap) {
                    $run[] = $clean;
                } else {
                    if ($run) {
                        $name = implode(' ', $run);
                        $found[mb_strtolower($name, 'UTF-8')] = $name;
                    }
                    $run = [];
                }
            }
            if ($run) {
                $name = implode(' ', $run);
                $found[mb_strtolower($name, 'UTF-8')] = $name;
            }
        }
        return array_values($found);
    }

    /**
     * @return array{
     *   numeric_consistency:string, numbers_a:array, numbers_b:array, numbers_removed:string[], numbers_added:string[],
     *   dates_a:string[], dates_b:string[], dates_changed:bool,
     *   entities_removed:string[], entities_added:string[], entities_shared:string[], temporal_a:string[], temporal_b:string[]
     * }
     */
    public function compare(string $a, string $b): array
    {
        $na = $this->numbers($a);
        $nb = $this->numbers($b);
        $ka = array_column($na, 'key');
        $kb = array_column($nb, 'key');

        $removed = array_values(array_map(fn ($n) => $n['raw'], array_filter($na, fn ($n) => !in_array($n['key'], $kb, true))));
        $added = array_values(array_map(fn ($n) => $n['raw'], array_filter($nb, fn ($n) => !in_array($n['key'], $ka, true))));

        if (!$na && !$nb) {
            $consistency = 'NOT_APPLICABLE';
        } else {
            $consistency = (!$removed && !$added) ? 'CONSISTENT' : 'INCONSISTENT';
        }

        $da = $this->dates($a);
        $db = $this->dates($b);
        sort($da);
        sort($db);

        $ea = $this->entities($a);
        $eb = $this->entities($b);
        $la = array_map(fn ($e) => mb_strtolower($e, 'UTF-8'), $ea);
        $lb = array_map(fn ($e) => mb_strtolower($e, 'UTF-8'), $eb);

        return [
            'numeric_consistency' => $consistency,
            'numbers_a' => $na,
            'numbers_b' => $nb,
            'numbers_removed' => $removed,
            'numbers_added' => $added,
            'dates_a' => $da,
            'dates_b' => $db,
            'dates_changed' => ($da || $db) && $da !== $db,
            'entities_removed' => array_values(array_filter($ea, fn ($e) => !in_array(mb_strtolower($e, 'UTF-8'), $lb, true))),
            'entities_added' => array_values(array_filter($eb, fn ($e) => !in_array(mb_strtolower($e, 'UTF-8'), $la, true))),
            'entities_shared' => array_values(array_filter($ea, fn ($e) => in_array(mb_strtolower($e, 'UTF-8'), $lb, true))),
            'temporal_a' => $this->temporalCues($a),
            'temporal_b' => $this->temporalCues($b),
        ];
    }
}
