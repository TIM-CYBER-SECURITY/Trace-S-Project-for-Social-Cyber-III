<?php

namespace App\Http\Controllers;

use App\Models\AnalystReview;
use App\Models\EvidenceItem;
use App\Models\InvestigationCase;
use App\Models\ThreatAnalysis;
use App\Services\Ai\AiManager;
use App\Services\Analysis\AuthenticityComparator;
use App\Services\Analysis\CrossSourceAnalyzer;
use App\Services\Support\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ScannerController extends Controller
{
    public function __construct(
        private AiManager $ai,
        private AuthenticityComparator $authenticity,
        private CrossSourceAnalyzer $crossSource,
        private AuditLogger $audit
    ) {
    }

    public function index(Request $request)
    {
        $cases = InvestigationCase::with('evidence')->latest()->get();
        $selectedCase = null;
        $caseId = $request->query('case_id');

        if ($caseId) {
            $selectedCase = InvestigationCase::with(['evidence.source', 'sources', 'analyses.reviews.user'])->find($caseId);
        } elseif ($cases->isNotEmpty()) {
            $selectedCase = $cases->first();
        }

        $latestAnalysis = $selectedCase?->analyses()->where('module', 'credibility_scan')->latest()->first();
        $latestComparison = $selectedCase?->comparisons()->latest()->first();

        // Cross-source pre-run if sources exist
        $crossResult = null;
        if ($selectedCase && $selectedCase->evidence->whereIn('role', ['REFERENCE', 'PRIMARY'])->count() >= 1) {
            $crossResult = $this->crossSource->run($selectedCase, Auth::user(), false);
        }

        return view('scanner.index', compact('cases', 'selectedCase', 'latestAnalysis', 'latestComparison', 'crossResult'));
    }

    /**
     * Jalankan Credibility Scan melalui AI Manager (Master §7.3)
     */
    public function scan(Request $request)
    {
        $validated = $request->validate([
            'case_id' => 'required|exists:cases,id',
            'text' => 'required|string|min:5',
        ]);

        $case = InvestigationCase::with('evidence.source')->findOrFail($validated['case_id']);

        $payload = [
            'text' => $validated['text'],
            'case' => ['case_code' => $case->case_code, 'title' => $case->title],
            'evidence' => $case->evidence->map(fn ($e) => [
                'id' => $e->evidence_code,
                'source_id' => $e->source?->source_code,
                'publisher' => $e->publisher,
                'url' => $e->url,
                'excerpt' => $e->extracted_text,
                'access_status' => $e->access_status,
                'published_at' => $e->published_at?->toIso8601String(),
                'retrieved_at' => $e->retrieved_at?->toIso8601String(),
            ])->all(),
            'flags' => [],
        ];

        $validCodes = $case->evidence->pluck('evidence_code')->all();

        try {
            $analysisResult = $this->ai->analyze('credibility_scan', $payload, $validCodes);

            $data = $analysisResult['result'];

            $threat = ThreatAnalysis::create([
                'analysis_uuid' => $data['analysis_id'],
                'case_id' => $case->id,
                'module' => 'credibility_scan',
                'source_text' => $validated['text'],
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
                'is_simulation' => $threat->is_simulation,
            ]);

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'threat' => $threat,
                    'analysis' => $data,
                ]);
            }

            return redirect()->route('scanner.index', ['case_id' => $case->id, 'tab' => 'scan'])
                ->with('success', 'Credibility Scan selesai dieksekusi. Hasil tersimpan dengan status PENDING REVIEW.');
        } catch (\Exception $e) {
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
            }
            return back()->with('error', 'Eksekusi scan AI gagal: ' . $e->getMessage())->withInput();
        }
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

        $res = $this->authenticity->compare($ref, $cmp, Auth::user(), true);

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
        $res = $this->crossSource->run($case, Auth::user(), true);

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
            'reason' => 'required_if:decision,REJECTED,RECLASSIFIED|nullable|string',
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
