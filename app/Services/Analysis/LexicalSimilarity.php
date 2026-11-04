<?php

namespace App\Services\Analysis;

/**
 * Lexical Similarity (Supplemental §5.2-A): TF-IDF cosine pada unigram + bigram.
 * Nilai 0-100 hanya mengukur KEMIRIPAN KATA, bukan kebenaran informasi.
 */
class LexicalSimilarity
{
    public function method(): string
    {
        return config('trace.analysis.lexical_method');
    }

    public function version(): string
    {
        return config('trace.analysis.lexical_version');
    }

    /** @return string[] */
    public function terms(string $text): array
    {
        $uni = TextNormalizer::tokens($text);
        $terms = $uni;
        for ($i = 0; $i < count($uni) - 1; $i++) {
            $terms[] = $uni[$i] . '_' . $uni[$i + 1];
        }
        return $terms;
    }

    /**
     * Bandingkan dua teks (korpus dua dokumen).
     *
     * @return array{score:float,jaccard:float,shared_terms:string[],comparable:bool,method:string,version:string}
     */
    public function compare(string $a, string $b): array
    {
        $out = ['score' => 0.0, 'jaccard' => 0.0, 'shared_terms' => [], 'comparable' => false,
            'method' => $this->method(), 'version' => $this->version()];

        $ta = $this->terms($a);
        $tb = $this->terms($b);
        if (!$ta || !$tb) {
            return $out;
        }

        $out['comparable'] = true;
        $out['score'] = round($this->cosine($ta, $tb, [$ta, $tb]) * 100, 1);

        $ua = array_unique(TextNormalizer::tokens($a));
        $ub = array_unique(TextNormalizer::tokens($b));
        $inter = array_values(array_intersect($ua, $ub));
        $union = array_unique(array_merge($ua, $ub));
        $out['jaccard'] = $union ? round(count($inter) / count($union) * 100, 1) : 0.0;
        $out['shared_terms'] = array_slice($inter, 0, 12);

        return $out;
    }

    /**
     * Matriks kemiripan antar-dokumen dengan IDF korpus penuh.
     *
     * @param  array<int|string,string>  $texts
     * @return array<int|string,array<int|string,float>>
     */
    public function matrix(array $texts): array
    {
        $termLists = array_map(fn ($t) => $this->terms($t), $texts);
        $corpus = array_values($termLists);
        $keys = array_keys($texts);
        $m = [];
        foreach ($keys as $i) {
            foreach ($keys as $j) {
                if ($i === $j) {
                    $m[$i][$j] = $termLists[$i] ? 100.0 : 0.0;
                    continue;
                }
                if (isset($m[$j][$i])) {
                    $m[$i][$j] = $m[$j][$i];
                    continue;
                }
                $m[$i][$j] = ($termLists[$i] && $termLists[$j])
                    ? round($this->cosine($termLists[$i], $termLists[$j], $corpus) * 100, 1)
                    : 0.0;
            }
        }
        return $m;
    }

    /**
     * @param  string[]  $a
     * @param  string[]  $b
     * @param  array<int,string[]>  $corpus
     */
    private function cosine(array $a, array $b, array $corpus): float
    {
        $n = count($corpus);
        $df = [];
        foreach ($corpus as $doc) {
            foreach (array_unique($doc) as $term) {
                $df[$term] = ($df[$term] ?? 0) + 1;
            }
        }
        $va = $this->vector($a, $df, $n);
        $vb = $this->vector($b, $df, $n);

        $dot = 0.0;
        foreach ($va as $term => $w) {
            if (isset($vb[$term])) {
                $dot += $w * $vb[$term];
            }
        }
        $na = sqrt(array_sum(array_map(fn ($x) => $x * $x, $va)));
        $nb = sqrt(array_sum(array_map(fn ($x) => $x * $x, $vb)));
        return ($na > 0 && $nb > 0) ? $dot / ($na * $nb) : 0.0;
    }

    /** @return array<string,float> */
    private function vector(array $terms, array $df, int $n): array
    {
        $tf = array_count_values($terms);
        $v = [];
        foreach ($tf as $term => $count) {
            $idf = log((1 + $n) / (1 + ($df[$term] ?? 0))) + 1;
            $v[$term] = $count * $idf;
        }
        return $v;
    }
}
