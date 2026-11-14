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

    public function test_multi_ai_model_differentiation_and_seamless_adaptive_switching(): void
    {
        $admin = $this->makeUser('ADMINISTRATOR');
        $operator = $this->makeUser('OPERATOR');

        // Operator ditolak memicu start-local-server
        $this->actingAs($operator, 'web')->post('/admin/ai/start-local-server')->assertStatus(403);

        // 1. Verifikasi ModelProfileResolver membedakan tiap karakter model AI lokal
        $resolver = app(\App\Services\Ai\ModelProfileResolver::class);
        $p14b = $resolver->resolve('qwen3-14b-claude-4.5-opus-high-reasoning-distill', ['size_bytes' => 6657108500, 'architecture' => 'qwen3', 'paramsString' => '14B', 'quantization' => 'Q3_K_S']);
        $p05b = $resolver->resolve('qwen2.5-0.5b-instruct', ['size_bytes' => 531068224, 'architecture' => 'qwen2', 'paramsString' => '0.5B', 'quantization' => 'Q8_0']);
        $pCoder = $resolver->resolve('qwen2.5-coder:7b', ['size_bytes' => 4683073536, 'parameters' => '7.6B', 'quantization' => 'Q4_K_M']);
        $pVl = $resolver->resolve('qwen3-vl-8b-instruct', ['vision' => true, 'paramsString' => '8B', 'architecture' => 'qwen3vl']);
        $pEmb = $resolver->resolve('text-embedding-nomic-embed-text-v1.5', ['type' => 'embedding', 'architecture' => 'nomic-bert']);

        $this->assertSame('REASONING', $p14b['capability_type']);
        $this->assertTrue($p14b['is_reasoning']);
        $this->assertSame(240, $p14b['recommended_timeout']);

        $this->assertSame('LIGHTWEIGHT', $p05b['capability_type']);
        $this->assertFalse($p05b['is_reasoning']);
        $this->assertSame(60, $p05b['recommended_timeout']);

        $this->assertSame('CODER_JSON', $pCoder['capability_type']);
        $this->assertSame(0.10, $pCoder['recommended_temperature']);

        $this->assertSame('MULTIMODAL', $pVl['capability_type']);
        $this->assertTrue($pVl['is_vision']);

        $this->assertSame('EMBEDDING', $pEmb['capability_type']);
        $this->assertTrue($pEmb['is_embedding']);

        // 2. Simulasikan 2 engine AI lokal sekaligus (Ollama :11434 + LM Studio :1234 dengan multi-model)
        Http::fake([
            'http://127.0.0.1:11434/api/version' => Http::response(['version' => '0.40.16'], 200),
            'http://127.0.0.1:11434/api/tags' => Http::response([
                'models' => [
                    ['name' => 'qwen2.5-coder:7b', 'size' => 4683073536, 'details' => ['parameter_size' => '7.6B', 'quantization_level' => 'Q4_K_M', 'family' => 'qwen2']],
                ],
            ], 200),
            'http://127.0.0.1:11434/api/ps' => Http::response(['models' => []], 200),
            'http://127.0.0.1:1234/api/v0/models' => Http::response([
                'data' => [
                    ['id' => 'qwen3-14b-claude-4.5-opus-high-reasoning-distill', 'type' => 'llm', 'arch' => 'qwen3', 'quantization' => 'Q3_K_S', 'state' => 'loaded', 'max_context_length' => 40960],
                    ['id' => 'qwen2.5-0.5b-instruct', 'type' => 'llm', 'arch' => 'qwen2', 'quantization' => 'Q8_0', 'state' => 'not-loaded', 'max_context_length' => 32768],
                    ['id' => 'text-embedding-nomic-embed-text-v1.5', 'type' => 'embedding', 'arch' => 'nomic-bert', 'quantization' => 'Q4_K_M', 'state' => 'not-loaded', 'max_context_length' => 2048],
                ],
            ], 200),
            'http://127.0.0.1:8080/*' => Http::response([], 503),
        ]);

        // Konfigurasi awal ke Ollama 7B
        $this->actingAs($admin, 'web')->post('/admin/ai/configure', [
            'driver' => 'ollama',
            'base_url' => 'http://127.0.0.1:11434',
            'model' => 'qwen2.5-coder:7b',
        ])->assertRedirect();

        // Pindai multi-node lewat endpoint detect-local -> harus mendaftarkan KEDUA server & ke-4 model ke DB
        $detect = $this->actingAs($admin, 'web')->postJson('/admin/ai/detect-local');
        $detect->assertStatus(200)->assertJsonPath('ok', true);

        $this->assertDatabaseHas('ai_servers', ['driver' => 'ollama', 'base_url' => 'http://127.0.0.1:11434', 'status' => 'ONLINE']);
        $this->assertDatabaseHas('ai_servers', ['driver' => 'openai_compatible', 'base_url' => 'http://127.0.0.1:1234', 'status' => 'ONLINE']);
        $this->assertDatabaseHas('ai_models', ['name' => 'qwen3-14b-claude-4.5-opus-high-reasoning-distill', 'capability_type' => 'REASONING', 'recommended_timeout' => 240]);
        $this->assertDatabaseHas('ai_models', ['name' => 'qwen2.5-0.5b-instruct', 'capability_type' => 'LIGHTWEIGHT', 'recommended_timeout' => 60]);

        // 3. Alihkan dari Ollama ke LM Studio qwen2.5-0.5b-instruct lewat 1-klik switchModel -> driver, base_url, model & timeout otomatis berubah!
        $tinyModel = AiModel::where('name', 'qwen2.5-0.5b-instruct')->firstOrFail();
        $this->actingAs($admin, 'web')->post('/admin/ai/switch-model', [
            'model_id' => $tinyModel->id,
            'reason' => 'Pengujian switching otomatis ke model ringan 0.5B',
        ])->assertRedirect();

        $mgr = app(AiManager::class);
        $mgr->resetDriver();
        $cfgAfterTiny = $mgr->resolvedConfig();
        $this->assertSame('openai_compatible', $cfgAfterTiny['driver']);
        $this->assertSame('http://127.0.0.1:1234', $cfgAfterTiny['base_url']);
        $this->assertSame('qwen2.5-0.5b-instruct', $cfgAfterTiny['model']);
        $this->assertSame(60, $cfgAfterTiny['timeout']);

        // 4. Alihkan ke qwen3-14b-claude-4.5-opus-high-reasoning-distill -> timeout otomatis naik ke 240s & is_reasoning = true
        $bigModel = AiModel::where('name', 'qwen3-14b-claude-4.5-opus-high-reasoning-distill')->firstOrFail();
        $this->actingAs($admin, 'web')->post('/admin/ai/switch-model', [
            'model_id' => $bigModel->id,
            'reason' => 'Beralih ke model High Reasoning 14B untuk analisis kasus berat',
        ])->assertRedirect();

        $mgr->resetDriver();
        $cfgAfter14b = $mgr->resolvedConfig();
        $this->assertSame('qwen3-14b-claude-4.5-opus-high-reasoning-distill', $cfgAfter14b['model']);
        $this->assertSame(240, $cfgAfter14b['timeout']);
        $this->assertTrue($mgr->activeModelProfile()['is_reasoning']);

        // 5. Pastikan server Ollama (:11434) beserta modelnya tetap tersimpan dan tidak hilang
        $this->assertDatabaseHas('ai_models', ['name' => 'qwen2.5-coder:7b', 'capability_type' => 'CODER_JSON']);

        // 6. Setel model embedding tanpa menimpa model LLM aktif
        $embModel = AiModel::where('name', 'text-embedding-nomic-embed-text-v1.5')->firstOrFail();
        $this->actingAs($admin, 'web')->post('/admin/ai/switch-model', [
            'model_id' => $embModel->id,
            'reason' => 'Menetapkan model vektor embedding lokal',
        ])->assertRedirect();

        $mgr->resetDriver();
        $cfgAfterEmbed = $mgr->resolvedConfig();
        $this->assertSame('qwen3-14b-claude-4.5-opus-high-reasoning-distill', $cfgAfterEmbed['model']);
        $this->assertSame('text-embedding-nomic-embed-text-v1.5', $cfgAfterEmbed['embedding_model']);
    }

    public function test_open_weights_bilingual_enum_normalization_strips_fictional_evidence_without_fabricating(): void
    {
        $admin = $this->makeUser('ADMINISTRATOR');

        $noisyLlmJson = json_encode([
            'module' => 'credibility_scan',
            'summary' => 'Klaim tarif PPN sembako bertentangan dengan bukti resmi Kemenkeu.',
            'is_simulation' => false,
            'claims' => [
                ['text' => 'Beras dikenakan PPN 12%.', 'type' => 'FACT', 'check_status' => 'REFUTED'],
            ],
            'verification_status' => 'FALSE',
            'content_indicator' => 'HOAX',
            'classification' => 'HOAX',
            'credibility_score' => 15,
            'threat_level' => 'HIGH',
            'confidence' => 0.85,
            'linguistic_analysis' => [
                'emotion' => 'Nada mendesak.',
                'fallacy' => 'Generalisasi.',
                'source_verification' => 'Bertentangan dengan bukti primer.',
            ],
            'findings' => [
                ['statement' => 'Kemenkeu memastikan beras bebas PPN.', 'type' => 'FACT', 'evidence_ids' => ['e-001', 'E-999-FIKTIF']],
                ['statement' => 'Perlu edukasi publik lebih lanjut.', 'type' => 'FACT', 'evidence_ids' => ['E-888-FIKTIF']],
            ],
            'evidence' => [
                ['id' => 'E-001', 'excerpt' => 'Beras dan kebutuhan pokok dibebaskan dari PPN.'],
                ['id' => 'E-999-FIKTIF', 'excerpt' => 'Kutipan fiktif dari model.'],
            ],
            'sources' => [
                ['id' => 'S-1', 'access_status' => 'AVAILABLE'],
            ],
            'recommendations' => ['Publikasikan klarifikasi Kemenkeu.'],
            'limitations' => ['Analisis berbasis satu dokumen primer.'],
            'tactical_summary' => 'Narasi terbantahkan oleh dokumen UU HPP (E-001).',
        ], JSON_UNESCAPED_UNICODE);

        Http::fake([
            'http://127.0.0.1:1234/api/v0/models' => Http::response([
                'data' => [
                    ['id' => 'qwen2.5-coder-1.5b-instruct', 'type' => 'llm', 'state' => 'loaded', 'max_context_length' => 32768],
                ],
            ], 200),
            'http://127.0.0.1:1234/v1/chat/completions' => Http::response([
                'choices' => [['message' => ['content' => $noisyLlmJson]]],
            ], 200),
        ]);

        $this->actingAs($admin, 'web')->post('/admin/ai/configure', [
            'driver' => 'openai_compatible',
            'base_url' => 'http://127.0.0.1:1234',
            'model' => 'qwen2.5-coder-1.5b-instruct',
        ])->assertRedirect();

        $case = InvestigationCase::create([
            'case_code' => 'TS-2026-599',
            'title' => 'Kasus Uji Normalisasi Enum',
            'claim_text' => 'Beras dikenakan PPN 12%.',
            'priority' => 'TINGGI',
            'status' => 'OPEN',
            'is_simulation' => false,
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin, 'web')->post("/cases/{$case->id}/evidence/import", [
            'title' => 'Siaran Resmi Kemenkeu',
            'publisher' => 'Kemenkeu RI',
            'platform' => 'PORTAL_RESMI',
            'role' => 'REFERENCE',
            'type' => 'DOCUMENT',
            'extracted_text' => 'Beras dan bahan pokok dibebaskan dari pengenaan PPN.',
        ])->assertRedirect();

        $scan = $this->actingAs($admin, 'web')->postJson('/scanner/scan', [
            'case_id' => $case->id,
            'text' => 'Beras dikenakan PPN 12%.',
        ]);

        $scan->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('threat.credibility_score', 15)
            ->assertJsonPath('threat.verification_status', 'TERBANTAHKAN')
            ->assertJsonPath('threat.classification', 'DISINFORMATION')
            ->assertJsonPath('threat.threat_level', 'TINGGI');

        // Evidence fiktif terbuang & temuan tanpa bukti sah diturunkan jujur ke INTERPRETASI_AI
        $result = $scan->json('analysis');
        $this->assertCount(1, $result['evidence']);
        $this->assertSame('E-001', $result['evidence'][0]['id']);
        $this->assertSame(['E-001'], $result['findings'][0]['evidence_ids']);
        $this->assertSame('FAKTA', $result['findings'][0]['type']);
        $this->assertSame([], $result['findings'][1]['evidence_ids']);
        $this->assertSame('INTERPRETASI_AI', $result['findings'][1]['type']);
    }

    public function test_hybrid_cloud_ai_google_studio_exa_and_auto_failover_to_local_ai(): void
    {
        $admin = $this->makeUser('ADMINISTRATOR');

        $cloudPayload = json_encode([
            'module' => 'credibility_scan',
            'summary' => 'Analisis Cloud Google AI Studio: klaim terbukti valid sesuai rujukan resmi.',
            'is_simulation' => false,
            'claims' => [['id' => 'c1', 'text' => 'Proyek infrastruktur berjalan sesuai target.', 'type' => 'FAKTA', 'check_status' => 'TERVERIFIKASI']],
            'verification_status' => 'TERVERIFIKASI',
            'content_indicator' => 'ASLI',
            'classification' => 'VERIFIED',
            'credibility_score' => 88,
            'threat_level' => 'RENDAH',
            'confidence' => 0.91,
            'linguistic_analysis' => ['emotion' => 'Netral', 'fallacy' => 'Tidak ada', 'source_verification' => 'Selaras'],
            'findings' => [['statement' => 'Analisis via Google AI Studio', 'type' => 'INTERPRETASI_AI', 'evidence_ids' => []]],
            'evidence' => [],
            'sources' => [],
            'recommendations' => ['Pertahankan monitoring'],
            'limitations' => ['Verifikasi periodik'],
            'tactical_summary' => 'Google AI Studio mengonfirmasi narasi sejalan dengan sumber publik.',
        ], JSON_UNESCAPED_UNICODE);

        $localFallbackPayload = json_encode([
            'module' => 'credibility_scan',
            'summary' => 'Fallback Lokal AI: klaim belum terverifikasi dan perlu audit lanjutan.',
            'is_simulation' => false,
            'claims' => [['id' => 'c1', 'text' => 'Klaim lanjutan saat jaringan putus.', 'type' => 'FAKTA', 'check_status' => 'BELUM TERVERIFIKASI']],
            'verification_status' => 'BELUM TERVERIFIKASI',
            'content_indicator' => 'BELUM DAPAT DIPASTIKAN',
            'classification' => 'UNVERIFIED',
            'credibility_score' => 45,
            'threat_level' => 'SEDANG',
            'confidence' => 0.62,
            'linguistic_analysis' => ['emotion' => 'Netral', 'fallacy' => 'Tidak ada', 'source_verification' => 'Mode Offline Lokal'],
            'findings' => [['statement' => 'Diproses oleh AI Lokal setelah failover jaringan.', 'type' => 'INTERPRETASI_AI', 'evidence_ids' => []]],
            'evidence' => [],
            'sources' => [],
            'recommendations' => ['Verifikasi ulang saat sinyal pulih'],
            'limitations' => ['Mode offline failover lokal'],
            'tactical_summary' => 'Sistem beralih mulus ke AI Lokal (qwen2.5-0.5b-instruct) saat Cloud API terputus.',
        ], JSON_UNESCAPED_UNICODE);

        Http::fake([
            'http://127.0.0.1:1234/api/v0/models' => Http::response([
                'data' => [['id' => 'qwen2.5-0.5b-instruct', 'type' => 'llm', 'state' => 'loaded', 'max_context_length' => 8192]],
            ], 200),
            'generativelanguage.googleapis.com/*' => Http::sequence()
                ->push(['models' => [['name' => 'models/gemini-2.0-flash']]], 200) // health check
                ->push(['candidates' => [['content' => ['parts' => [['text' => $cloudPayload]]]]]], 200) // cloud primary success
                ->push(['error' => ['message' => 'Service temporarily unavailable']], 503), // cloud outage -> auto-failover!
            'http://127.0.0.1:1234/v1/chat/completions' => Http::response([
                'choices' => [['message' => ['content' => $localFallbackPayload]]],
            ], 200),
        ]);

        // 1. Konfigurasi engine lokal + Hybrid Cloud API (Google AI Studio + Exa.ai)
        $this->actingAs($admin, 'web')->post('/admin/ai/configure', [
            'driver' => 'openai_compatible',
            'base_url' => 'http://127.0.0.1:1234',
            'model' => 'qwen2.5-0.5b-instruct',
        ])->assertRedirect();

        $this->actingAs($admin, 'web')->post('/admin/ai/configure-cloud', [
            'engine_mode' => 'HYBRID_AUTO',
            'cloud_provider' => 'google_ai_studio',
            'cloud_timeout' => 8,
            'google_model' => 'gemini-2.0-flash',
            'google_api_key' => 'AIzaSyTestSecretKey999',
            'exa_model' => 'exa',
            'exa_api_key' => 'exa-secret-token-777',
            'exa_search_enabled' => '1',
        ])->assertRedirect();

        // API key tersimpan terenkripsi dan tidak pernah muncul plaintext di log
        $settingsSvc = app(\App\Services\Support\AppSettings::class);
        $encGoogle = DB::table('app_settings')->where('key', $settingsSvc->orgKey('ai.google_api_key_enc', $admin->organization_id))->value('value');
        $encExa = DB::table('app_settings')->where('key', $settingsSvc->orgKey('ai.exa_api_key_enc', $admin->organization_id))->value('value');
        $this->assertSame('AIzaSyTestSecretKey999', Crypt::decryptString($encGoogle));
        $this->assertSame('exa-secret-token-777', Crypt::decryptString($encExa));

        // 2. Uji endpoint /admin/ai/test-cloud
        $this->actingAs($admin, 'web')->postJson('/admin/ai/test-cloud')
            ->assertStatus(200)
            ->assertJsonPath('ok', true)
            ->assertJsonPath('local_fallback.status', 'ONLINE');

        /** @var AiManager $mgr */
        $mgr = app(AiManager::class);
        $mgr->resetDriver();

        // 3. Panggilan #1: Cloud AI Studio sukses (CLOUD_PRIMARY)
        $resCloud = $mgr->analyze('credibility_scan', ['text' => 'Proyek infrastruktur berjalan sesuai target.', 'evidence' => [], 'case' => null, 'flags' => []], []);
        $this->assertSame('CLOUD_PRIMARY', $resCloud['execution_tier']);
        $this->assertSame('google_ai_studio', $resCloud['driver']);
        $this->assertFalse($resCloud['failover_triggered']);
        $this->assertSame(88, $resCloud['result']['credibility_score']);

        // 4. Panggilan #2: Google AI Studio mengalami HTTP 503 / putus jaringan -> Auto-Failover ke qwen2.5-0.5b-instruct!
        $resFailover = $mgr->analyze('credibility_scan', ['text' => 'Klaim lanjutan saat jaringan putus.', 'evidence' => [], 'case' => null, 'flags' => []], []);
        $this->assertSame('LOCAL_FAILOVER', $resFailover['execution_tier']);
        $this->assertTrue($resFailover['failover_triggered']);
        $this->assertSame('openai_compatible', $resFailover['driver']);
        $this->assertSame('qwen2.5-0.5b-instruct', $resFailover['model_name']);
        $this->assertSame(45, $resFailover['result']['credibility_score']);

        // Audit trail mencatat AI_CLOUD_FAILOVER tanpa membocorkan kunci
        $failLog = SystemLog::where('action', 'AI_CLOUD_FAILOVER')->latest('id')->first();
        $this->assertNotNull($failLog);
        $this->assertStringNotContainsString('AIzaSyTestSecretKey999', json_encode($failLog->metadata));
    }
}
