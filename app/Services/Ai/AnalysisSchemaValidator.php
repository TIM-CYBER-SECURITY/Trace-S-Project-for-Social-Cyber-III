<?php

namespace App\Services\Ai;

use Illuminate\Support\Facades\Validator;

/**
 * Validator skema JSON terpadu (Master §10) + ekstensi Supplemental §11.
 * Nilai di luar daftar enum ditolak. Temuan yang mengklaim didukung bukti
 * wajib memiliki Evidence ID yang valid.
 */
class AnalysisSchemaValidator
{
    private function in(string $key): string
    {
        return 'in:' . implode(',', array_map(fn ($v) => str_replace(',', '', $v), config("trace.enums.$key")));
    }

    /**
     * @param  string[]  $validEvidenceIds
     * @return string[] daftar kesalahan (kosong = valid)
     */
    public function validateAnalysis(array $d, array $validEvidenceIds): array
    {
        $rules = [
            'module' => ['required', $this->in('module')],
            'summary' => ['required', 'string', 'max:4000'],
            'is_simulation' => ['required', 'boolean'],
            'model_name' => ['nullable', 'string', 'max:200'],
            'model_version' => ['nullable', 'string', 'max:100'],
            'claims' => ['required', 'array', 'max:30'],
            'claims.*.id' => ['required', 'string', 'max:20'],
            'claims.*.text' => ['required', 'string', 'max:2000'],
            'claims.*.type' => ['required', $this->in('claim_type')],
            'claims.*.check_status' => ['required', $this->in('check_status')],
            'verification_status' => ['required', $this->in('check_status')],
            'content_indicator' => ['required', $this->in('content_indicator')],
            'classification' => ['required', $this->in('classification')],
            'credibility_score' => ['required', 'integer', 'between:0,100'],
            'threat_level' => ['required', $this->in('threat_level')],
            'confidence' => ['required', 'numeric', 'between:0,1'],
            'linguistic_analysis' => ['required', 'array'],
            'linguistic_analysis.emotion' => ['required', 'string', 'max:1000'],
            'linguistic_analysis.fallacy' => ['required', 'string', 'max:1000'],
            'linguistic_analysis.source_verification' => ['required', 'string', 'max:1000'],
            'findings' => ['required', 'array', 'max:40'],
            'findings.*.statement' => ['required', 'string', 'max:2000'],
            'findings.*.type' => ['required', $this->in('finding_type')],
            'findings.*.evidence_ids' => ['present', 'array'],
            'findings.*.evidence_ids.*' => ['string', 'max:30'],
            'evidence' => ['present', 'array'],
            'evidence.*.id' => ['required', 'string', 'max:30'],
            'evidence.*.excerpt' => ['required', 'string', 'max:3000'],
            'sources' => ['present', 'array'],
            'sources.*.id' => ['required', 'string', 'max:30'],
            'sources.*.access_status' => ['required', $this->in('access_status')],
            'recommendations' => ['present', 'array'],
            'recommendations.*' => ['string', 'max:1000'],
            'limitations' => ['present', 'array'],
            'limitations.*' => ['string', 'max:1000'],
            'tactical_summary' => ['required', 'string', 'max:1500'],
        ];

        $v = Validator::make($d, $rules);
        $errors = $v->errors()->all();

        // Evidence ID harus valid; temuan faktual wajib punya bukti
        foreach ($d['findings'] ?? [] as $i => $f) {
            $ids = $f['evidence_ids'] ?? [];
            foreach ($ids as $id) {
                if (!in_array($id, $validEvidenceIds, true)) {
                    $errors[] = "findings.$i: Evidence ID '$id' tidak valid pada kasus ini.";
                }
            }
            if (in_array($f['type'] ?? '', ['FAKTA', 'KLAIM_SUMBER'], true) && !$ids) {
                $errors[] = "findings.$i: temuan bertipe {$f['type']} wajib memiliki Evidence ID.";
            }
        }
        foreach ($d['evidence'] ?? [] as $i => $e) {
            if (isset($e['id']) && !in_array($e['id'], $validEvidenceIds, true)) {
                $errors[] = "evidence.$i: Evidence ID '{$e['id']}' tidak valid pada kasus ini.";
            }
        }

        return $errors;
    }

    /** Ekstensi: cross_source_analysis (Supplemental §11). @return string[] */
    public function validateCrossSource(array $d): array
    {
        $v = Validator::make($d, [
            'status' => ['required', $this->in('cross_source_status')],
            'sources_compared' => ['present', 'array'],
            'sources_compared.*.source_id' => ['required', 'string'],
            'sources_compared.*.source_authenticity' => ['required', $this->in('authenticity')],
            'sources_compared.*.relationship' => ['required', $this->in('relationship')],
            'comparisons' => ['present', 'array'],
            'comparisons.*.lexical_similarity' => ['required', 'numeric', 'between:0,100'],
            'comparisons.*.semantic_similarity' => ['nullable', 'numeric', 'between:0,100'],
            'comparisons.*.claim_match_status' => ['required', $this->in('claim_match')],
            'comparisons.*.numeric_consistency' => ['required', $this->in('numeric_consistency')],
            'comparisons.*.context_consistency' => ['required', $this->in('context_consistency')],
            'comparisons.*.evidence_ids' => ['present', 'array'],
            'primary_evidence_ids' => ['present', 'array'],
            'independence_assessment' => ['required', 'string'],
            'limitations' => ['present', 'array'],
        ]);
        return $v->errors()->all();
    }

    /** Ekstensi: authenticity_comparison. @return string[] */
    public function validateAuthenticity(array $d): array
    {
        $v = Validator::make($d, [
            'reference_evidence_id' => ['required', 'string'],
            'compared_evidence_id' => ['required', 'string'],
            'changes' => ['present', 'array'],
            'changes.*.element' => ['required', 'string'],
            'changes.*.change_type' => ['required', $this->in('change_type')],
            'changes.*.meaning_impact' => ['required', 'string'],
            'changes.*.evidence_ids' => ['present', 'array'],
            'assessment' => ['required', 'string'],
        ]);
        return $v->errors()->all();
    }

    /** Ekstensi: narrative_cluster_analysis. @return string[] */
    public function validateCluster(array $d): array
    {
        $v = Validator::make($d, [
            'cluster_id' => ['required', 'string'],
            'main_claim' => ['required', 'string'],
            'variations' => ['present', 'array'],
            'platform_distribution' => ['present', 'array'],
            'earliest_observed_in_dataset' => ['nullable', 'string'],
            'coordination_assessment' => ['required', $this->in('coordination')],
            'limitations' => ['present', 'array'],
        ]);
        return $v->errors()->all();
    }
}
