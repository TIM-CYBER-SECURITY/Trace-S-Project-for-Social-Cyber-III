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
use App\Services\DataAcquisition\LiveGlobalSearchService;
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
        private LiveGlobalSearchService $liveSearch,
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
                    $latestAnalysis->result['claims'] ?? [],
                    $latestAnalysis->result['extracted_keywords'] ?? []
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

        $rawInput = $this->liveSearch->cleanAndDeduplicateInput($validated['text']);
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

        $kwInfo = $this->liveSearch->extractKeywordsAndQuery($rawInput);
        $trace[] = [
            'step' => 'KEYWORD_EXTRACTION',
            'label' => 'Ekstraksi & Highlight Kata Kunci Pencarian',
            'detail' => count($kwInfo['keywords']) . ' kata kunci utama -> Kueri: "' . $kwInfo['query'] . '"',
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

        // 3. Pencarian Berita Publik & Sosial Media Global Secara Real-Time
        $liveRes = $this->liveSearch->searchRealtime($inspectionText, $case, Auth::user());
        $trace[] = [
            'step' => 'LIVE_GLOBAL_SEARCH',
            'label' => 'Pencarian Berita Publik & Sosial Media Global (Real-Time)',
            'detail' => $liveRes['live_enabled']
                ? ('Ditemukan ' . count($liveRes['matches']) . ' artikel/konten real-time dari: ' . (implode(', ', $liveRes['sources_checked']) ?: 'Portal Berita Global'))
                : 'Mode pengujian lokal aktif (korpus lokal terisolasi).',
            'status' => count($liveRes['matches']) > 0 ? 'OK' : ($liveRes['live_enabled'] ? 'LIMITED' : 'OFFLINE_TEST'),
        ];

        $case->load('evidence.source');

        // 4. Pencarian Lintas Kanal Korpus Internal & Arsip Kasus (Filter Ketat Entitas)
        $corpusMatches = $this->searchCrossChannelCorpus($inspectionText, $case, $kwInfo, $liveRes['matches']);
        $mergedMatches = array_slice(array_merge($liveRes['matches'], $corpusMatches['matches']), 0, 14);
        $corroboration = $this->liveSearch->assessCorroboration($inspectionText, $mergedMatches);

        $trace[] = [
            'step' => 'MULTI_CHANNEL_SCAN',
            'label' => 'Konsolidasi Sumber Lintas Kanal (Berita Publik, Medsos & Arsip Internal)',
            'detail' => count($mergedMatches) . ' sumber terverifikasi relevan (' . count($liveRes['matches']) . ' live web + ' . count($corpusMatches['matches']) . ' arsip korpus dari ' . $corpusMatches['scanned_count'] . ' rekaman).',
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
            'cross_channel_context' => array_slice($mergedMatches, 0, 6),
            'corroboration_summary' => $corroboration['synthesized_conclusion'] ?? '',
            'flags' => [],
        ];

        $validCodes = $case->evidence->pluck('evidence_code')->all();

        try {
            $analysisResult = $this->ai->analyze('credibility_scan', $payload, $validCodes);
            $data = $analysisResult['result'];

            // 5. Deteksi Highlight Kata Kunci & Potensi Timpa Teks / Editan AI terhadap Referensi & Klaim
            $referenceItems = [];
            foreach ($case->evidence->whereIn('role', ['REFERENCE', 'PRIMARY']) as $ev) {
                $referenceItems[] = [
                    'code' => $ev->evidence_code,
                    'text' => (string) $ev->extracted_text,
                    'is_reference' => true,
                ];
            }
            foreach ($mergedMatches as $cm) {
                if (!empty($cm['is_reference']) && !empty($cm['excerpt'])) {
                    $referenceItems[] = [
                        'code' => $cm['code'],
                        'text' => ($cm['title'] ?? '') . ' ' . $cm['excerpt'],
                        'is_reference' => true,
                    ];
                }
            }

            $highlights = $this->manipulation->detect(
                $inspectionText,
                $referenceItems,
                $data['claims'] ?? [],
                $kwInfo['keywords'],
                $corroboration
            );
            $data = $this->calibrateWithEvidence($data, $inspectionText, $corroboration, $highlights, $mergedMatches);
            $data['extracted_keywords'] = $kwInfo['keywords'];
            $data['search_query'] = $kwInfo['query'];
            $data['corroboration'] = $corroboration;
            $data['manipulation_highlights'] = $highlights;
            $data['cross_channel_matches'] = $mergedMatches;

            $trace[] = [
                'step' => 'MANIPULATION_AUDIT',
                'label' => 'Highlight Kata Kunci, Deteksi Timpa Teks & Anomali Angka/Tanggal',
                'detail' => count($highlights) . ' sorotan kata kunci / anomali ditandai pada konten.',
                'status' => collect($highlights)->contains(fn ($h) => ($h['severity'] ?? '') === 'HIGH') ? 'FLAGGED' : 'CLEAN',
            ];

            // 6. Evaluasi Kemandirian Sumber (Independence Assessment)
            $caseIndep = $this->sourceRelations->analyze($case, $case->evidence);
            $distinctPublishers = count(array_unique(array_filter(array_map(
                fn ($m) => strtolower(trim((string) ($m['publisher'] ?? ''))),
                $mergedMatches
            ))));
            $syndicatedCount = count(array_filter(
                $caseIndep['relationships'] ?? [],
                fn ($r) => ($r['type'] ?? '') === 'SYNDICATED_REPUBLISHED'
            ));
            $totalSources = count($mergedMatches) > 0 ? count($mergedMatches) : $case->evidence->count();
            $independentUnits = max((int) ($caseIndep['independent_units'] ?? 0), $distinctPublishers);

            $indepStatement = $distinctPublishers >= 2
                ? "Ditemukan {$totalSources} sumber pembanding lintas kanal dari {$distinctPublishers} penerbit/kanal berbeda (" . implode(', ', array_slice($corroboration['publishers'] ?? [], 0, 4)) . ').'
                : ($case->evidence->count() >= 2
                    ? (string) ($caseIndep['statement'] ?? 'Evaluasi kemandirian selesai.')
                    : 'Belum terdapat cukup bukti pembanding independen; diperlukan konfirmasi sumber primer.');

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
                'cross_matches_count' => count($mergedMatches),
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
     * Kalibrasi keputusan berbasis bukti real-time agar model lokal kecil (mis. 0.5B) tidak
     * mengabaikan konsensus berita aktual atau sekadar mengulang kalimat masukan.
     */
    private function calibrateWithEvidence(
        array $data,
        string $inputText,
        array $corroboration,
        array $highlights,
        array $matches
    ): array {
        if ($matches === []) {
            return $data;
        }

        $hasHighTamper = collect($highlights)->contains(
            fn ($h) => in_array($h['type'] ?? '', ['NUMERIC_TAMPER', 'DATE_TAMPER'], true)
        );
        $hint = (string) ($corroboration['verdict_hint'] ?? 'UNVERIFIED');
        $supporting = (int) ($corroboration['supporting_count'] ?? 0);
        $refuting = (int) ($corroboration['refuting_count'] ?? 0);
        $pubs = implode(', ', array_slice($corroboration['publishers'] ?? [], 0, 5));

        if ($hasHighTamper || $hint === 'REFUTED') {
            if (($data['verification_status'] ?? '') !== 'TERBANTAHKAN' || ($data['credibility_score'] ?? 50) > 35) {
                $data['verification_status'] = 'TERBANTAHKAN';
                $data['classification'] = $hasHighTamper ? 'MANIPULATED_CONTENT' : 'DISINFORMATION';
                $data['content_indicator'] = $hasHighTamper ? 'HASIL EDIT' : 'INDIKASI HOAKS';
                $data['credibility_score'] = $hasHighTamper ? 15 : 20;
                $data['threat_level'] = 'TINGGI';
                $data['confidence'] = max((float) ($data['confidence'] ?? 0.5), 0.78);
                foreach ($data['claims'] ?? [] as $k => $cl) {
                    $data['claims'][$k]['check_status'] = 'TERBANTAHKAN';
                }
            }
            if (!empty($corroboration['synthesized_conclusion'])) {
                $data['tactical_summary'] = $corroboration['synthesized_conclusion'];
            }
        } elseif ($hint === 'CORROBORATED' && $supporting >= 2 && $refuting === 0 && !$hasHighTamper) {
            if (in_array($data['verification_status'] ?? '', ['BELUM TERVERIFIKASI', 'TIDAK CUKUP BUKTI'], true)
                || (int) ($data['credibility_score'] ?? 50) === 50) {
                $data['verification_status'] = 'TERVERIFIKASI';
                $data['classification'] = 'VERIFIED';
                $data['content_indicator'] = 'ASLI';
                $data['credibility_score'] = min(94, 74 + ($supporting * 3));
                $data['threat_level'] = 'RENDAH';
                $data['confidence'] = min(0.92, round(0.68 + ($supporting * 0.04), 2));
                foreach ($data['claims'] ?? [] as $k => $cl) {
                    $data['claims'][$k]['check_status'] = 'TERVERIFIKASI';
                }
            }

            // Pastikan kesimpulan faktual benar-benar menyebut daftar media/sumber (tidak hanya mengulang input)
            $summary = trim((string) ($data['tactical_summary'] ?? $data['summary'] ?? ''));
            $normSummary = mb_strtolower((string) preg_replace('/[^a-z0-9]/i', '', $summary), 'UTF-8');
            $normInput = mb_strtolower((string) preg_replace('/[^a-z0-9]/i', '', $inputText), 'UTF-8');
            if ($normSummary === '' || $normSummary === $normInput || str_contains($normInput, $normSummary) || mb_strlen($summary) < 90) {
                $data['tactical_summary'] = ($corroboration['synthesized_conclusion'] ?? '')
                    . " Status klaim dinilai TERVERIFIKASI berdasarkan {$supporting} pemberitaan selaras ({$pubs}).";
            }
        }

        return $data;
    }

    /**
     * Pencarian Lintas Kanal di seluruh repositori lokal dengan filter entitas ketat (tanpa berita nyasar).
     *
     * @return array{scanned_count:int,matches:array<int,array<string,mixed>>}
     */
    private function searchCrossChannelCorpus(
        string $queryText,
        InvestigationCase $activeCase,
        array $kwInfo = [],
        array $liveMatches = []
    ): array {
        $meaningfulTokens = $kwInfo['meaningful_tokens'] ?? array_values(array_unique(array_filter(
            TextNormalizer::tokens($queryText),
            fn ($t) => mb_strlen($t, 'UTF-8') >= 4
        )));
        $liveUrls = array_flip(array_filter(array_column($liveMatches, 'url')));
        $liveCodes = array_flip(array_filter(array_column($liveMatches, 'code')));
        $scanned = 0;
        $candidates = [];

        $passesTopicGate = function (string $targetText, float $simScore) use ($meaningfulTokens): bool {
            if ($simScore < 20.0) {
                return false;
            }
            if ($meaningfulTokens === []) {
                return true;
            }
            $low = mb_strtolower($targetText, 'UTF-8');
            $hits = 0;
            foreach ($meaningfulTokens as $tok) {
                if (str_contains($low, mb_strtolower($tok, 'UTF-8'))) {
                    $hits++;
                }
            }
            return $hits >= min(2, count($meaningfulTokens));
        };

        // 1. Bukti di berbagai kasus & platform (Web, Sosial Media, Forum, Referensi)
        $evidences = EvidenceItem::with('case:id,case_code')
            ->whereNotNull('extracted_text')
            ->latest()
            ->take(80)
            ->get();
        $scanned += $evidences->count();

        foreach ($evidences as $ev) {
            if (isset($liveCodes[$ev->evidence_code]) || ($ev->url && isset($liveUrls[$ev->url]))) {
                continue;
            }
            if ($ev->case_id === $activeCase->id && $ev->collection_method === 'LIVE_GLOBAL_SEARCH') {
                continue;
            }
            $body = trim((string) $ev->extracted_text);
            if ($body === '' || trim($body) === trim($queryText)) {
                continue;
            }
            $combined = $ev->title . ' ' . $body;
            $sim = $this->lexical->compare($queryText, $combined);
            if ($ev->case_id === $activeCase->id || $passesTopicGate($combined, (float) $sim['score'])) {
                $numCmp = $this->numericComparer->compare($queryText, $body);
                $candidates[] = [
                    'code' => $ev->evidence_code,
                    'channel' => strtoupper((string) ($ev->platform ?: 'EVIDENCE')),
                    'case_code' => $ev->case?->case_code,
                    'title' => $ev->title,
                    'publisher' => $ev->publisher ?: ($ev->platform ?: 'Source'),
                    'url' => $ev->url,
                    'published_at' => $ev->published_at?->format('d M Y H:i'),
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
            if ($fi->link && isset($liveUrls[$fi->link])) {
                continue;
            }
            $body = trim($fi->title . ' ' . ($fi->summary ?? ''));
            $sim = $this->lexical->compare($queryText, $body);
            if ($passesTopicGate($body, (float) $sim['score'])) {
                $numCmp = $this->numericComparer->compare($queryText, $body);
                $candidates[] = [
                    'code' => 'RSS-' . $fi->id,
                    'channel' => 'NEWS_RSS',
                    'case_code' => null,
                    'title' => $fi->title,
                    'publisher' => $fi->feed?->name ?? 'News Feed',
                    'url' => $fi->link,
                    'published_at' => $fi->published_at?->format('d M Y H:i'),
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
            $verBody = $rec->versions->first()?->content ?? $rec->description ?? '';
            $body = trim($rec->title . ' ' . $verBody);
            $sim = $this->lexical->compare($queryText, $body);
            if ($passesTopicGate($body, (float) $sim['score'])) {
                $numCmp = $this->numericComparer->compare($queryText, $body);
                $candidates[] = [
                    'code' => $rec->record_code,
                    'channel' => 'VERIFIED_DIRECTORY',
                    'case_code' => null,
                    'title' => $rec->title,
                    'publisher' => 'TRACE-ID Official Record',
                    'url' => null,
                    'published_at' => $rec->updated_at?->format('d M Y H:i'),
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
        if ($meaningfulTokens !== []) {
            $narratives = Narrative::latest()->take(60)->get();
            $scanned += $narratives->count();
            foreach ($narratives as $nar) {
                $sim = $this->lexical->compare($queryText, (string) $nar->title);
                if ($passesTopicGate((string) $nar->title, (float) $sim['score'])) {
                    $numCmp = $this->numericComparer->compare($queryText, (string) $nar->title);
                    $candidates[] = [
                        'code' => 'NAR-' . $nar->id,
                        'channel' => 'SOCIAL_MONITOR',
                        'case_code' => null,
                        'title' => $nar->title,
                        'publisher' => strtoupper((string) ($nar->region ?: 'SOCIAL')),
                        'url' => null,
                        'published_at' => $nar->created_at?->format('d M Y H:i'),
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
            'matches' => array_slice($candidates, 0, 6),
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
