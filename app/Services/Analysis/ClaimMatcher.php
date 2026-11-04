<?php

namespace App\Services\Analysis;

/**
 * Claim-Level Matching (Supplemental §5.2-C): klaim dibandingkan satu per satu
 * pada dimensi status, cakupan, tindakan, angka, dan entitas.
 *
 * Aturan keputusan (transparan, dapat diuji):
 *  - UNKNOWN : keterkaitan topik terlalu rendah (kemiripan kata < 10 dan tanpa entitas bersama).
 *  - CONFLICT: ada >= 2 perbedaan material (status, tindakan, cakupan, angka)
 *              atau tindakan saling berlawanan (TERMINATE vs CONTINUE).
 *  - PARTIAL_MATCH: tepat 1 perbedaan material.
 *  - MATCH   : topik sama tanpa perbedaan material.
 */
class ClaimMatcher
{
    public function __construct(
        private ClaimAnalyzer $claims,
        private LexicalSimilarity $lexical,
        private NumericEntityComparer $numeric,
    ) {
    }

    public function method(): string
    {
        return config('trace.analysis.claim_method');
    }

    public function version(): string
    {
        return config('trace.analysis.claim_version');
    }

    /**
     * @return array{match_status:string,differences:array,lexical:float,shared_entities:string[],claim_a:array,claim_b:array,explanation:string}
     */
    public function compare(string $textA, string $textB): array
    {
        $a = $this->claims->primaryClaim($textA);
        $b = $this->claims->primaryClaim($textB);

        if (!$a || !$b) {
            return ['match_status' => 'UNKNOWN', 'differences' => [], 'lexical' => 0.0, 'shared_entities' => [],
                'claim_a' => $a ?? [], 'claim_b' => $b ?? [], 'explanation' => 'Salah satu materi tidak memiliki teks yang dapat dibandingkan.'];
        }
        return $this->compareClaims($a, $b);
    }

    public function compareClaims(array $a, array $b): array
    {
        $lex = $this->lexical->compare($a['text'], $b['text']);
        $ea = array_map(fn ($e) => mb_strtolower($e, 'UTF-8'), $a['entities']);
        $eb = array_map(fn ($e) => mb_strtolower($e, 'UTF-8'), $b['entities']);
        $shared = array_values(array_intersect($ea, $eb));

        if ($lex['score'] < 10 && !$shared) {
            return ['match_status' => 'UNKNOWN', 'differences' => [], 'lexical' => $lex['score'], 'shared_entities' => [],
                'claim_a' => $a, 'claim_b' => $b,
                'explanation' => 'Keterkaitan topik antar-klaim terlalu rendah untuk dibandingkan.'];
        }

        $diff = [];
        if ($a['status'] !== $b['status'] && $a['status'] !== 'TIDAK_DIKETAHUI' && $b['status'] !== 'TIDAK_DIKETAHUI') {
            $diff['status'] = [Lexicon::statusLabel($a['status']), Lexicon::statusLabel($b['status'])];
        }
        if ($a['scope'] !== $b['scope'] && $a['scope'] !== 'UNSPECIFIED' && $b['scope'] !== 'UNSPECIFIED') {
            $diff['cakupan'] = [Lexicon::scopeLabel($a['scope']), Lexicon::scopeLabel($b['scope'])];
        }
        $actA = $a['actions'];
        $actB = $b['actions'];
        sort($actA);
        sort($actB);
        $opposite = (in_array('TERMINATE', $actA, true) && in_array('CONTINUE', $actB, true))
            || (in_array('TERMINATE', $actB, true) && in_array('CONTINUE', $actA, true));
        if ($actA && $actB && $actA !== $actB) {
            $diff['tindakan'] = [
                implode(', ', array_map([Lexicon::class, 'actionLabel'], $actA)),
                implode(', ', array_map([Lexicon::class, 'actionLabel'], $actB)),
            ];
        }
        $num = $this->numeric->compare($a['text'], $b['text']);
        if ($num['numeric_consistency'] === 'INCONSISTENT' && ($a['numbers'] && $b['numbers'])) {
            $diff['angka'] = [implode(', ', $a['numbers']), implode(', ', $b['numbers'])];
        }

        $material = count($diff);
        if ($opposite || $material >= 2) {
            $status = 'CONFLICT';
        } elseif ($material === 1) {
            $status = 'PARTIAL_MATCH';
        } else {
            $status = 'MATCH';
        }

        $explanation = match ($status) {
            'MATCH' => 'Inti klaim sejalan pada status, cakupan, tindakan, dan angka yang dapat diperiksa.',
            'PARTIAL_MATCH' => 'Topik sama tetapi terdapat satu perbedaan substansi: ' . implode(', ', array_keys($diff)) . '.',
            default => 'Terdapat beberapa perbedaan substansi (' . implode(', ', array_keys($diff) ?: ['tindakan berlawanan']) . '); klaim tidak boleh dianggap setara.',
        };

        return ['match_status' => $status, 'differences' => $diff, 'lexical' => $lex['score'], 'shared_entities' => $shared,
            'claim_a' => $a, 'claim_b' => $b, 'explanation' => $explanation];
    }
}
