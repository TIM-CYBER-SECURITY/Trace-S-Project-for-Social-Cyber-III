<?php

namespace App\Services\Analysis;

use App\Models\EvidenceComparison;
use App\Models\EvidenceItem;
use App\Models\SimilarityAnalysis;
use App\Models\User;
use App\Services\Ai\AnalysisSchemaValidator;
use App\Services\Support\AuditLogger;

/**
 * Authenticity Comparator (Supplemental §6): membandingkan materi referensi
 * dengan konten yang dicurigai berubah. Menggunakan istilah
 * "indikasi perubahan materi" sampai bukti forensik memadai tersedia.
 */
class AuthenticityComparator
{
    public function __construct(
        private LexicalSimilarity $lexical,
        private SemanticSimilarity $semantic,
        private ClaimMatcher $claims,
        private NumericEntityComparer $numeric,
        private TextDiff $diff,
        private ChangeImpact $impact,
        private AnalysisSchemaValidator $validator,
        private AuditLogger $audit,
    ) {
    }

    /**
     * @return array<string,mixed>
     */
    public function compare(EvidenceItem $ref, EvidenceItem $cmp, ?User $user = null, bool $persist = true): array
    {
        $a = (string) $ref->extracted_text;
        $b = (string) $cmp->extracted_text;
        $comparable = trim($a) !== '' && trim($b) !== '';
        $isSim = (bool) ($ref->is_simulation || $cmp->is_simulation);
        $limitations = [];

        if (!$comparable) {
            $missing = trim($a) === '' ? $ref->evidence_code : $cmp->evidence_code;
            $limitations[] = "Teks {$missing} tidak tersedia (OCR NOT CONFIGURED / akses gagal); bagian ini ABU-ABU (belum dapat dibandingkan).";
        }

        $lex = $this->lexical->compare($a, $b);
        $sem = $comparable ? $this->semantic->compare($a, $b) : ['available' => false, 'score' => null, 'note' => 'Tidak ada teks.', 'method' => 'Embedding cosine', 'model' => null, 'version' => null, 'is_simulation' => false];
        $claim = $this->claims->compare($a, $b);
        $num = $this->numeric->compare($a, $b);
        $diff = $this->diff->diff($a, $b);
        $changes = $comparable ? $this->impact->explain($a, $b) : [];

        // Konteks
        $notes = [];
        $ctx = 'UNKNOWN';
        $refDate = $ref->published_at;
        $cmpDate = $cmp->published_at;
        if ($refDate && $cmpDate) {
            if ($cmpDate->lt($refDate)) {
                $notes[] = "Konten pembanding tercatat terbit SEBELUM materi referensi ({$cmpDate->toDateTimeString()} vs {$refDate->toDateTimeString()}); tinjau urutan kronologi.";
            } else {
                $notes[] = "Selisih waktu terbit: {$refDate->diffForHumans($cmpDate, true)}.";
            }
        } else {
            $notes[] = 'Waktu terbit salah satu materi tidak tersedia; kronologi belum dapat dinilai.';
        }
        if ($num['dates_changed']) {
            $notes[] = 'Tanggal yang disebut pada teks berbeda.';
        }
        if ($claim['match_status'] === 'CONFLICT' || isset($claim['differences']['status'])) {
            $notes[] = 'Status pernyataan berbeda (wacana vs keputusan) sehingga konteks tidak setara.';
        }
        if ($comparable) {
            if (isset($claim['differences']['status']) || $num['dates_changed']) {
                $ctx = 'INCONSISTENT';
            } elseif ($lex['score'] >= 40 || $claim['shared_entities']) {
                $ctx = 'CONSISTENT';
            }
        }

        $assessment = $this->assessment($comparable, $changes, $ref, $cmp);
        $limitations = array_merge($limitations, [
            'Image comparison / perceptual hashing: NOT AVAILABLE (pipeline belum dibangun).',
            'Metadata yang hilang tidak dianggap sebagai bukti manipulasi.',
            'Perbedaan teks tidak membuktikan manipulasi digital; gunakan istilah "indikasi perubahan materi".',
            'Aturan pemaknaan berbasis kata kunci Indonesia; peninjauan analis diperlukan.',
        ]);
        if (!empty($diff['truncated'])) {
            $limitations[] = 'Teks dipotong pada 2.500 kata pertama untuk perbandingan.';
        }
        if (!$sem['available'] && $sem['note']) {
            $limitations[] = $sem['note'];
        }
        if ($isSim) {
            $limitations[] = 'Sebagian data/hasil berlabel SIMULASI.';
        }

        $contract = [
            'reference_evidence_id' => $ref->evidence_code,
            'compared_evidence_id' => $cmp->evidence_code,
            'changes' => array_map(fn ($c) => $c + ['evidence_ids' => [$ref->evidence_code, $cmp->evidence_code]], $changes),
            'assessment' => $assessment,
        ];
        $errors = $this->validator->validateAuthenticity($contract);

        $result = [
            'comparable' => $comparable,
            'reference' => ['id' => $ref->evidence_code, 'title' => $ref->title, 'publisher' => $ref->publisher, 'published_at' => $ref->published_at?->toIso8601String(), 'hash' => $ref->content_hash, 'text' => $a],
            'compared' => ['id' => $cmp->evidence_code, 'title' => $cmp->title, 'publisher' => $cmp->publisher, 'published_at' => $cmp->published_at?->toIso8601String(), 'hash' => $cmp->content_hash, 'text' => $b],
            'lexical' => $lex,
            'semantic' => $sem,
            'claim' => [
                'match_status' => $claim['match_status'],
                'differences' => $claim['differences'],
                'explanation' => $claim['explanation'],
                'method' => $this->claims->method(), 'version' => $this->claims->version(),
            ],
            'numeric_entity' => $num,
            'context' => ['consistency' => $ctx, 'notes' => $notes],
            'diff' => $diff,
            'changes' => $contract['changes'],
            'assessment' => $assessment,
            'image' => ['status' => 'NOT AVAILABLE', 'note' => 'Perbandingan gambar/OCR belum tersedia pada pipeline ini.'],
            'contract_errors' => $errors,
            'limitations' => $limitations,
            'is_simulation' => $isSim,
            'analyzed_at' => now()->toIso8601String(),
        ];

        if ($persist) {
            $this->persist($ref, $cmp, $result, $user);
        }
        return $result;
    }

    private function assessment(bool $comparable, array $changes, EvidenceItem $ref, EvidenceItem $cmp): string
    {
        if (!$comparable) {
            return 'Materi belum dapat dibandingkan karena salah satu teks tidak tersedia. Tidak ada kesimpulan yang dapat ditarik.';
        }
        if (!$changes) {
            return "Tidak ditemukan perubahan substansi (status, cakupan, tindakan, angka, tanggal, entitas) antara {$ref->evidence_code} dan {$cmp->evidence_code} pada teks yang tersedia. Kesamaan teks tidak otomatis membuktikan kebenaran klaim; periksa sumber primer.";
        }
        $elements = implode(', ', array_unique(array_column($changes, 'element')));
        return 'Ditemukan ' . count($changes) . " indikasi perubahan materi ({$elements}) antara {$ref->evidence_code} (referensi) dan {$cmp->evidence_code} (pembanding). "
            . 'Perubahan redaksi tersebut berpotensi mengubah makna. Materi pembanding tidak dapat dianggap setara dengan materi referensi sebelum klaim diverifikasi melalui sumber primer, '
            . 'dan materi referensi sendiri tidak otomatis dianggap benar.';
    }

    private function persist(EvidenceItem $ref, EvidenceItem $cmp, array $r, ?User $user): void
    {
        $base = ['case_id' => $ref->case_id, 'evidence_a_id' => $ref->id, 'evidence_b_id' => $cmp->id,
            'analyzed_at' => now(), 'is_simulation' => $r['is_simulation']];

        SimilarityAnalysis::where('case_id', $ref->case_id)
            ->where('evidence_a_id', $ref->id)->where('evidence_b_id', $cmp->id)->delete();

        SimilarityAnalysis::create($base + [
            'comparison_type' => 'LEXICAL', 'method' => $r['lexical']['method'], 'model_version' => $r['lexical']['version'],
            'score' => $r['lexical']['comparable'] ? $r['lexical']['score'] : null,
            'result_json' => ['jaccard' => $r['lexical']['jaccard'], 'shared_terms' => $r['lexical']['shared_terms']],
        ]);
        SimilarityAnalysis::create($base + [
            'comparison_type' => 'SEMANTIC', 'method' => $r['semantic']['method'], 'model_name' => $r['semantic']['model'],
            'model_version' => $r['semantic']['version'], 'score' => $r['semantic']['score'],
            'result_json' => ['available' => $r['semantic']['available'], 'note' => $r['semantic']['note'] ?? null],
            'is_simulation' => $r['semantic']['is_simulation'] ?: $r['is_simulation'],
        ]);
        SimilarityAnalysis::create($base + [
            'comparison_type' => 'CLAIM_LEVEL', 'method' => $r['claim']['method'], 'model_version' => $r['claim']['version'],
            'score' => null, 'result_json' => ['match_status' => $r['claim']['match_status'], 'differences' => $r['claim']['differences']],
        ]);
        SimilarityAnalysis::create($base + [
            'comparison_type' => 'NUMERIC_ENTITY', 'method' => 'Regex angka/tanggal/entitas', 'model_version' => '1.0',
            'score' => null, 'result_json' => ['numeric_consistency' => $r['numeric_entity']['numeric_consistency']],
        ]);
        SimilarityAnalysis::create($base + [
            'comparison_type' => 'CONTEXTUAL', 'method' => 'Aturan kronologi + status pernyataan', 'model_version' => '1.0',
            'score' => null, 'result_json' => $r['context'],
        ]);

        $cmpRow = EvidenceComparison::updateOrCreate(
            ['case_id' => $ref->case_id, 'reference_evidence_id' => $ref->id, 'compared_evidence_id' => $cmp->id],
            ['diff_json' => $r, 'assessment' => $r['assessment'], 'is_simulation' => $r['is_simulation'], 'reviewed_by' => null, 'reviewed_at' => null]
        );

        $this->audit->log('COMPARE_EVIDENCE', $cmpRow, [
            'reference' => $ref->evidence_code, 'compared' => $cmp->evidence_code,
            'changes' => count($r['changes']), 'case_id' => $ref->case_id,
        ], $user?->id);
    }
}
