<?php

namespace App\Services\Analysis;

use App\Models\ClaimComparison;
use App\Models\EvidenceItem;
use App\Models\InvestigationCase;
use App\Models\SimilarityAnalysis;
use App\Models\SourceRelationship;
use App\Models\User;
use App\Services\Ai\AnalysisSchemaValidator;
use App\Services\Support\AuditLogger;

/**
 * Cross-Source Verification & Similarity Engine (Supplemental §5).
 * Status akhir adalah PENILAIAN DUKUNGAN INFORMASI, bukan label kebenaran.
 */
class CrossSourceAnalyzer
{
    public function __construct(
        private LexicalSimilarity $lexical,
        private SemanticSimilarity $semantic,
        private ClaimMatcher $claims,
        private NumericEntityComparer $numeric,
        private SourceRelationshipAnalyzer $relationships,
        private AnalysisSchemaValidator $validator,
        private AuditLogger $audit,
    ) {
    }

    /** @return array<string,mixed> */
    public function run(InvestigationCase $case, ?User $user = null, bool $persist = true): array
    {
        $all = $case->evidence()->with('source')->whereIn('role', ['REFERENCE', 'PRIMARY'])->orderBy('id')->get();
        $items = $all->filter(fn ($e) => $e->access_status === 'OK' && trim((string) $e->extracted_text) !== '')->values();
        $unavailable = $all->reject(fn ($e) => $items->contains('id', $e->id));
        $limitations = [];
        $isSim = $all->contains(fn ($e) => $e->is_simulation);

        foreach ($unavailable as $u) {
            $limitations[] = "{$u->evidence_code} ({$u->publisher}) tidak dapat dibandingkan: status akses {$u->access_status} / teks tidak tersedia. Isi tidak diasumsikan.";
        }

        $rel = $this->relationships->analyze($case, $items);

        // Semantic: satu panggilan embedding untuk seluruh teks
        $texts = [];
        foreach ($items as $it) {
            $texts[$it->id] = (string) $it->extracted_text;
        }
        $sem = count($texts) >= 2 ? $this->semantic->matrix($texts) : ['available' => false, 'matrix' => [], 'meta' => ['method' => 'Embedding cosine', 'model' => null, 'version' => null, 'is_simulation' => false], 'note' => 'Kurang dari dua sumber.'];
        if (!$sem['available'] && $sem['note']) {
            $limitations[] = $sem['note'];
        }
        if ($sem['meta']['is_simulation'] ?? false) {
            $isSim = true;
            $limitations[] = 'Semantic similarity berasal dari MockDriver (SIMULASI).';
        }
        $lexMatrix = $this->lexical->matrix($texts);

        $comparisons = [];
        $matchCount = 0;
        $conflict = false;
        $relBy = [];
        foreach ($rel['relationships'] as $r) {
            $relBy[$r['evidence_a'] . '|' . $r['evidence_b']] = $r;
        }

        for ($i = 0; $i < $items->count(); $i++) {
            for ($j = $i + 1; $j < $items->count(); $j++) {
                $a = $items[$i];
                $b = $items[$j];
                $claim = $this->claims->compare($a->extracted_text, $b->extracted_text);
                $num = $this->numeric->compare($a->extracted_text, $b->extracted_text);
                $ctx = 'UNKNOWN';
                if (isset($claim['differences']['status']) || $num['dates_changed']) {
                    $ctx = 'INCONSISTENT';
                } elseif ($claim['match_status'] !== 'UNKNOWN') {
                    $ctx = 'CONSISTENT';
                }
                if ($claim['match_status'] === 'CONFLICT' || $num['numeric_consistency'] === 'INCONSISTENT') {
                    $conflict = true;
                }
                if (in_array($claim['match_status'], ['MATCH', 'PARTIAL_MATCH'], true)) {
                    $matchCount++;
                }
                $comparisons[] = [
                    'source_a_id' => $a->source?->source_code, 'source_b_id' => $b->source?->source_code,
                    'evidence_a' => $a->evidence_code, 'evidence_b' => $b->evidence_code,
                    'publisher_a' => $a->publisher, 'publisher_b' => $b->publisher,
                    'published_a' => $a->published_at?->toDateString(), 'published_b' => $b->published_at?->toDateString(),
                    'lexical_similarity' => $lexMatrix[$a->id][$b->id],
                    'semantic_similarity' => $sem['available'] ? $sem['matrix'][$a->id][$b->id] : null,
                    'claim_match_status' => $claim['match_status'],
                    'claim_differences' => $claim['differences'],
                    'claim_a' => $claim['claim_a']['text'] ?? '', 'claim_b' => $claim['claim_b']['text'] ?? '',
                    'numeric_consistency' => $num['numeric_consistency'],
                    'context_consistency' => $ctx,
                    'relationship' => $relBy[$a->evidence_code . '|' . $b->evidence_code]['type'] ?? 'RELATIONSHIP_UNKNOWN',
                    'explanation' => $claim['explanation'],
                    'evidence_ids' => [$a->evidence_code, $b->evidence_code],
                    '_ids' => [$a->id, $b->id, $a->source_id, $b->source_id],
                ];
            }
        }

        $primary = $items->where('role', 'PRIMARY')->pluck('evidence_code')->values()->all();
        $status = $this->status($items->count(), $conflict, $primary, $items, $comparisons, $rel, $matchCount);

        $sourcesCompared = $items->map(fn (EvidenceItem $e) => [
            'source_id' => $e->source?->source_code ?? $e->evidence_code,
            'evidence_id' => $e->evidence_code,
            'publisher' => $e->publisher,
            'url' => $e->url,
            'platform' => $e->platform,
            'published_at' => $e->published_at?->toIso8601String(),
            'role' => $e->role,
            'source_authenticity' => $e->source?->authenticity ?? 'UNKNOWN',
            'relationship' => $this->bestRelationship($e->evidence_code, $rel['relationships']),
        ])->values()->all();

        $limitations = array_merge($limitations, [
            'Nama organisasi tidak otomatis membuktikan keaslian sumber; keaslian sumber dinilai terpisah dari kebenaran klaim.',
            'Nilai similarity adalah ukuran kemiripan menurut metode tercantum, bukan tingkat kebenaran berita.',
            'Hubungan antar-sumber hanya dideteksi dari kutipan/kemiripan pada teks yang tersedia.',
        ]);
        if ($items->count() < 2) {
            $limitations[] = 'Minimal dua sumber dengan teks yang berhasil diperoleh diperlukan untuk perbandingan.';
        }

        $contract = [
            'status' => $status,
            'sources_compared' => array_map(fn ($s) => ['source_id' => $s['source_id'], 'publisher' => $s['publisher'],
                'source_authenticity' => $s['source_authenticity'], 'relationship' => $s['relationship']], $sourcesCompared),
            'comparisons' => array_map(fn ($c) => [
                'source_a_id' => $c['source_a_id'], 'source_b_id' => $c['source_b_id'],
                'lexical_similarity' => $c['lexical_similarity'], 'semantic_similarity' => $c['semantic_similarity'],
                'claim_match_status' => $c['claim_match_status'], 'numeric_consistency' => $c['numeric_consistency'],
                'context_consistency' => $c['context_consistency'], 'evidence_ids' => $c['evidence_ids'],
            ], $comparisons),
            'primary_evidence_ids' => $primary,
            'independence_assessment' => $rel['statement'],
            'limitations' => $limitations,
        ];
        $errors = $this->validator->validateCrossSource($contract);

        $result = $contract + [
            'sources' => $sourcesCompared,
            'comparisons_detail' => array_map(function ($c) { unset($c['_ids']); return $c; }, $comparisons),
            'relationships' => $rel['relationships'],
            'groups' => $rel['groups'],
            'independent_units' => $rel['independent_units'],
            'confirmed_independent_pairs' => $rel['confirmed_independent_pairs'],
            'unavailable' => $unavailable->map(fn ($u) => ['evidence_id' => $u->evidence_code, 'publisher' => $u->publisher, 'access_status' => $u->access_status])->values()->all(),
            'methods' => [
                'lexical' => $this->lexical->method() . ' v' . $this->lexical->version(),
                'semantic' => $sem['meta']['method'] . ($sem['meta']['model'] ? ' / ' . $sem['meta']['model'] : ''),
                'claim' => $this->claims->method() . ' v' . $this->claims->version(),
            ],
            'contract_errors' => $errors,
            'is_simulation' => $isSim,
            'analyzed_at' => now()->toIso8601String(),
        ];

        if ($persist) {
            $this->persist($case, $items, $comparisons, $rel, $sem, $isSim, $user);
        }
        return $result;
    }

    private function bestRelationship(string $code, array $rels): string
    {
        $order = ['SYNDICATED_REPUBLISHED', 'SECONDARY_CITATION', 'COMMON_PRIMARY_SOURCE', 'INDEPENDENT_REPORTING', 'RELATIONSHIP_UNKNOWN'];
        $best = 'RELATIONSHIP_UNKNOWN';
        $bestIdx = 99;
        foreach ($rels as $r) {
            if ($r['evidence_a'] === $code || $r['evidence_b'] === $code) {
                $idx = array_search($r['type'], $order, true);
                if ($idx !== false && $idx < $bestIdx) {
                    $bestIdx = $idx;
                    $best = $r['type'];
                }
            }
        }
        return $best;
    }

    /**
     * Urutan prioritas: INSUFFICIENT -> CONFLICTING -> CONSISTENT_WITH_PRIMARY ->
     * STRONG (>=2 kelompok rujukan terpisah & >=3 sumber & semua cocok) ->
     * UNVERIFIED_SOURCE_RELATIONSHIP (ada kecocokan tetapi hubungan semua tak diketahui) -> PARTIAL.
     */
    private function status(int $n, bool $conflict, array $primary, $items, array $comparisons, array $rel, int $matchCount): string
    {
        if ($n < 2) {
            return 'INSUFFICIENT_SOURCE_EVIDENCE';
        }
        if ($conflict) {
            return 'CONFLICTING_SOURCES';
        }
        if ($primary) {
            $primaryCodes = $primary;
            $againstPrimary = array_filter($comparisons, fn ($c) => in_array($c['evidence_a'], $primaryCodes, true) || in_array($c['evidence_b'], $primaryCodes, true));
            if ($againstPrimary && count(array_filter($againstPrimary, fn ($c) => in_array($c['claim_match_status'], ['MATCH', 'PARTIAL_MATCH'], true))) === count($againstPrimary)) {
                return 'CONSISTENT_WITH_PRIMARY_EVIDENCE';
            }
        }
        $allMatch = $comparisons && count(array_filter($comparisons, fn ($c) => $c['claim_match_status'] === 'MATCH')) === count($comparisons);
        if ($allMatch && $n >= 3 && $rel['independent_units'] >= 2 && $rel['confirmed_independent_pairs'] >= 1) {
            return 'STRONG_CROSS_SOURCE_SUPPORT';
        }
        $allUnknown = $rel['relationships'] && count(array_filter($rel['relationships'], fn ($r) => $r['type'] === 'RELATIONSHIP_UNKNOWN')) === count($rel['relationships']);
        if ($matchCount > 0 && $allUnknown) {
            return 'UNVERIFIED_SOURCE_RELATIONSHIP';
        }
        return $matchCount > 0 ? 'PARTIAL_CROSS_SOURCE_SUPPORT' : 'INSUFFICIENT_SOURCE_EVIDENCE';
    }

    private function persist(InvestigationCase $case, $items, array $comparisons, array $rel, array $sem, bool $isSim, ?User $user): void
    {
        $ids = $items->pluck('id')->all();
        SimilarityAnalysis::where('case_id', $case->id)->whereIn('evidence_a_id', $ids)->whereIn('evidence_b_id', $ids)
            ->whereIn('comparison_type', ['LEXICAL', 'SEMANTIC'])->delete();
        ClaimComparison::where('case_id', $case->id)->delete();
        SourceRelationship::where('case_id', $case->id)->whereNull('reviewed_by')->delete();

        foreach ($comparisons as $c) {
            [$ea, $eb, $sa, $sb] = $c['_ids'];
            SimilarityAnalysis::create([
                'case_id' => $case->id, 'evidence_a_id' => $ea, 'evidence_b_id' => $eb, 'comparison_type' => 'LEXICAL',
                'method' => $this->lexical->method(), 'model_version' => $this->lexical->version(),
                'score' => $c['lexical_similarity'], 'analyzed_at' => now(), 'is_simulation' => false,
            ]);
            if ($c['semantic_similarity'] !== null) {
                SimilarityAnalysis::create([
                    'case_id' => $case->id, 'evidence_a_id' => $ea, 'evidence_b_id' => $eb, 'comparison_type' => 'SEMANTIC',
                    'method' => $sem['meta']['method'], 'model_name' => $sem['meta']['model'], 'model_version' => $sem['meta']['version'],
                    'score' => $c['semantic_similarity'], 'analyzed_at' => now(), 'is_simulation' => $sem['meta']['is_simulation'],
                ]);
            }
            if ($sa && $sb) {
                ClaimComparison::create([
                    'case_id' => $case->id, 'source_a_id' => $sa, 'source_b_id' => $sb,
                    'claim_a' => $c['claim_a'], 'claim_b' => $c['claim_b'], 'match_status' => $c['claim_match_status'],
                    'similarity_method' => $this->claims->method() . ' v' . $this->claims->version(),
                    'similarity_score' => $c['lexical_similarity'],
                    'result_json' => ['differences' => $c['claim_differences'], 'numeric' => $c['numeric_consistency'], 'context' => $c['context_consistency']],
                ]);
            }
        }
        foreach ($rel['relationships'] as $r) {
            if ($r['reviewed'] || !$r['source_a_id'] || !$r['source_b_id']) {
                continue;
            }
            SourceRelationship::create([
                'case_id' => $case->id, 'source_a_id' => $r['source_a_id'], 'source_b_id' => $r['source_b_id'],
                'relationship_type' => $r['type'], 'assessment' => $r['basis'],
            ]);
        }
        $this->audit->log('CROSS_SOURCE_RUN', $case, ['sources' => $items->count(), 'comparisons' => count($comparisons)], $user?->id);
    }
}
