<?php

namespace App\Services\Analysis;

/**
 * Text diff tingkat kata (LCS). Referensi hanya menampilkan SAME / REMOVED,
 * pembanding SAME / ADDED. Pewarnaan UI: hijau = sama, merah = berubah/ditambah,
 * kuning = dihapus / berpotensi kehilangan konteks, abu-abu = belum dapat dibandingkan.
 */
class TextDiff
{
    private const MAX_TOKENS = 2500;

    /**
     * @return array{
     *   comparable:bool, truncated:bool, ref_segments:array<int,array{t:string,s:string}>,
     *   cmp_segments:array<int,array{t:string,s:string}>, blocks:array<int,array{del:string,add:string}>,
     *   same_ratio:float
     * }
     */
    public function diff(string $ref, string $cmp): array
    {
        $a = TextNormalizer::words($ref);
        $b = TextNormalizer::words($cmp);

        if (!$a || !$b) {
            return ['comparable' => false, 'truncated' => false,
                'ref_segments' => array_map(fn ($w) => ['t' => $w, 's' => 'unknown'], $a),
                'cmp_segments' => array_map(fn ($w) => ['t' => $w, 's' => 'unknown'], $b),
                'blocks' => [], 'same_ratio' => 0.0];
        }

        $truncated = false;
        if (count($a) > self::MAX_TOKENS) {
            $a = array_slice($a, 0, self::MAX_TOKENS);
            $truncated = true;
        }
        if (count($b) > self::MAX_TOKENS) {
            $b = array_slice($b, 0, self::MAX_TOKENS);
            $truncated = true;
        }

        $ka = array_map([TextNormalizer::class, 'key'], $a);
        $kb = array_map([TextNormalizer::class, 'key'], $b);
        $n = count($a);
        $m = count($b);

        // LCS (DP)
        $dp = array_fill(0, $n + 1, array_fill(0, $m + 1, 0));
        for ($i = $n - 1; $i >= 0; $i--) {
            for ($j = $m - 1; $j >= 0; $j--) {
                $dp[$i][$j] = ($ka[$i] === $kb[$j])
                    ? $dp[$i + 1][$j + 1] + 1
                    : max($dp[$i + 1][$j], $dp[$i][$j + 1]);
            }
        }

        $ref = [];
        $cmpSeg = [];
        $blocks = [];
        $curDel = [];
        $curAdd = [];
        $same = 0;
        $flush = function () use (&$curDel, &$curAdd, &$blocks) {
            if ($curDel || $curAdd) {
                $blocks[] = ['del' => implode(' ', $curDel), 'add' => implode(' ', $curAdd)];
            }
            $curDel = [];
            $curAdd = [];
        };

        $i = 0;
        $j = 0;
        while ($i < $n && $j < $m) {
            if ($ka[$i] === $kb[$j]) {
                $flush();
                $ref[] = ['t' => $a[$i], 's' => 'same'];
                $cmpSeg[] = ['t' => $b[$j], 's' => 'same'];
                $same++;
                $i++;
                $j++;
            } elseif ($dp[$i + 1][$j] >= $dp[$i][$j + 1]) {
                $ref[] = ['t' => $a[$i], 's' => 'removed'];
                $curDel[] = $a[$i];
                $i++;
            } else {
                $cmpSeg[] = ['t' => $b[$j], 's' => 'added'];
                $curAdd[] = $b[$j];
                $j++;
            }
        }
        while ($i < $n) {
            $ref[] = ['t' => $a[$i], 's' => 'removed'];
            $curDel[] = $a[$i++];
        }
        while ($j < $m) {
            $cmpSeg[] = ['t' => $b[$j], 's' => 'added'];
            $curAdd[] = $b[$j++];
        }
        $flush();

        return [
            'comparable' => true,
            'truncated' => $truncated,
            'ref_segments' => $ref,
            'cmp_segments' => $cmpSeg,
            'blocks' => $blocks,
            'same_ratio' => round($same / max($n, $m) * 100, 1),
        ];
    }
}
