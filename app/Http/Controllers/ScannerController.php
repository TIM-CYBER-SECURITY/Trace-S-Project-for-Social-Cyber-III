<?php

namespace App\Http\Controllers;

use App\Models\AiModel;
use App\Models\AnalystReview;
use App\Models\EvidenceItem;
use App\Models\FeedItem;
use App\Models\InformationRecord;
use App\Models\InvestigationCase;
use App\Models\Narrative;
use App\Models\ThreatAnalysis;
use App\Services\Ai\AiManager;
use App\Services\Ai\Exceptions\AiUnavailableException;
use App\Services\Ai\HardwareTelemetry;
use App\Services\Analysis\AuthenticityComparator;
use App\Services\Analysis\CrossSourceAnalyzer;
use App\Services\Analysis\LexicalSimilarity;
use App\Services\Analysis\ManipulationDetector;
use App\Services\Analysis\NumericEntityComparer;
use App\Services\Analysis\SourceRelationshipAnalyzer;
use App\Services\Analysis\TextNormalizer;
use App\Services\DataAcquisition\EvidenceCollectorService;
use App\Services\Reporting\ReportGeneratorService;
use App\Services\Support\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class ScannerController extends Controller
{
    public function __construct(
        private AiManager $ai,
        private AuthenticityComparator $authenticity,
        private CrossSourceAnalyzer $crossSource,
        private AuditLogger $audit,
        private EvidenceCollectorService $collector,
        private LexicalSimilarity $lexical,
        private ManipulationDetector $manipulation,
        private NumericEntityComparer $numericComparer,
        private SourceRelationshipAnalyzer $sourceRelations,
        private ReportGeneratorService $reports,
        private HardwareTelemetry $telemetry
    ) {
    }

    public function index(Request $request)
    {
        $cases = InvestigationCase::with('evidence')->latest()->get();
        $selectedCase = null;
        $caseId = $request->query('case_id');

        if ($caseId && $caseId !== 'auto') {
            $selectedCase = InvestigationCase::with(['evidence.source', 'sources', 'analyses.reviews.user', 'reports'])->find($caseId);
        } elseif ($cases->isNotEmpty() && $caseId !== 'auto') {
            $selectedCase = $cases->first();
        }

        $latestAnalysis = $selectedCase?->analyses()->where('module', 'credibility_scan')->latest()->first();
        $latestComparison = $selectedCase?->comparisons()->latest()->first();
        $latestReport = $selectedCase?->reports()->latest()->first();

        $highlightedHtml = null;
        if ($latestAnalysis) {
            $highlights = $latestAnalysis->result['manipulation_highlights'] ?? [];
            if ($highlights === [] && $selectedCase) {
                $refItems = $selectedCase->evidence
                    ->whereIn('role', ['REFERENCE', 'PRIMARY'])
                    ->map(fn ($e) => ['code' => $e->evidence_code, 'text' => (string) $e->extracted_text, 'is_reference' => true])
                    ->values()
                    ->all();
                $highlights = $this->manipulation->detect(
                    (string) $latestAnalysis->source_text,
                    $refItems,
                    $latestAnalysis->result['claims'] ?? []
                );
            }
            $highlightedHtml = $this->manipulation->renderHighlightedHtml((string) $latestAnalysis->source_text, $highlights);
        }

        // Cross-source pre-run if sources exist
        $crossResult = null;
        if ($selectedCase && $selectedCase->evidence->whereIn('role', ['REFERENCE', 'PRIMARY'])->count() >= 1) {
            $crossResult = $this->crossSource->run($selectedCase, Auth::user(), false);
        }

        $ownedModels = AiModel::with('server')
            ->where('is_simulation', false)
            ->where('is_embedding', false)
            ->whereHas('server', fn ($q) => $q->where('driver', '!=', 'mock'))
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get();

        $aiStatus = $this->ai->status();
        $hardware = $this->telemetry->snapshot();

        return view('scanner.index', compact(
            'cases',
            'selectedCase',
            'latestAnalysis',
            'latestComparison',
            'latestReport',
            'crossResult',
            'highlightedHtml',
            'ownedModels',
            'aiStatus',
            'hardware'
        ));
    }

    /**
     * Aktifkan model AI lokal milik pengguna langsung dari Dashboard / Scanner.
     */
    public function selectModel(Request $request)
    {
        $validated = $request->validate([
            'model_id' => 'required|exists:ai_models,id',
        ]);

        $model = AiModel::with('server')->findOrFail($validated['model_id']);

        try {
            $res = $this->ai->activateModel($model, Auth::user(), $this->audit, 'Aktivasi model AI lokal oleh pengguna dari workspace');
            if (!empty($res['warning'])) {
                return back()->with('warning', $res['message'] . ' ' . $res['warning']);
            }
            return back()->with('success', $res['message']);
        } catch (AiUnavailableException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Jalankan Investigasi End-to-End (Keyword / Link / Konten -> Multi-Channel Scan -> Highlight Timpa Teks -> AI Lokal).
     */
    public function scan(Request $request)
    {
        $validated = $request->validate([
            'case_id' => 'nullable',
            'text' => 'required|string|min:3',
            'source_url' => 'nullable|url|max:1000',
        ]);

        $rawInput = trim($validated['text']);
        $trace = [];
        $tStart = microtime(true);

        $aiStatus = $this->ai->status();
        $hw = $this->telemetry->snapshot();
        $trace[] = [
            'step' => 'MODEL_INIT',
            'label' => 'Aktivasi Model AI Lokal & Telemetri RAM',
            'detail' => strtoupper($aiStatus['driver'] ?? 'MOCK')
                . ($aiStatus['active_model'] ? " • {$aiStatus['active_model']}" : '')
                . (!empty($hw['ai_process_ram_gb']) ? " • RAM AI: {$hw['ai_process_ram_gb']} GB" : ''),
            'status' => 'OK',
        ];

        // 1. Tentukan atau buat Kasus Investigasi otomatis bila case_id == 'auto' atau kosong
        $caseIdInput = trim((string) ($validated['case_id'] ?? 'auto'));
        if ($caseIdInput === 'auto' || $caseIdInput === '') {
            $autoTitle = Str::limit(preg_replace('/\s+/', ' ', strip_tags($rawInput)), 90);
            $case = InvestigationCase::create([
                'case_code' => InvestigationCase::nextCode(),
                'title' => 'Investigasi: ' . $autoTitle,
                'claim_text' => $rawInput,
                'category' => 'OSINT / MULTI-CHANNEL',
                'priority' => 'SEDANG',
                'status' => 'OPEN',
                'is_simulation' => $this->ai->isSimulation(),
                'created_by' => Auth::id(),
            ]);
            $this->audit->log('CASE_CREATED', $case, [
                'case_code' => $case->case_code,
                'auto_created' => true,
            ]);
        } else {
            $request->validate(['case_id' => 'exists:cases,id']);
            $case = InvestigationCase::with('evidence.source')->findOrFail($caseIdInput);
        }

        // 2. Deteksi URL/Link eksplisit atau di dalam teks & akuisisi bila memungkinkan
        $detectedUrl = $validated['source_url'] ?? null;
        if (!$detectedUrl && preg_match('/https?:\/\/[^\s<>"\']+/i', $rawInput, $urlMatch)) {
            $detectedUrl = $urlMatch[0];
        }

        $inspectionText = $rawInput;
        if ($detectedUrl) {
            $alreadyFetched = $case->evidence()->where('url', $detectedUrl)->exists();
            if (!$alreadyFetched) {
                $fetchedEv = $this->collector->fetchUrl($case, $detectedUrl, [
                    'role' => 'SUBJECT',
                    'platform' => 'WEB',
                    'is_simulation' => $this->ai->isSimulation(),
                ], Auth::user());

                $trace[] = [
                    'step' => 'URL_CAPTURE',
                    'label' => 'Ekstraksi Tautan & Konten Web',
                    'detail' => "{$detectedUrl} -> Status: {$fetchedEv->access_status}",
                    'status' => $fetchedEv->access_status === 'OK' ? 'OK' : 'OFFLINE_FALLBACK',
                ];

                if ($fetchedEv->access_status === 'OK' && !empty($fetchedEv->extracted_text) && mb_strlen($rawInput) < 180) {
                    $inspectionText = trim($rawInput . "\n\n[Konten Halaman URL]: " . Str::limit($fetchedEv->extracted_text, 3000));
                }
            }
        }

        $case->load('evidence.source');

        // 3. Pencarian Lintas Kanal Offline/Lokal (Web/RSS Feeds, Bukti Kasus, Narasi Sosmed, Direktori Terverifikasi)
        $corpusMatches = $this->searchCrossChannelCorpus($inspectionText, $case);
        $trace[] = [
            'step' => 'MULTI_CHANNEL_SCAN',
            'label' => 'Pemindaian Lintas Kanal (Berita, Forum, Sosmed & Arsip Internal)',
            'detail' => 'Ditemukan ' . count($corpusMatches['matches']) . ' sumber pembanding relevan dari total ' . $corpusMatches['scanned_count'] . ' rekaman korpus.',
            'status' => 'OK',
        ];

        // Gabungkan bukti kasus dengan cuplikan pembanding lintas kanal untuk dikirim ke AI Lokal
        $caseEvidence = $case->evidence->map(fn ($e) => [
            'id' => $e->evidence_code,
            'source_id' => $e->source?->source_code,
            'publisher' => $e->publisher,
            'url' => $e->url,
            'excerpt' => $e->extracted_text,
            'access_status' => $e->access_status,
            'published_at' => $e->published_at?->toIso8601String(),
            'retrieved_at' => $e->retrieved_at?->toIso8601String(),
        ])->all();

        $payload = [
            'text' => $inspectionText,
            'case' => ['case_code' => $case->case_code, 'title' => $case->title],
            'evidence' => $caseEvidence,
            'cross_channel_context' => array_slice($corpusMatches['matches'], 0, 5),
            'flags' => [],
        ];

        $validCodes = $case->evidence->pluck('evidence_code')->all();

        try {
            $analysisResult = $this->ai->analyze('credibility_scan', $payload, $validCodes);
            $data = $analysisResult['result'];

            // 4. Deteksi Highlight Potensi Timpa Teks / Editan AI terhadap Referensi & Klaim
            $referenceItems = [];
            foreach ($case->evidence->whereIn('role', ['REFERENCE', 'PRIMARY']) as $ev) {
                $referenceItems[] = [
                    'code' => $ev->evidence_code,
                    'text' => (string) $ev->extracted_text,
                    'is_reference' => true,
                ];
            }
            foreach ($corpusMatches['matches'] as $cm) {
                if (!empty($cm['is_reference']) && !empty($cm['excerpt'])) {
                    $referenceItems[] = [
                        'code' => $cm['code'],
                        'text' => $cm['excerpt'],
                        'is_reference' => true,
                    ];
                }
            }

            $highlights = $this->manipulation->detect($inspectionText, $referenceItems, $data['claims'] ?? []);
            $data['manipulation_highlights'] = $highlights;
            $data['cross_channel_matches'] = $corpusMatches['matches'];

            $trace[] = [
                'step' => 'MANIPULATION_AUDIT',
                'label' => 'Deteksi Timpa Teks, Anomali Angka/Tanggal & Indikasi Editan',
                'detail' => count($highlights) > 0
                    ? count($highlights) . ' segmen indikasi editan / timpa teks ditandai pada konten.'
                    : 'Tidak ditemukan indikasi timpa teks atau deviasi angka/tanggal terhadap rujukan tersedia.',
                'status' => count($highlights) > 0 ? 'FLAGGED' : 'CLEAN',
            ];

            // 5. Evaluasi Kemandirian Sumber (Independence Assessment)
            $caseIndep = $this->sourceRelations->analyze($case, $case->evidence);
            $distinctPublishers = count(array_unique(array_filter(array_map(
                fn ($m) => strtolower(trim((string) ($m['publisher'] ?? ''))),
                $corpusMatches['matches']
            ))));
            $syndicatedCount = count(array_filter(
                $caseIndep['relationships'] ?? [],
                fn ($r) => ($r['type'] ?? '') === 'SYNDICATED_REPUBLISHED'
            ));
            $totalSources = $case->evidence->count() + count($corpusMatches['matches']);
            $independentUnits = max((int) ($caseIndep['independent_units'] ?? 0), $distinctPublishers);

            $indepStatement = $case->evidence->count() >= 2
                ? (string) ($caseIndep['statement'] ?? 'Evaluasi kemandirian selesai.')
                : ($totalSources > 0
                    ? "Terdeteksi {$totalSources} rekaman pembanding dari {$distinctPublishers} penerbit/kanal berbeda di korpus lokal."
                    : 'Belum terdapat cukup bukti pembanding independen pada dataset aktif; diperlukan konfirmasi sumber primer.');

            $data['independence_assessment'] = [
                'independent_units' => $independentUnits,
                'total_sources' => $totalSources,
                'syndicated_count' => $syndicatedCount,
                'confirmed_independent_pairs' => (int) ($caseIndep['confirmed_independent_pairs'] ?? 0),
                'statement' => $indepStatement,
            ];

            $trace[] = [
                'step' => 'INDEPENDENCE_CHECK',
                'label' => 'Evaluasi Kemandirian Sumber & Anti-Sindikasi',
                'detail' => "{$independentUnits} klaster independen • {$totalSources} total sumber • {$syndicatedCount} sindikasi/duplikat",
                'status' => $independentUnits >= 2 ? 'VERIFIED_PLURAL' : 'SINGLE_OR_LIMITED',
            ];

            $elapsedMs = (int) round((microtime(true) - $tStart) * 1000);
            $trace[] = [
                'step' => 'AI_CONCLUSION',
                'label' => 'Penyusunan Kesimpulan Berbasis Bukti & Siap Ekspor PDF/Spreadsheet',
                'detail' => "Skor {$data['credibility_score']}/100 • Klasifikasi {$data['classification']} • {$elapsedMs} ms",
                'status' => 'DONE',
            ];

            $data['investigation_trace'] = $trace;
            $data['hardware_snapshot'] = [
                'model' => $analysisResult['model_name'],
                'driver' => $analysisResult['driver'],
                'ai_ram_used_gb' => $hw['ai_process_ram_gb'] ?? null,
                'ram_total_gb' => $hw['ram_total_gb'] ?? null,
            ];

            $threat = ThreatAnalysis::create([
                'analysis_uuid' => $data['analysis_id'],
                'case_id' => $case->id,
                'module' => 'credibility_scan',
                'source_text' => $inspectionText,
                'result' => $data,
                'credibility_score' => $data['credibility_score'],
                'classification' => $data['classification'],
                'verification_status' => $data['verification_status'],
                'threat_level' => $data['threat_level'],
                'confidence' => $data['confidence'],
                'model_name' => $analysisResult['model_name'],
                'model_version' => $analysisResult['model_version'],
                'driver' => $analysisResult['driver'],
                'is_simulation' => $analysisResult['is_simulation'],
                'validation_status' => 'PENDING REVIEW',
                'scanned_by' => Auth::id(),
                'analyzed_at' => now(),
            ]);

            $this->audit->log('CREDIBILITY_SCAN_RUN', $threat, [
                'case_code' => $case->case_code,
                'credibility_score' => $threat->credibility_score,
                'threat_level' => $threat->threat_level,
                'highlights_count' => count($highlights),
                'cross_matches_count' => count($corpusMatches['matches']),
                'is_simulation' => $threat->is_simulation,
            ]);

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'case_id' => $case->id,
                    'threat' => $threat,
                    'analysis' => $data,
                ]);
            }

            return redirect()->route('scanner.index', ['case_id' => $case->id, 'tab' => 'scan'])
                ->with('success', "Investigasi AI selesai ({$case->case_code}). " . count($highlights) . ' segmen disorot; laporan PDF & Spreadsheet siap diunduh.');
        } catch (\Exception $e) {
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
            }
            return back()->with('error', 'Eksekusi investigasi AI gagal: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Ekspor langsung hasil analisis ke PDF (membuat/memperbarui Intel Brief untuk kasus terkait).
     */
    public function exportAnalysisPdf($id)
    {
        $analysis = ThreatAnalysis::with('case')->findOrFail($id);
        $report = $this->reports->generate($analysis->case, Auth::user());
        return $this->reports->exportPdf($report);
    }

    /**
     * Ekspor langsung hasil analisis ke Spreadsheet (.csv kompatibel Excel).
     */
    public function exportAnalysisSpreadsheet($id)
    {
        $analysis = ThreatAnalysis::with('case')->findOrFail($id);
        $report = $this->reports->generate($analysis->case, Auth::user());
        return $this->reports->exportSpreadsheet($report);
    }

    /**
     * Pencarian Lintas Kanal di seluruh repositori lokal (offline-first).
     *
     * @return array{scanned_count:int,matches:array<int,array<string,mixed>>}
     */
    private function searchCrossChannelCorpus(string $queryText, InvestigationCase $activeCase): array
    {
        $tokens = array_values(array_unique(array_filter(
            TextNormalizer::tokens($queryText),
            fn ($t) => mb_strlen($t) >= 3
        )));
        $keywords = array_slice($tokens, 0, 8);
        $scanned = 0;
        $candidates = [];

        // 1. Bukti di berbagai kasus & platform (Web, Sosial Media, Forum, Referensi)
        $evidences = EvidenceItem::with('case:id,case_code')
            ->whereNotNull('extracted_text')
            ->latest()
            ->take(80)
            ->get();
        $scanned += $evidences->count();

        foreach ($evidences as $ev) {
            $body = trim((string) $ev->extracted_text);
            if ($body === '' || trim($body) === trim($queryText)) {
                continue;
            }
            $sim = $this->lexical->compare($queryText, $ev->title . ' ' . $body);
            if ($sim['score'] >= 10.0 || $ev->case_id === $activeCase->id) {
                $numCmp = $this->numericComparer->compare($queryText, $body);
                $candidates[] = [
                    'code' => $ev->evidence_code,
                    'channel' => strtoupper((string) ($ev->platform ?: 'EVIDENCE')),
                    'case_code' => $ev->case?->case_code,
                    'title' => $ev->title,
                    'publisher' => $ev->publisher ?: ($ev->platform ?: 'Source'),
                    'url' => $ev->url,
                    'similarity_pct' => $sim['score'],
                    'numeric_consistency' => $numCmp['numeric_consistency'],
                    'dates_changed' => $numCmp['dates_changed'],
                    'is_reference' => in_array($ev->role, ['REFERENCE', 'PRIMARY'], true),
                    'role' => $ev->role,
                    'access_status' => $ev->access_status,
                    'excerpt' => Str::limit($body, 320),
                ];
            }
        }

        // 2. Artikel RSS / Feed Berita yang sudah disinkronkan
        $feeds = FeedItem::with('feed:id,name')->latest()->take(60)->get();
        $scanned += $feeds->count();
        foreach ($feeds as $fi) {
            $body = trim($fi->title . ' ' . ($fi->summary ?? ''));
            $sim = $this->lexical->compare($queryText, $body);
            if ($sim['score'] >= 12.0) {
                $numCmp = $this->numericComparer->compare($queryText, $body);
                $candidates[] = [
                    'code' => 'RSS-' . $fi->id,
                    'channel' => 'NEWS_RSS',
                    'case_code' => null,
                    'title' => $fi->title,
                    'publisher' => $fi->feed?->name ?? 'News Feed',
                    'url' => $fi->link,
                    'similarity_pct' => $sim['score'],
                    'numeric_consistency' => $numCmp['numeric_consistency'],
                    'dates_changed' => $numCmp['dates_changed'],
                    'is_reference' => true,
                    'role' => 'FEED_ARTICLE',
                    'access_status' => 'OK',
                    'excerpt' => Str::limit(strip_tags((string) ($fi->summary ?? $fi->title)), 280),
                ];
            }
        }

        // 3. Direktori Informasi Terverifikasi (InformationRecord Approved)
        $records = InformationRecord::with(['versions' => fn ($q) => $q->limit(1)])
            ->where('status', 'APPROVED')
            ->latest()
            ->take(50)
            ->get();
        $scanned += $records->count();
        foreach ($records as $rec) {
            $verBody = $rec->versions->first()?->body ?? $rec->description ?? '';
            $body = trim($rec->title . ' ' . $verBody);
            $sim = $this->lexical->compare($queryText, $body);
            if ($sim['score'] >= 12.0) {
                $numCmp = $this->numericComparer->compare($queryText, $body);
                $candidates[] = [
                    'code' => $rec->record_code,
                    'channel' => 'VERIFIED_DIRECTORY',
                    'case_code' => null,
                    'title' => $rec->title,
                    'publisher' => 'TRACE-ID Official Record',
                    'url' => null,
                    'similarity_pct' => $sim['score'],
                    'numeric_consistency' => $numCmp['numeric_consistency'],
                    'dates_changed' => $numCmp['dates_changed'],
                    'is_reference' => true,
                    'role' => 'OFFICIAL_RECORD',
                    'access_status' => 'OK',
                    'excerpt' => Str::limit(strip_tags((string) $verBody), 280),
                ];
            }
        }

        // 4. Pantauan Narasi Sosial Media (Early Warning)
        if ($keywords !== []) {
            $narratives = Narrative::latest()->take(60)->get();
            $scanned += $narratives->count();
            foreach ($narratives as $nar) {
                $sim = $this->lexical->compare($queryText, (string) $nar->title);
                if ($sim['score'] >= 15.0) {
                    $numCmp = $this->numericComparer->compare($queryText, (string) $nar->title);
                    $candidates[] = [
                        'code' => 'NAR-' . $nar->id,
                        'channel' => 'SOCIAL_MONITOR',
                        'case_code' => null,
                        'title' => $nar->title,
                        'publisher' => strtoupper((string) ($nar->region ?: 'SOCIAL')),
                        'url' => null,
                        'similarity_pct' => $sim['score'],
                        'numeric_consistency' => $numCmp['numeric_consistency'],
                        'dates_changed' => $numCmp['dates_changed'],
                        'is_reference' => false,
                        'role' => 'NARRATIVE_' . strtoupper((string) ($nar->severity ?? 'INFO')),
                        'access_status' => 'OK',
                        'excerpt' => Str::limit((string) $nar->title, 240),
                    ];
                }
            }
        }

        usort($candidates, fn ($a, $b) => $b['similarity_pct'] <=> $a['similarity_pct']);

        return [
            'scanned_count' => $scanned,
            'matches' => array_slice($candidates, 0, 8),
        ];
    }

    /**
     * Jalankan Authenticity Comparator (Supplemental §6)
     */
    public function compare(Request $request)
    {
        $validated = $request->validate([
            'case_id' => 'required|exists:cases,id',
            'reference_id' => 'required|exists:evidence_items,id',
            'compared_id' => 'required|exists:evidence_items,id|different:reference_id',
        ]);

        $ref = EvidenceItem::findOrFail($validated['reference_id']);
        $cmp = EvidenceItem::findOrFail($validated['compared_id']);

        $this->authenticity->compare($ref, $cmp, Auth::user(), true);

        return redirect()->route('scanner.index', ['case_id' => $validated['case_id'], 'tab' => 'compare'])
            ->with('success', 'Perbandingan autentisitas konten berhasil dihitung.');
    }

    /**
     * Jalankan Cross-Source Verification (Supplemental §5)
     */
    public function verifyCrossSource(Request $request)
    {
        $validated = $request->validate([
            'case_id' => 'required|exists:cases,id',
        ]);

        $case = InvestigationCase::findOrFail($validated['case_id']);
        $this->crossSource->run($case, Auth::user(), true);

        return redirect()->route('scanner.index', ['case_id' => $case->id, 'tab' => 'crosssource'])
            ->with('success', 'Verifikasi lintas sumber selesai dieksekusi.');
    }

    /**
     * Human Validation: Analis mereview hasil AI (Supplemental §13)
     */
    public function review(Request $request)
    {
        $validated = $request->validate([
            'analysis_id' => 'required|exists:threat_analyses,id',
            'decision' => 'required|in:VALIDATED,REJECTED,RECLASSIFIED',
            'reason' => 'required|string|min:3',
            'reclassified_status' => 'nullable|string',
        ]);

        $analysis = ThreatAnalysis::findOrFail($validated['analysis_id']);
        $prevResult = $analysis->result;
        $finalResult = $prevResult;

        if ($validated['decision'] === 'RECLASSIFIED' && !empty($validated['reclassified_status'])) {
            $finalResult['classification'] = $validated['reclassified_status'];
            $analysis->classification = $validated['reclassified_status'];
        }

        $analysis->validation_status = $validated['decision'];
        $analysis->save();

        $review = AnalystReview::create([
            'analysis_id' => $analysis->id,
            'user_id' => Auth::id(),
            'decision' => $validated['decision'],
            'reason' => $validated['reason'] ?? 'Disetujui oleh analis.',
            'previous_result' => $prevResult,
            'final_result' => $finalResult,
        ]);

        $this->audit->log('ANALYST_REVIEW_SUBMITTED', $review, [
            'decision' => $review->decision,
            'analysis_id' => $analysis->id,
            'case_id' => $analysis->case_id,
        ]);

        return back()->with('success', "Validasi analis tersimpan: [{$validated['decision']}]. Keputusan resmi dicatat dalam audit trail.");
    }
}
