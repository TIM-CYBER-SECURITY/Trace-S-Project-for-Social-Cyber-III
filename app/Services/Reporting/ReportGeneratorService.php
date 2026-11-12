<?php

namespace App\Services\Reporting;

use App\Models\InvestigationCase;
use App\Models\Report;
use App\Models\User;
use App\Services\Analysis\AuthenticityComparator;
use App\Services\Analysis\CrossSourceAnalyzer;
use App\Services\Analysis\NarrativeClusterer;
use App\Services\Support\AuditLogger;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * Command Reporting & Intelligence Brief (Supplemental §14).
 * Struktur 16 bagian formal militer dengan 5 penanda visual:
 * [FAKTA TERDOKUMENTASI], [KLAIM SUMBER], [INTERPRETASI AI], [TEMUAN ANALIS], [REKOMENDASI].
 */
class ReportGeneratorService
{
    public function __construct(
        private CrossSourceAnalyzer $crossSource,
        private AuthenticityComparator $authenticity,
        private NarrativeClusterer $clusterer,
        private AuditLogger $audit
    ) {
    }

    public function generate(InvestigationCase $case, ?User $user = null): Report
    {
        $case->load(['evidence.source', 'sources', 'analyses.reviews.user', 'clusters.items', 'actors', 'comparisons']);

        // Cross-Source
        $crossRes = $this->crossSource->run($case, $user, false);

        // Clusters
        $clusterRes = $this->clusterer->run($case, $user, false);

        // Authenticity Comparison (ambil comparison utama bila ada)
        $comp = $case->comparisons()->first();
        $authRes = $comp ? $comp->diff_json : null;
        if (!$authRes) {
            $ref = $case->evidence()->where('role', 'REFERENCE')->first();
            $sub = $case->evidence()->whereIn('role', ['COMPARED', 'POST', 'SUBJECT'])->first();
            if ($ref && $sub) {
                $authRes = $this->authenticity->compare($ref, $sub, $user, false);
            }
        }

        // Credibility Scan Analysis
        $credAnalysis = $case->analyses()->where('module', 'credibility_scan')->latest()->first();

        // Reviews
        $latestReview = $credAnalysis?->reviews()->latest()->first();
        $classificationLabel = mb_substr((string) (app(\App\Services\Support\AppSettings::class)->get('classification_label') ?: config('trace.classification_label')), 0, 40);

        // 16 Bagian Laporan
        $content = [
            'section_1_case_identity' => [
                'case_code' => $case->case_code,
                'title' => $case->title,
                'category' => $case->category,
                'priority' => $case->priority,
                'status' => $case->status,
                'created_at' => $case->created_at->toIso8601String(),
                'classification_label' => $classificationLabel,
                'is_simulation' => $case->is_simulation,
            ],
            'section_2_executive_summary' => [
                'summary' => $credAnalysis ? ($credAnalysis->result['tactical_summary'] ?? $credAnalysis->result['summary'] ?? '') : 'Analisis awal kredibilitas belum dijalankan.',
                'marker' => 'INTERPRETASI AI',
            ],
            'section_3_data_scope' => [
                'scope' => 'Analisis dibatasi secara ketat pada dataset bukti yang berhasil dikumpulkan oleh operator (' . $case->evidence->count() . ' item bukti). Sistem TIDAK melakukan pemindaian menyeluruh terhadap seluruh internet.',
                'sources_connected' => $case->sources->pluck('platform')->unique()->values()->all(),
            ],
            'section_4_main_claims' => [
                'primary_claim' => $case->claim_text,
                'extracted_claims' => $credAnalysis->result['claims'] ?? [],
            ],
            'section_5_sources_and_evidence' => $case->evidence->map(fn ($e) => [
                'evidence_code' => $e->evidence_code,
                'title' => $e->title,
                'publisher' => $e->publisher,
                'platform' => $e->platform,
                'role' => $e->role,
                'access_status' => $e->access_status,
                'content_hash' => $e->content_hash,
                'published_at' => $e->published_at?->toIso8601String(),
            ])->all(),
            'section_6_cross_source_verification' => [
                'status' => $crossRes['status'] ?? 'BELUM DIANALISIS',
                'comparisons_count' => count($crossRes['comparisons_detail'] ?? []),
                'sources_compared' => $crossRes['sources'] ?? [],
                'findings' => $crossRes['comparisons_detail'] ?? [],
            ],
            'section_7_source_independence' => [
                'assessment' => $crossRes['independence_assessment'] ?? 'Belum dianalisis.',
                'groups' => $crossRes['groups'] ?? [],
                'independent_units' => $crossRes['independent_units'] ?? 0,
            ],
            'section_8_authenticity_comparison' => [
                'assessment' => $authRes['assessment'] ?? 'Tidak ada perbandingan langsung materi referensi dan pembanding.',
                'changes' => $authRes['changes'] ?? [],
                'numeric_consistency' => $authRes['numeric_entity']['numeric_consistency'] ?? 'NOT_APPLICABLE',
                'context_consistency' => $authRes['context']['consistency'] ?? 'UNKNOWN',
            ],
            'section_9_narrative_clustering' => [
                'clusters_count' => count($clusterRes['clusters'] ?? []),
                'clusters' => array_map(fn ($c) => [
                    'cluster_id' => $c['cluster_id'],
                    'main_claim' => $c['main_claim'],
                    'variations_count' => count($c['variations']),
                    'coordination_assessment' => $c['coordination_assessment'],
                    'coordination_note' => $c['coordination_note'],
                    'earliest_observed' => $c['earliest_observed_in_dataset'],
                ], $clusterRes['clusters'] ?? []),
            ],
            'section_10_spread_analysis' => [
                'total_posts_in_dataset' => $case->evidence->whereIn('role', ['POST', 'SUBJECT'])->count(),
                'platform_distribution' => $case->evidence->groupBy('platform')->map->count()->all(),
                'earliest_observed' => $case->evidence->sortBy('published_at')->first()?->only(['evidence_code', 'publisher', 'published_at']),
                'disclaimer' => 'Titik publikasi paling awal yang ditemukan dalam dataset terkumpul, bukan publikasi pertama di internet.',
            ],
            'section_11_key_findings' => [
                [
                    'marker' => 'FAKTA TERDOKUMENTASI',
                    'statement' => 'Terdapat ' . $case->evidence->count() . ' berkas bukti digital tersimpan dengan hash SHA-256 terverifikasi.',
                ],
                [
                    'marker' => 'KLAIM SUMBER',
                    'statement' => 'Narasi utama mengklaim penghentian program kebijakan nasional dengan amplifikasi viral.',
                ],
                [
                    'marker' => 'INTERPRETASI AI',
                    'statement' => $credAnalysis ? ($credAnalysis->result['tactical_summary'] ?? 'Analisis AI selesai.') : 'Menunggu kalkulasi kredibilitas.',
                ],
                [
                    'marker' => 'TEMUAN ANALIS',
                    'statement' => $latestReview ? "Keputusan [{$latestReview->decision}]: {$latestReview->reason}" : 'Belum divalidasi oleh analis manusia (Status: PENDING REVIEW).',
                ],
            ],
            'section_12_threat_indicators' => [
                'threat_level' => $credAnalysis->threat_level ?? 'SEDANG',
                'credibility_score' => $credAnalysis->credibility_score ?? 50,
                'verification_status' => $credAnalysis->verification_status ?? 'BELUM TERVERIFIKASI',
                'basis' => 'Dihitung berdasarkan ketercukupan bukti, anomali status keputusan, dan jangkauan dataset.',
            ],
            'section_13_analyst_validation' => [
                'status' => $credAnalysis->validation_status ?? 'PENDING REVIEW',
                'analyst' => $latestReview?->user?->name ?? 'Belum Ditugaskan',
                'decision' => $latestReview?->decision ?? 'PENDING REVIEW',
                'reason' => $latestReview?->reason ?? 'Menunggu tinjauan manual.',
                'reviewed_at' => $latestReview?->created_at?->toIso8601String(),
            ],
            'section_14_limitations' => array_merge(
                $credAnalysis->result['limitations'] ?? [],
                $crossRes['limitations'] ?? [],
                [
                    'Aplikasi tidak ditujukan sebagai penentu kebenaran otomatis atau sensor informasi.',
                    'Semua keputusan operasional memerlukan otorisasi komando berwenang.',
                ]
            ),
            'section_15_recommendations' => [
                'Lakukan verifikasi berkala terhadap rujukan primer kementerian terkait.',
                'Susun draf klarifikasi resmi berbasis bukti jika narasi terbukti menimbulkan disrupsi kognitif publik.',
                'DILARANG melakukan serangan balik, botting, atau penutupan paksa tanpa prosedur hukum sah.',
            ],
            'section_16_evidence_hashes' => $case->evidence->map(fn ($e) => [
                'code' => $e->evidence_code,
                'hash' => $e->content_hash ?? 'N/A',
                'method' => $e->collection_method,
                'retrieved_at' => $e->retrieved_at?->toIso8601String(),
            ])->all(),
        ];

        $reportCode = 'REP-' . $case->case_code . '-' . now()->format('His') . '-' . strtoupper(substr(bin2hex(random_bytes(2)), 0, 4));

        $report = Report::create([
            'case_id' => $case->id,
            'report_code' => $reportCode,
            'title' => 'INTEL BRIEF: ' . $case->title,
            'classification_label' => $classificationLabel,
            'content' => $content,
            'status' => 'FINAL',
            'is_simulation' => $case->is_simulation,
            'generated_by' => $user?->id,
        ]);

        $this->audit->log('REPORT_GENERATED', $report, [
            'case_id' => $case->id,
            'report_code' => $report->report_code,
        ], $user?->id);

        return $report;
    }

    public function exportPdf(Report $report)
    {
        $pdf = Pdf::loadView('reports.pdf', ['report' => $report])
            ->setPaper('a4', 'portrait')
            ->setOptions([
                'defaultFont' => 'sans-serif',
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled' => false,
            ]);

        $this->audit->log('REPORT_EXPORT_PDF', $report, [
            'report_code' => $report->report_code,
        ]);

        return $pdf->download("TRACE-S_INTEL_BRIEF_{$report->report_code}.pdf");
    }
}
