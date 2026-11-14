<?php

namespace App\Http\Controllers;

use App\Models\ConsoleMessage;
use App\Models\ConsoleSession;
use App\Models\InvestigationCase;
use App\Services\Ai\AiManager;
use App\Services\Ai\PromptBuilder;
use App\Services\Ai\Support\PromptGuard;
use App\Services\Ai\ToolRegistry;
use App\Services\Support\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ConsoleController extends Controller
{
    public function __construct(
        private AiManager $ai,
        private PromptBuilder $prompts,
        private ToolRegistry $tools,
        private AuditLogger $audit
    ) {
    }

    public function index(Request $request)
    {
        $orgId = Auth::user()->organization_id;
        $cases = InvestigationCase::forOrganization($orgId)->latest()->get();
        $caseId = $request->query('case_id');
        $selectedCase = $caseId ? InvestigationCase::forOrganization($orgId)->with('evidence')->find($caseId) : $cases->first();

        // Get or create session for user
        $session = ConsoleSession::firstOrCreate(
            ['user_id' => Auth::id(), 'case_id' => $selectedCase?->id],
            [
                'title' => 'Sesi Analis - ' . ($selectedCase?->case_code ?? 'Umum'),
                'organization_id' => $orgId,
            ]
        );

        $messages = $session->messages()->orderBy('id')->get();

        return view('console.index', compact('cases', 'selectedCase', 'session', 'messages'));
    }

    public function sendMessage(Request $request)
    {
        $validated = $request->validate([
            'session_id' => 'required|exists:console_sessions,id',
            'message' => 'required|string|max:4000',
        ]);

        $session = ConsoleSession::with('case.evidence')->findOrFail($validated['session_id']);
        if ((int) $session->user_id !== (int) Auth::id()) {
            $this->audit->log('CONSOLE_SESSION_DENIED', $session, ['requested_session' => $session->id], Auth::id(), 'DENIED');
            abort(403, 'Anda tidak berwenang mengakses sesi konsol milik akun lain.');
        }
        $userMsgText = trim($validated['message']);

        // 1. Cek Prompt Injection
        $guard = PromptGuard::scan($userMsgText);

        // Simpan pesan user
        $userMsg = ConsoleMessage::create([
            'session_id' => $session->id,
            'role' => 'user',
            'content' => $userMsgText,
            'sources' => null,
            'tool_calls' => $guard['flagged'] ? ['prompt_injection_warning' => $guard['patterns']] : null,
            'is_simulation' => false,
        ]);

        // Bangun konteks kasus
        $case = $session->case;
        $context = [
            'case_code' => $case?->case_code ?? 'TIDAK_TERIKAT',
            'title' => $case?->title ?? 'Eksplorasi Bebas',
            'claim_text' => $case?->claim_text ?? '',
            'evidence_items' => $case?->evidence->map(fn ($e) => [
                'id' => $e->evidence_code,
                'role' => $e->role,
                'publisher' => $e->publisher,
                'excerpt' => mb_substr((string) $e->extracted_text, 0, 300),
            ])->all() ?? [],
        ];

        // Eksekusi tool allowlist secara kontekstual sesuai kueri analis
        $toolCalls = [];
        $contextSummary = [];

        if (preg_match('/ringkas|summary|ikhtisar/i', $userMsgText)) {
            if ($case) {
                $toolCalls[] = $this->tools->execute('generate_case_summary', ['case_id' => $case->id]);
                $contextSummary['ringkasan_kasus'] = "Kasus {$case->case_code} memuat {$case->evidence->count()} bukti.";
            }
        }
        if (preg_match('/klaim|claim/i', $userMsgText)) {
            $textToExtract = $case?->claim_text ?: $userMsgText;
            $toolRes = $this->tools->execute('extract_claims', ['text' => $textToExtract]);
            $toolCalls[] = $toolRes;
            $contextSummary['klaim_terdeteksi'] = count($toolRes['claims']) . ' klaim utama teridentifikasi.';
        }
        if ($case && $case->evidence->count() >= 2 && preg_match('/angka|nominal|numerik|statistik|entitas|numeric/i', $userMsgText)) {
            $ref = $case->evidence->whereIn('role', ['REFERENCE', 'PRIMARY'])->first() ?? $case->evidence->first();
            $cmp = $case->evidence->where('id', '!=', $ref->id)->first();
            if ($ref && $cmp) {
                $numRes = $this->tools->execute('compare_numeric_entities', [
                    'text_a' => (string) $ref->extracted_text,
                    'text_b' => (string) $cmp->extracted_text,
                ]);
                $toolCalls[] = $numRes;
                $contextSummary['konsistensi_numerik'] = $numRes['result']['numeric_consistency'] ?? 'CHECKED';
            }
        }
        if ($case && $case->evidence->count() >= 2 && preg_match('/mirip|kemiripan|banding|diff|similarity|persamaan/i', $userMsgText)) {
            $ref = $case->evidence->whereIn('role', ['REFERENCE', 'PRIMARY'])->first() ?? $case->evidence->first();
            $cmp = $case->evidence->where('id', '!=', $ref->id)->first();
            if ($ref && $cmp) {
                $simRes = $this->tools->execute('calculate_text_similarity', [
                    'text_a' => (string) $ref->extracted_text,
                    'text_b' => (string) $cmp->extracted_text,
                ]);
                $toolCalls[] = $simRes;
                $contextSummary['kemiripan_leksikal'] = ($simRes['result']['score'] ?? 0) . '% (' . ($ref->evidence_code) . ' vs ' . ($cmp->evidence_code) . ')';
            }
        }
        if ($case && preg_match('/sebar|jaringan|spread|aktor|platform/i', $userMsgText)) {
            $spreadRes = $this->tools->execute('analyze_spread', ['case_id' => $case->id]);
            $toolCalls[] = $spreadRes;
            $contextSummary['analisis_sebaran'] = "{$spreadRes['total_posts']} unggahan, {$spreadRes['actors_observed']} pola akun terpantau.";
        }
        if ($case && preg_match('/sumber|lintas|sindikasi|cross/i', $userMsgText)) {
            $relRes = $this->tools->execute('analyze_source_relationships', ['case_id' => $case->id]);
            $toolCalls[] = $relRes;
            $contextSummary['relasi_sumber'] = $relRes['status'] ?? 'CHECKED';
        }
        if ($case && preg_match('/klaster|cluster/i', $userMsgText)) {
            $clRes = $this->tools->execute('cluster_narratives', ['case_id' => $case->id]);
            $toolCalls[] = $clRes;
            $contextSummary['klaster_narasi'] = count($clRes['result']['clusters'] ?? []) . ' klaster teridentifikasi.';
        }

        // Histori pesan
        $history = $session->messages()->orderBy('id')->get()->map(fn ($m) => [
            'role' => $m->role,
            'content' => $m->content,
        ])->all();

        $messages = $this->prompts->console($history, $userMsgText, $context);

        try {
            $reply = $this->ai->chat($messages, [
                'context_summary' => $contextSummary,
            ]);

            $sources = $case ? $case->evidence->take(3)->pluck('evidence_code')->all() : [];

            $assistantMsg = ConsoleMessage::create([
                'session_id' => $session->id,
                'role' => 'assistant',
                'content' => $reply['content'],
                'sources' => $sources,
                'tool_calls' => $toolCalls ?: null,
                'is_simulation' => $reply['is_simulation'],
            ]);

            $this->audit->log('CONSOLE_CHAT', $session, [
                'user_msg_length' => strlen($userMsgText),
                'tools_used' => count($toolCalls),
            ]);

            return response()->json([
                'status' => 'OK',
                'message' => [
                    'id' => $assistantMsg->id,
                    'role' => 'assistant',
                    'content' => $assistantMsg->content,
                    'sources' => $sources,
                    'tool_calls' => $toolCalls,
                    'is_simulation' => $assistantMsg->is_simulation,
                    'model' => $reply['model'],
                    'created_at' => $assistantMsg->created_at->format('H:i:s'),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'ERROR',
                'error' => 'Konsol AI gagal merespons: ' . $e->getMessage(),
            ], 500);
        }
    }
}
