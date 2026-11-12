<?php

namespace Tests\Feature;

use App\Models\AiModel;
use App\Models\AiServer;
use App\Models\Connector;
use App\Models\InvestigationCase;
use App\Models\Narrative;
use App\Models\RssFeed;
use App\Models\SystemLog;
use App\Services\Ai\AiManager;
use App\Services\Ai\PromptBuilder;
use App\Services\Ai\Support\JsonExtractor;
use App\Services\DataAcquisition\RssIngestionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\CreatesTraceFixtures;
use Tests\TestCase;

class AiLocalIntegrationTest extends TestCase
{
    use RefreshDatabase;
    use CreatesTraceFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTraceFixtures();
    }

    protected function tearDown(): void
    {
        $this->cleanTestingProvisioning();
        parent::tearDown();
    }

    public function test_json_extractor_strips_reasoning_think_blocks_and_code_fences(): void
    {
        $raw = "<think>\nSaya harus memeriksa skema: {\"bad\": json}\ndan memilih hasil akhir.\n</think>\n```json\n{\"module\":\"credibility_scan\",\"summary\":\"Netral\",\"credibility_score\":55}\n```";

        $this->assertStringContainsString('Saya harus memeriksa skema', (string) JsonExtractor::extractThinking($raw));
        $stripped = JsonExtractor::stripThinking($raw);
        $this->assertStringNotContainsString('<think>', $stripped);

        $extracted = JsonExtractor::extract($raw);
        $this->assertIsArray($extracted);
        $this->assertSame('credibility_scan', $extracted['module']);
        $this->assertSame(55, $extracted['credibility_score']);
    }

    public function test_prompt_builder_explicitly_instructs_empty_evidence_when_none_present(): void
    {
        $pb = app(PromptBuilder::class);
        $msgs = $pb->analysis('credibility_scan', [
            'text' => 'Klaim uji tanpa bukti pembanding.',
            'case' => ['case_code' => 'TS-2026-010', 'title' => 'Kasus Uji'],
            'evidence' => [],
            'flags' => [],
        ]);

        $this->assertStringContainsString('KOSONG', $msgs[1]['content']);
        $this->assertStringContainsString('"evidence": []', $msgs[1]['content']);
        $this->assertStringNotContainsString('E-001', $msgs[0]['content']);
        $this->assertStringNotContainsString('E-001', $msgs[1]['content']);
    }

    public function test_lm_studio_and_pussiberad_openai_compatible_flow_handles_reasoning_models(): void
    {
        $admin = $this->makeUser('ADMINISTRATOR');

        $llmPayload = json_encode([
            'module' => 'credibility_scan',
            'summary' => 'Klaim belum didukung bukti primer.',
            'is_simulation' => false,
            'claims' => [
                ['id' => 'c1', 'text' => 'Klaim pengalihan anggaran total.', 'type' => 'fakta', 'check_status' => 'BELUM_TERVERIFIKASI'],
            ],
            'verification_status' => 'tidak_cukup_bukti',
            'content_indicator' => 'BELUM DAPAT DIPASTIKAN',
            'classification' => 'UNVERIFIED',
            'credibility_score' => 42,
            'threat_level' => 'sedang',
            'confidence' => 75, // dinormalisasi ke 0.75
            'linguistic_analysis' => [
                'emotion' => 'Diksi persuasif ditemukan.',
                'fallacy' => 'Tidak ada indikasi kuat.',
                'source_verification' => 'Belum ada bukti pembanding.',
            ],
            'findings' => [
                ['statement' => 'Bukti pembanding belum tersedia pada kasus ini.', 'type' => 'INTERPRETASI_AI', 'evidence_ids' => []],
            ],
            'evidence' => [],
            'sources' => [],
            'recommendations' => ['Tambahkan sumber rujukan primer.'],
            'limitations' => ['Bukti pembanding belum tersedia.'],
            'tactical_summary' => 'Status awal belum terverifikasi karena keterbatasan bukti primer.',
        ], JSON_UNESCAPED_UNICODE);

        Http::fake([
            'http://192.168.10.50:1234/api/v0/models' => Http::response([
                'data' => [
                    [
                        'id' => 'qwen3-27b-claude-5.5-opus-max-reasoning',
                        'type' => 'llm',
                        'arch' => 'qwen3',
                        'quantization' => 'Q4_K_M',
                        'state' => 'loaded',
                        'max_context_length' => 40960,
                    ],
                ],
            ], 200),
            'http://192.168.10.50:1234/v1/chat/completions' => Http::sequence()
                ->push([
                    'choices' => [[
                        'message' => [
                            'content' => "<think>Analisis reasoning mendalam 27B...</think>\n```json\n{$llmPayload}\n```",
                        ],
                    ]],
                ], 200)
                ->push([
                    'choices' => [[
                        'message' => [
                            'content' => "<think>Proses konsol...</think>Kasus ini belum memiliki bukti primer pembanding.",
                        ],
                    ]],
                ], 200),
        ]);

        // 1. Konfigurasi workstation LAN PUSSIBERAD lewat UI endpoint (AI_REQUIRE_LOCAL mengizinkan IP LAN privat)
        $this->actingAs($admin, 'web')->post('/admin/ai/configure', [
            'driver' => 'openai_compatible',
            'base_url' => 'http://192.168.10.50:1234',
            'model' => 'qwen3-27b-claude-5.5-opus-max-reasoning',
            'timeout' => 300,
            'api_key' => 'secret-intranet-token-123',
        ])->assertRedirect();

        $this->assertDatabaseHas('ai_servers', [
            'driver' => 'openai_compatible',
            'base_url' => 'http://192.168.10.50:1234',
            'status' => 'ONLINE',
            'is_active' => true,
        ]);
        $this->assertDatabaseHas('ai_models', [
            'name' => 'qwen3-27b-claude-5.5-opus-max-reasoning',
            'profile' => 'ADVANCED',
            'quantization' => 'Q4_K_M',
            'is_active' => true,
            'is_simulation' => false,
        ]);

        // Pastikan API Key terenkripsi di DB dan tidak pernah muncul plaintext di audit log
        $encVal = DB::table('app_settings')->where('key', 'ai.api_key_enc')->value('value');
        $this->assertNotEmpty($encVal);
        $this->assertNotSame('secret-intranet-token-123', $encVal);
        $this->assertSame('secret-intranet-token-123', Crypt::decryptString($encVal));
        $audit = SystemLog::where('action', 'AI_SERVER_CONFIGURED')->latest('id')->firstOrFail();
        $this->assertStringNotContainsString('secret-intranet-token-123', json_encode($audit->metadata));

        // 2. Uji real Credibility Scan via driver live (non-simulation)
        $case = InvestigationCase::create([
            'case_code' => 'TS-2026-501',
            'title' => 'Kasus Workstation PUSSIBERAD',
            'claim_text' => 'Klaim pengalihan anggaran total.',
            'priority' => 'TINGGI',
            'status' => 'OPEN',
            'is_simulation' => false,
            'created_by' => $admin->id,
        ]);

        $scan = $this->actingAs($admin, 'web')->postJson('/scanner/scan', [
            'case_id' => $case->id,
            'text' => 'Klaim pengalihan anggaran total.',
        ]);
        $scan->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('threat.driver', 'openai_compatible')
            ->assertJsonPath('threat.is_simulation', false)
            ->assertJsonPath('threat.credibility_score', 42);

        // 3. Uji Console AI chat membersihkan blok <think>...</think>
        $this->actingAs($admin, 'web')->get("/console?case_id={$case->id}")->assertStatus(200);
        $session = \App\Models\ConsoleSession::where('user_id', $admin->id)->firstOrFail();
        $chat = $this->actingAs($admin, 'web')->postJson('/console/message', [
            'session_id' => $session->id,
            'message' => 'Apa status bukti kasus ini?',
        ]);
        $chat->assertStatus(200)
            ->assertJsonPath('status', 'OK')
            ->assertJsonPath('message.is_simulation', false);
        $this->assertStringNotContainsString('<think>', (string) $chat->json('message.content'));
        $this->assertStringContainsString('Kasus ini belum memiliki bukti primer pembanding.', (string) $chat->json('message.content'));
    }

    public function test_local_guard_blocks_public_endpoints_and_rbac_protects_ai_routes(): void
    {
        $operator = $this->makeUser('OPERATOR');
        $admin = $this->makeUser('ADMINISTRATOR');

        // Operator ditolak pada semua endpoint konfigurasi AI
        $this->actingAs($operator, 'web')->post('/admin/ai/configure', ['driver' => 'ollama', 'base_url' => 'http://127.0.0.1:11434'])->assertStatus(403);
        $this->actingAs($operator, 'web')->post('/admin/ai/sync-models')->assertStatus(403);
        $this->actingAs($operator, 'web')->post('/admin/ai/detect-local')->assertStatus(403);

        // Admin ditolak bila memasukkan endpoint cloud publik saat AI_REQUIRE_LOCAL=true
        $resp = $this->actingAs($admin, 'web')->post('/admin/ai/configure', [
            'driver' => 'openai_compatible',
            'base_url' => 'https://api.openai.com/v1',
            'model' => 'gpt-4o',
        ]);
        $resp->assertRedirect();
        $resp->assertSessionHas('error');
        $this->assertNull(DB::table('app_settings')->where('key', 'ai.driver')->value('value'));
    }

    public function test_operational_narrative_logging_rss_ingestion_and_purge_simulation(): void
    {
        $admin = $this->makeUser('ADMINISTRATOR');
        Connector::create(['key' => 'rss', 'name' => 'News RSS Feed Connector', 'priority' => 'P1', 'status' => 'ACTIVE']);
        RssFeed::create(['name' => 'Antara Uji', 'feed_url' => 'https://93.184.216.34/rss.xml', 'is_active' => true]);

        // Seed 1 narrative simulasi
        Narrative::create([
            'title' => 'Data contoh simulasi',
            'region' => 'Jakarta',
            'sentiment' => 'NETRAL',
            'severity' => 'RENDAH',
            'captured_at' => now(),
            'is_simulation' => true,
        ]);

        // 1. Catat narasi operasional nyata via Dashboard form endpoint
        $this->actingAs($admin, 'web')->post('/early-warning/narratives', [
            'title' => 'Laporan hoaks penutupan pelabuhan di Surabaya terdeteksi di kanal publik',
            'region' => 'Surabaya',
            'category' => 'MONITORING_LAPANGAN',
            'severity' => 'AUTO',
            'sentiment' => 'AUTO',
        ])->assertRedirect('/early-warning');

        $this->assertDatabaseHas('narratives', [
            'region' => 'Surabaya',
            'sentiment' => 'NEGATIF',
            'is_simulation' => false,
        ]);

        // 2. Sinkronkan RSS Feed nyata dengan XXE guard
        $rssXml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0"><channel><title>Berita Nasional</title>
<item>
  <title>Klarifikasi resmi kegiatan latihan gabungan berjalan aman di Jakarta</title>
  <link>https://example.org/berita-1</link>
  <description>Kegiatan berjalan lancar dan terkendali sesuai jadwal.</description>
  <guid>news-item-001</guid>
  <pubDate>Thu, 12 Nov 2026 07:00:00 GMT</pubDate>
</item>
</channel></rss>
XML;
        Http::fake(['https://93.184.216.34/rss.xml' => Http::response($rssXml, 200)]);

        $this->actingAs($admin, 'web')->post('/early-warning/sync-rss')->assertRedirect('/early-warning');
        $this->assertDatabaseHas('narratives', [
            'title' => '[Antara Uji] Klarifikasi resmi kegiatan latihan gabungan berjalan aman di Jakarta',
            'region' => 'Jakarta',
            'sentiment' => 'POSITIF',
            'is_simulation' => false,
        ]);

        // XXE payload harus ditolak
        $xxe = '<?xml version="1.0"?><!DOCTYPE foo [<!ENTITY xxe SYSTEM "file:///etc/passwd">]><rss><channel></channel></rss>';
        $feed = RssFeed::firstOrFail();
        $this->expectException(\RuntimeException::class);
        try {
            app(RssIngestionService::class)->ingestXml($feed, $xxe);
        } finally {
            // 3. Bersihkan data simulasi tanpa menghapus narasi operasional maupun user
            $this->actingAs($admin, 'web')->post('/admin/system/purge-simulation')->assertRedirect();
            $this->assertSame(0, Narrative::where('is_simulation', true)->count());
            $this->assertSame(2, Narrative::where('is_simulation', false)->count());
            $this->assertDatabaseHas('users', ['id' => $admin->id]);
        }
    }
}
