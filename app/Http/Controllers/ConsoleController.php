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
        $cases = InvestigationCase::latest()->get();
        $caseId = $request->query('case_id');
        $selectedCase = $caseId ? InvestigationCase::with('evidence')->find($caseId) : $cases->first();

        // Get or create session for user
        $session = ConsoleSession::firstOrCreate(
            ['user_id' => Auth::id(), 'case_id' => $selectedCase?->id],
            ['title' => 'Sesi Analis - ' . ($selectedCase?->case_code ?? 'Umum')]
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

        // Eksekusi tool otomatis bila user menanyakan kesamaan atau ringkasan
        $toolCalls = [];
        $contextSummary = [];

        if (stripos($userMsgText, 'ringkasan') !== false || stripos($userMsgText, 'summary') !== false) {
            if ($case) {
                $toolCalls[] = $this->tools->execute('generate_case_summary', ['case_id' => $case->id]);
                $contextSummary['ringkasan_kasus'] = "Kasus {$case->case_code} memuat {$case->evidence->count()} bukti.";
            }
        }
        if (stripos($userMsgText, 'klaim') !== false) {
            $textToExtract = $case?->claim_text ?: $userMsgText;
            $toolRes = $this->tools->execute('extract_claims', ['text' => $textToExtract]);
            $toolCalls[] = $toolRes;
            $contextSummary['klaim_terdeteksi'] = count($toolRes['claims']) . ' klaim utama teridentifikasi.';
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
