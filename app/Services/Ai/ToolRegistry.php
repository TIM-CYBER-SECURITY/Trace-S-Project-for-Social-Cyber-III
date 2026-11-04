<?php

namespace App\Services\Ai;

use App\Models\InvestigationCase;
use App\Services\Analysis\AuthenticityComparator;
use App\Services\Analysis\ClaimAnalyzer;
use App\Services\Analysis\ClaimMatcher;
use App\Services\Analysis\CrossSourceAnalyzer;
use App\Services\Analysis\LexicalSimilarity;
use App\Services\Analysis\NarrativeClusterer;
use App\Services\Analysis\NumericEntityComparer;
use App\Services\Analysis\SemanticSimilarity;
use InvalidArgumentException;

/**
 * Tool Allowlist & Executor (Supplemental §10.1).
 * Pola terisolasi: Operator -> Backend -> RBAC -> AI Job -> Tool Allowlist -> Database.
 * AI HANYA boleh mengeksekusi tool dari allowlist resmi ini.
 * DILARANG shell, raw SQL, filesystem sembarangan, atau jaringan arbitrer.
 */
class ToolRegistry
{
    public const ALLOWLIST = [
        'extract_claims',
        'calculate_text_similarity',
        'calculate_semantic_similarity',
        'compare_claims',
        'compare_numeric_entities',
        'analyze_source_relationships',
        'compare_evidence',
        'cluster_narratives',
        'analyze_spread',
        'generate_case_summary',
    ];

    public function __construct(
        private ClaimAnalyzer $claims,
        private LexicalSimilarity $lexical,
        private SemanticSimilarity $semantic,
        private ClaimMatcher $matcher,
        private NumericEntityComparer $numeric,
        private CrossSourceAnalyzer $crossSource,
        private AuthenticityComparator $authenticity,
        private NarrativeClusterer $clusterer,
    ) {
    }

    public function isAllowed(string $toolName): bool
    {
        return in_array($toolName, self::ALLOWLIST, true);
    }

    public function execute(string $toolName, array $params): array
    {
        if (!$this->isAllowed($toolName)) {
            throw new InvalidArgumentException("Tool [{$toolName}] tidak diizinkan dalam AI Tool Allowlist Trace-S.");
        }

        return match ($toolName) {
            'extract_claims' => $this->toolExtractClaims($params),
            'calculate_text_similarity' => $this->toolCalculateTextSimilarity($params),
            'calculate_semantic_similarity' => $this->toolCalculateSemanticSimilarity($params),
            'compare_claims' => $this->toolCompareClaims($params),
            'compare_numeric_entities' => $this->toolCompareNumericEntities($params),
            'analyze_source_relationships' => $this->toolAnalyzeSourceRelationships($params),
            'compare_evidence' => $this->toolCompareEvidence($params),
            'cluster_narratives' => $this->toolClusterNarratives($params),
            'analyze_spread' => $this->toolAnalyzeSpread($params),
            'generate_case_summary' => $this->toolGenerateCaseSummary($params),
        };
    }

    private function toolExtractClaims(array $params): array
    {
        $text = (string) ($params['text'] ?? '');
        $max = (int) ($params['max'] ?? 8);
        return [
            'tool' => 'extract_claims',
            'claims' => $this->claims->extract($text, $max),
        ];
    }

    private function toolCalculateTextSimilarity(array $params): array
    {
        $textA = (string) ($params['text_a'] ?? '');
        $textB = (string) ($params['text_b'] ?? '');
        return [
            'tool' => 'calculate_text_similarity',
            'result' => $this->lexical->compare($textA, $textB),
        ];
    }

    private function toolCalculateSemanticSimilarity(array $params): array
    {
        $textA = (string) ($params['text_a'] ?? '');
        $textB = (string) ($params['text_b'] ?? '');
        return [
            'tool' => 'calculate_semantic_similarity',
            'result' => $this->semantic->compare($textA, $textB),
        ];
    }

    private function toolCompareClaims(array $params): array
    {
        $claimA = (string) ($params['claim_a'] ?? '');
        $claimB = (string) ($params['claim_b'] ?? '');
        return [
            'tool' => 'compare_claims',
            'result' => $this->matcher->compare($claimA, $claimB),
        ];
    }

    private function toolCompareNumericEntities(array $params): array
    {
        $textA = (string) ($params['text_a'] ?? '');
        $textB = (string) ($params['text_b'] ?? '');
        return [
            'tool' => 'compare_numeric_entities',
            'result' => $this->numeric->compare($textA, $textB),
        ];
    }

    private function toolAnalyzeSourceRelationships(array $params): array
    {
        $caseId = $params['case_id'] ?? null;
        if (!$caseId) {
            throw new InvalidArgumentException("case_id wajib untuk tool analyze_source_relationships.");
        }
        $case = InvestigationCase::findOrFail($caseId);
        $res = $this->crossSource->run($case, null, false);
        return [
            'tool' => 'analyze_source_relationships',
            'status' => $res['status'],
            'sources' => $res['sources'],
            'relationships' => $res['relationships'],
            'statement' => $res['independence_assessment'],
        ];
    }

    private function toolCompareEvidence(array $params): array
    {
        $refId = $params['reference_id'] ?? null;
        $cmpId = $params['compared_id'] ?? null;
        if (!$refId || !$cmpId) {
            throw new InvalidArgumentException("reference_id dan compared_id wajib.");
        }
        $ref = \App\Models\EvidenceItem::findOrFail($refId);
        $cmp = \App\Models\EvidenceItem::findOrFail($cmpId);
        return [
            'tool' => 'compare_evidence',
            'result' => $this->authenticity->compare($ref, $cmp, null, false),
        ];
    }

    private function toolClusterNarratives(array $params): array
    {
        $caseId = $params['case_id'] ?? null;
        if (!$caseId) {
            throw new InvalidArgumentException("case_id wajib untuk cluster_narratives.");
        }
        $case = InvestigationCase::findOrFail($caseId);
        return [
            'tool' => 'cluster_narratives',
            'result' => $this->clusterer->run($case, null, false),
        ];
    }

    private function toolAnalyzeSpread(array $params): array
    {
        $caseId = $params['case_id'] ?? null;
        if (!$caseId) {
            throw new InvalidArgumentException("case_id wajib untuk analyze_spread.");
        }
        $case = InvestigationCase::findOrFail($caseId);
        $actors = $case->actors()->get();
        $posts = $case->evidence()->whereIn('role', ['POST', 'SUBJECT'])->get();
        return [
            'tool' => 'analyze_spread',
            'dataset_scope' => 'Hanya mencakup dataset yang berhasil dikumpulkan (Bukan seluruh internet)',
            'total_posts' => $posts->count(),
            'actors_observed' => $actors->count(),
            'platforms' => $posts->groupBy('platform')->map->count(),
            'earliest_post' => $posts->sortBy('published_at')->first()?->only(['evidence_code', 'published_at', 'publisher', 'platform']),
        ];
    }

    private function toolGenerateCaseSummary(array $params): array
    {
        $caseId = $params['case_id'] ?? null;
        if (!$caseId) {
            throw new InvalidArgumentException("case_id wajib untuk generate_case_summary.");
        }
        $case = InvestigationCase::with(['evidence', 'sources', 'clusters', 'actors'])->findOrFail($caseId);
        return [
            'tool' => 'generate_case_summary',
            'case_code' => $case->case_code,
            'title' => $case->title,
            'claim_text' => $case->claim_text,
            'evidence_count' => $case->evidence->count(),
            'source_count' => $case->sources->count(),
            'cluster_count' => $case->clusters->count(),
            'actor_count' => $case->actors->count(),
            'priority' => $case->priority,
            'status' => $case->status,
        ];
    }
}
