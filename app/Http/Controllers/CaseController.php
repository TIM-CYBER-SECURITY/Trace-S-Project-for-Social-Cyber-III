<?php

namespace App\Http\Controllers;

use App\Models\InvestigationCase;
use App\Services\DataAcquisition\EvidenceCollectorService;
use App\Services\Support\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CaseController extends Controller
{
    public function __construct(
        private EvidenceCollectorService $collector,
        private AuditLogger $audit
    ) {
    }

    public function index()
    {
        $cases = InvestigationCase::with(['creator', 'evidence', 'analyses', 'reports'])
            ->withCount(['evidence', 'sources', 'clusters', 'actors'])
            ->latest()
            ->paginate(15);

        return view('cases.index', compact('cases'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'claim_text' => 'required|string',
            'category' => 'required|string|max:100',
            'priority' => 'required|in:RENDAH,SEDANG,TINGGI,KRITIS',
            'description' => 'nullable|string',
            'operator_notes' => 'nullable|string',
        ]);

        $code = InvestigationCase::nextCode();

        $case = InvestigationCase::create([
            'case_code' => $code,
            'title' => $validated['title'],
            'claim_text' => $validated['claim_text'],
            'category' => $validated['category'],
            'priority' => $validated['priority'],
            'description' => $validated['description'] ?? null,
            'operator_notes' => $validated['operator_notes'] ?? null,
            'status' => 'OPEN',
            'is_simulation' => false,
            'created_by' => Auth::id(),
        ]);

        $this->audit->log('CASE_CREATED', $case, [
            'case_code' => $case->case_code,
            'priority' => $case->priority,
        ]);

        return redirect()->route('cases.show', $case->id)
            ->with('success', "Kasus investigasi {$case->case_code} berhasil dibuat.");
    }

    public function show($id)
    {
        $case = InvestigationCase::with([
            'creator',
            'evidence.source',
            'sources',
            'analyses.reviews.user',
            'clusters.items.evidence',
            'actors',
            'reports.author',
            'comparisons.reference',
            'comparisons.compared',
            'similarities',
            'claimComparisons',
            'relationships',
        ])->findOrFail($id);

        // Hitung progress 12 langkah operasional
        $steps = [
            ['name' => 'Monitoring & Early Warning', 'done' => true, 'desc' => 'Terdeteksi pada peringatan sentimen / feed'],
            ['name' => 'Identifikasi Klaim', 'done' => !empty($case->claim_text), 'desc' => 'Klaim utama kasus terdefinisi'],
            ['name' => 'Pengumpulan Bukti', 'done' => $case->evidence->count() > 0, 'desc' => $case->evidence->count() . ' berkas bukti digital terkumpul'],
            ['name' => 'Pembentukan Kasus', 'done' => true, 'desc' => 'Nomor kasus terdaftar: ' . $case->case_code],
            ['name' => 'Ekstraksi Informasi', 'done' => $case->evidence->whereNotNull('extracted_text')->count() > 0, 'desc' => 'Teks bukti diekstrak tanpa alterasi'],
            ['name' => 'Cross-Source Verification', 'done' => $case->similarities->count() > 0, 'desc' => 'Perbandingan lintas sumber dijalankan'],
            ['name' => 'Authenticity Comparison', 'done' => $case->comparisons->count() > 0, 'desc' => 'Analisis komparatif referensi vs editan'],
            ['name' => 'Narrative Clustering', 'done' => $case->clusters->count() > 0, 'desc' => $case->clusters->count() . ' klaster narasi terdeteksi'],
            ['name' => 'Spread Analysis', 'done' => $case->actors->count() > 0, 'desc' => 'Pemetaan aktor penyebar pada dataset'],
            ['name' => 'AI Preliminary Assessment', 'done' => $case->analyses->count() > 0, 'desc' => 'Skoring & rekomendasi AI selesai'],
            ['name' => 'Human Validation', 'done' => $case->analyses->pluck('reviews')->flatten()->count() > 0, 'desc' => 'Tervalidasi oleh analis manusia'],
            ['name' => 'Command Reporting', 'done' => $case->reports->count() > 0, 'desc' => $case->reports->count() . ' Intel Brief terbit'],
        ];

        return view('cases.show', compact('case', 'steps'));
    }

    public function importEvidence(Request $request, $id)
    {
        $case = InvestigationCase::findOrFail($id);

        $maxKb = (int) config('trace.upload.max_kb', 5120);
        $exts = implode(',', config('trace.upload.mimes', ['txt', 'pdf', 'png', 'jpg', 'jpeg', 'md', 'html']));

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'publisher' => 'nullable|string|max:150',
            'platform' => 'required|string|max:50',
            'role' => 'required|in:REFERENCE,PRIMARY,COMPARED,POST,SUBJECT,OTHER',
            'type' => 'required|in:TEXT,URL,IMAGE,DOCUMENT,SCREENSHOT',
            'extracted_text' => 'nullable|string',
            'evidence_file' => ['nullable', 'file', 'max:' . $maxKb, 'extensions:' . $exts],
            'collection_notes' => 'nullable|string',
            'published_at' => 'nullable|date',
            'is_simulation' => 'nullable|boolean',
        ]);

        $file = $request->file('evidence_file');
        $item = $this->collector->importManual($case, $validated, $file, Auth::user());

        return back()->with('success', "Bukti {$item->evidence_code} berhasil diimpor ke kasus {$case->case_code}.");
    }

    public function fetchUrlEvidence(Request $request, $id)
    {
        $case = InvestigationCase::findOrFail($id);

        $validated = $request->validate([
            'url' => 'required|url',
            'title' => 'nullable|string|max:255',
            'publisher' => 'nullable|string|max:150',
            'role' => 'required|in:REFERENCE,PRIMARY,COMPARED,POST,SUBJECT,OTHER',
            'is_simulation' => 'nullable|boolean',
        ]);

        $item = $this->collector->fetchUrl($case, $validated['url'], $validated, Auth::user());

        $msg = $item->access_status === 'OK'
            ? "URL berhasil diakses dan disimpan sebagai bukti {$item->evidence_code}."
            : "Pengambilan URL selesai dengan status: {$item->access_status}. Catatan tersimpan.";

        return back()->with($item->access_status === 'OK' ? 'success' : 'warning', $msg);
    }
}
