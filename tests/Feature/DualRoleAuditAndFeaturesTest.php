<?php

namespace Tests\Feature;

use App\Models\AiServer;
use App\Models\EvidenceItem;
use App\Models\InvestigationCase;
use App\Models\Narrative;
use App\Models\RssFeed;
use App\Models\SystemLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\CreatesTraceFixtures;
use Tests\TestCase;

class DualRoleAuditAndFeaturesTest extends TestCase
{
    use CreatesTraceFixtures;
    use RefreshDatabase;

    private function buildSamplePdf(string $text, bool $compress = true): string
    {
        $escaped = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
        $streamContent = "BT\n/F1 12 Tf\n72 720 Td\n({$escaped}) Tj\nET";
        if ($compress && function_exists('gzcompress')) {
            $encoded = gzcompress($streamContent);
            $streamObj = "<< /Length " . strlen($encoded) . " /Filter /FlateDecode >>\nstream\n{$encoded}\nendstream";
        } else {
            $streamObj = "<< /Length " . strlen($streamContent) . " >>\nstream\n{$streamContent}\nendstream";
        }

        return "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
            . "4 0 obj\n{$streamObj}\nendobj\n"
            . "trailer\n<< /Root 1 0 R >>\n%%EOF";
    }

    public function test_pdf_text_extractor_and_authenticated_evidence_download_work_securely(): void
    {
        $this->setUpTraceFixtures();
        $org = $this->makeOrg('PUSSIBERAD');
        $operator = $this->makeUser('OPERATOR', $org);

        $case = InvestigationCase::create([
            'case_code' => 'TS-2026-8801',
            'title' => 'Audit PDF Upload & Download',
            'claim_text' => 'Siaran pers resmi menyatakan program berjalan normal sepanjang 2026.',
            'category' => 'KLARIFIKASI',
            'priority' => 'SEDANG',
            'status' => 'OPEN',
            'is_simulation' => false,
            'created_by' => $operator->id,
        ]);

        $otherCase = InvestigationCase::create([
            'case_code' => 'TS-2026-8802',
            'title' => 'Kasus Terpisah',
            'claim_text' => 'Klaim lain',
            'category' => 'UMUM',
            'priority' => 'RENDAH',
            'status' => 'OPEN',
            'is_simulation' => false,
            'created_by' => $operator->id,
        ]);

        $pdfBytes = $this->buildSamplePdf('Siaran Pers Resmi Kemhan Nomor 12 Tahun 2026 berjalan normal.', true);
        $uploadedPdf = UploadedFile::fake()->createWithContent('siaran-resmi.pdf', $pdfBytes);

        $this->actingAs($operator)->post(route('cases.import-evidence', $case->id), [
            'title' => 'Dokumen Siaran Pers PDF',
            'publisher' => 'Kemhan RI',
            'platform' => 'DOKUMEN',
            'role' => 'REFERENCE',
            'type' => 'DOCUMENT',
            'evidence_file' => $uploadedPdf,
        ])->assertRedirect();

        $evidence = EvidenceItem::where('case_id', $case->id)->firstOrFail();
        $this->assertNotEmpty($evidence->raw_path);
        $this->assertStringContainsString('Siaran Pers Resmi Kemhan Nomor 12 Tahun 2026 berjalan normal.', (string) $evidence->extracted_text);
        $this->assertStringContainsString('[PDF Extractor]: Teks diekstrak otomatis', (string) $evidence->collection_notes);

        // Unduh oleh Operator berhasil & mencatat audit
        $dl = $this->actingAs($operator)->get(route('cases.evidence.download', [$case->id, $evidence->id]));
        $dl->assertOk();
        $dl->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertDatabaseHas('system_logs', [
            'action' => 'EVIDENCE_FILE_DOWNLOADED',
            'user_id' => $operator->id,
        ]);

        // IDOR check: mengakses evidence_id milik $case menggunakan URL $otherCase ditolak 404
        $this->actingAs($operator)
            ->get(route('cases.evidence.download', [$otherCase->id, $evidence->id]))
            ->assertNotFound();
    }

    public function test_rss_feed_governance_enforces_admin_only_controls(): void
    {
        $this->setUpTraceFixtures();
        $org = $this->makeOrg('PUSSIBERAD');
        $admin = $this->makeUser('ADMINISTRATOR', $org);
        $operator = $this->makeUser('OPERATOR', $org);

        $feed = RssFeed::create([
            'name' => 'Antara News - Nasional',
            'feed_url' => 'https://www.antaranews.com/rss/nasional.xml',
            'is_active' => true,
            'last_status' => 'OK',
        ]);

        // Operator ditolak mengelola RSS feed
        $this->actingAs($operator)->post(route('early-warning.rss-feeds.store'), [
            'name' => 'Feed Operator',
            'feed_url' => 'https://www.antaranews.com/rss/terkini.xml',
        ])->assertForbidden();
        $this->actingAs($operator)->post(route('early-warning.rss-feeds.toggle', $feed->id))->assertForbidden();
        $this->actingAs($operator)->delete(route('early-warning.rss-feeds.destroy', $feed->id))->assertForbidden();

        // Administrator berhasil menonaktifkan & menghapus feed
        $this->actingAs($admin)->post(route('early-warning.rss-feeds.toggle', $feed->id))->assertRedirect();
        $this->assertFalse((bool) $feed->fresh()->is_active);

        $this->actingAs($admin)->get(route('admin.system.index'))
            ->assertOk()
            ->assertSee('Antara News - Nasional');

        $this->actingAs($admin)->delete(route('early-warning.rss-feeds.destroy', $feed->id))->assertRedirect();
        $this->assertDatabaseMissing('rss_feeds', ['id' => $feed->id]);
    }

    public function test_console_multi_tool_dispatch_and_dual_role_record_handoff(): void
    {
        $this->setUpTraceFixtures();
        $org = $this->makeOrg('PUSSIBERAD');
        $admin = $this->makeUser('ADMINISTRATOR', $org);
        $operator = $this->makeUser('OPERATOR', $org);

        $case = InvestigationCase::create([
            'case_code' => 'TS-2026-8803',
            'title' => 'Verifikasi Klaim Tarif BBM',
            'claim_text' => 'Beredar video TikTok mengklaim harga BBM subsidi naik Rp 18.000 per liter mulai besok.',
            'category' => 'EKONOMI',
            'priority' => 'TINGGI',
            'status' => 'OPEN',
            'is_simulation' => false,
            'created_by' => $operator->id,
        ]);

        EvidenceItem::create([
            'case_id' => $case->id,
            'evidence_code' => 'EV-001',
            'type' => 'TEXT',
            'role' => 'REFERENCE',
            'title' => 'Siaran Pers Pertamina',
            'publisher' => 'Pertamina',
            'platform' => 'WEB',
            'access_status' => 'OK',
            'extracted_text' => 'Harga Pertalite resmi tetap Rp 10.000 per liter sesuai ketetapan pemerintah tahun 2026.',
            'content_hash' => EvidenceItem::hashOf('ref'),
            'collection_method' => 'MANUAL_IMPORT',
            'added_by' => $operator->id,
        ]);
        EvidenceItem::create([
            'case_id' => $case->id,
            'evidence_code' => 'EV-002',
            'type' => 'TEXT',
            'role' => 'POST',
            'title' => 'Unggahan TikTok Viral',
            'publisher' => '@info_cepat_viral',
            'platform' => 'TIKTOK',
            'access_status' => 'OK',
            'extracted_text' => 'Breaking! Harga BBM subsidi resmi naik jadi Rp 18.000 per liter mulai besok.',
            'content_hash' => EvidenceItem::hashOf('cmp'),
            'collection_method' => 'MANUAL_IMPORT',
            'added_by' => $operator->id,
        ]);

        // Buka halaman konsol sebagai Operator (auto-create ConsoleSession)
        $this->actingAs($operator)->get(route('console.index', ['case_id' => $case->id]))->assertOk();
        $session = \App\Models\ConsoleSession::where('user_id', $operator->id)->where('case_id', $case->id)->firstOrFail();

        $res = $this->actingAs($operator)->postJson(route('console.message'), [
            'session_id' => $session->id,
            'message' => 'Tolong berikan ringkasan kasus, cek angka nominal harga BBM, kemiripan teks, dan sebaran platform.',
        ]);

        $res->assertOk()->assertJsonPath('status', 'OK');
        $tools = collect($res->json('message.tool_calls'))->pluck('tool')->all();
        $this->assertContains('generate_case_summary', $tools);
        $this->assertContains('compare_numeric_entities', $tools);
        $this->assertContains('calculate_text_similarity', $tools);
        $this->assertContains('analyze_spread', $tools);

        // Dual-role InformationRecord lifecycle: Operator create -> submit -> Admin asks REVISION REQUIRED -> Operator revise & resubmit -> Admin APPROVE
        $this->actingAs($operator)->post(route('records.store'), [
            'title' => 'Laporan Observasi Isu BBM TikTok',
            'description' => 'Temuan awal kanal TikTok',
            'category' => 'narrative_observation',
            'content' => 'Draf v1 observasi hoaks kenaikan BBM Rp 18.000.',
            'submit_now' => '1',
        ])->assertRedirect();

        $rec = \App\Models\InformationRecord::where('created_by', $operator->id)->firstOrFail();
        $this->assertSame('SUBMITTED', $rec->status);

        // Operator tidak boleh review sendiri
        $this->actingAs($operator)->post(route('records.review', $rec->record_code), [
            'decision' => 'APPROVED',
            'comment' => 'Self approve',
        ])->assertForbidden();

        // Admin minta revisi
        $this->actingAs($admin)->post(route('records.review', $rec->record_code), [
            'decision' => 'REVISION REQUIRED',
            'comment' => 'Lengkapi rujukan resmi Pertamina EV-001.',
        ])->assertRedirect();
        $this->assertSame('REVISION REQUIRED', $rec->fresh()->status);

        // Operator revisi (v2) lalu ajukan ulang
        $this->actingAs($operator)->put(route('records.update', $rec->record_code), [
            'title' => 'Laporan Observasi Isu BBM TikTok (Revisi v2)',
            'description' => 'Temuan terverifikasi dengan rujukan Pertamina',
            'category' => 'narrative_observation',
            'content' => 'Observasi final v2: klaim Rp 18.000 terbantahkan oleh EV-001 (Rp 10.000/liter).',
            'change_reason' => 'Penambahan rujukan EV-001',
        ])->assertRedirect();
        $this->actingAs($operator)->post(route('records.submit', $rec->record_code))->assertRedirect();

        // Admin menyetujui v2
        $this->actingAs($admin)->post(route('records.review', $rec->record_code), [
            'decision' => 'APPROVED',
            'comment' => 'Terverifikasi lengkap dan akurat.',
        ])->assertRedirect();

        $rec->refresh();
        $this->assertSame('APPROVED', $rec->status);
        $this->assertSame(2, (int) $rec->approved_version);
    }

    public function test_ai_json_extractor_and_multilingual_schema_normalizer_handle_open_weights_edge_cases(): void
    {
        $rawWithThinkAndTrailingComma = "<think>Analisis klaim rekrutmen palsu</think>\n```json\n"
            . json_encode([
                'module' => 'credibility_scan',
                'summary' => 'Tautan Telegram rekrutmen TNI-AD tanpa tes terbantahkan pengumuman Dispenad.',
                'is_simulation' => false,
                'claims' => [
                    ['id' => 'c1', 'text' => 'Rekrutmen TNI-AD tanpa tes pungut Rp 2,5 juta.', 'type' => 'KLAIM', 'check_status' => 'BELUM CUKUP BUKTI'],
                ],
                'verification_status' => 'HOAKS',
                'content_indicator' => 'INDIKASI HOAKS',
                'classification' => 'PENIPUAN',
                'credibility_score' => 5,
                'threat_level' => 'DARURAT',
                'confidence' => 92,
                'linguistic_analysis' => [
                    'emotion' => ['panik', 'iming-iming'],
                    'fallacy' => 'false authority',
                    'source_verification' => 'Bertentangan dengan E-001',
                ],
                'findings' => [
                    ['statement' => 'Dispenad menyatakan rekrutmen TNI-AD gratis.', 'type' => 'FAKTA', 'evidence_ids' => ['e-001', 'EV-FIKTIF']],
                    ['statement' => 'Temuan tanpa bukti sah.', 'type' => 'FAKTA', 'evidence_ids' => ['EV-FIKTIF']],
                ],
                'evidence' => [
                    ['id' => 'e-001', 'excerpt' => 'Seluruh proses seleksi TNI-AD gratis.'],
                    ['id' => 'EV-PALSU', 'excerpt' => 'Bukti karangan.'],
                ],
                'sources' => [['id' => 'E-001', 'access_status' => 'AVAILABLE']],
                'recommendations' => ['Terbitkan klarifikasi resmi.'],
                'limitations' => ['Verifikasi kanal Telegram terbatas pada teks publik.'],
                'tactical_summary' => 'Terindikasi penipuan catut TNI-AD; rekomendasikan blokir dan klarifikasi.',
            ], JSON_UNESCAPED_UNICODE)
            . "\n```";

        $trailingComma = "<think>ok</think>```json\n{\"summary\":\"uji\",\"recommendations\":[\"r1\",],}\n```";
        $this->assertSame(['summary' => 'uji', 'recommendations' => ['r1']], \App\Services\Ai\Support\JsonExtractor::extract($trailingComma));

        $driver = new class($rawWithThinkAndTrailingComma) implements \App\Services\Ai\Contracts\AiDriver {
            public function __construct(private string $payload) {}
            public function name(): string { return 'openai_compatible'; }
            public function isSimulation(): bool { return false; }
            public function modelName(): ?string { return 'test-open-weights-1.5b'; }
            public function checkHealth(): array { return ['status' => 'ONLINE', 'latency_ms' => 1, 'error' => null]; }
            public function listModels(): array { return []; }
            public function getModelInfo(string $model): array { return []; }
            public function validateConnection(): array { return ['ok' => true, 'status' => 'ONLINE', 'latency_ms' => 1, 'message' => 'ok', 'details' => []]; }
            public function chat(array $messages, array $options = []): array { return ['content' => 'ok', 'thinking' => null, 'model' => 'test', 'is_simulation' => false]; }
            public function analyze(array $request): string { return $this->payload; }
            public function embed(array $texts): array { return []; }
        };

        /** @var \App\Services\Ai\AiManager $ai */
        $ai = app(\App\Services\Ai\AiManager::class);
        $ai->setDriver($driver);
        try {
            $res = $ai->analyze('credibility_scan', ['text' => 'uji', 'evidence' => [], 'case' => null, 'flags' => []], ['E-001']);
        } finally {
            $ai->resetDriver();
        }

        $out = $res['result'];
        $this->assertSame(1, $res['attempts']);
        $this->assertSame('TERBANTAHKAN', $out['verification_status']);
        $this->assertSame('DISINFORMATION', $out['classification']);
        $this->assertSame('KRITIS', $out['threat_level']);
        $this->assertSame(0.92, $out['confidence']);
        $this->assertSame('TIDAK CUKUP BUKTI', $out['claims'][0]['check_status']);
        $this->assertSame('panik; iming-iming', $out['linguistic_analysis']['emotion']);
        $this->assertSame(['E-001'], $out['findings'][0]['evidence_ids']);
        $this->assertSame('INTERPRETASI_AI', $out['findings'][1]['type']);
        $this->assertCount(1, $out['evidence']);
        $this->assertSame('E-001', $out['evidence'][0]['id']);
    }
}
