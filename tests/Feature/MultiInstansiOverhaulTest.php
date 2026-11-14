<?php

namespace Tests\Feature;

use App\Models\InvestigationCase;
use App\Models\Narrative;
use App\Models\Organization;
use App\Models\Report;
use App\Models\SystemLog;
use App\Models\User;
use App\Services\Ai\AiManager;
use App\Services\Identity\RegistryStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\Concerns\CreatesTraceFixtures;
use Tests\TestCase;

class MultiInstansiOverhaulTest extends TestCase
{
    use CreatesTraceFixtures;
    use RefreshDatabase;

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

    public function test_developer_issue_account_with_kode_instansi_and_forced_first_login_password_change(): void
    {
        $store = app(RegistryStore::class);

        $exitCode = Artisan::call('trace:issue-account', [
            'full_name' => 'Kapten Analis Satu',
            'organization' => 'Instansi Alpha',
            '--code' => 'INST-A#01',
            '--role' => 'OPERATOR',
            '--position' => 'Analis OSINT',
            '--approved-by' => 'DevLead-Verify',
        ]);

        $this->assertSame(0, $exitCode);

        $user = User::with('organization')->where('name', 'Kapten Analis Satu')->firstOrFail();
        $this->assertSame('INST-A#01', $user->organization?->code);
        $this->assertSame('Instansi Alpha', $user->organization?->name);
        $this->assertTrue((bool) $user->must_change_password);
        $this->assertSame('PROVISIONED', $user->account_status);

        $credFile = $store->issuedDir() . DIRECTORY_SEPARATOR . $user->trace_id . '.credential.txt';
        $this->assertFileExists($credFile);
        $contents = (string) file_get_contents($credFile);
        $this->assertStringContainsString('INST-A#01', $contents);
        $this->assertMatchesRegularExpression('/Kata sandi sementara\s*:\s*(\S+)/', $contents);
        preg_match('/Kata sandi sementara\s*:\s*(\S+)/', $contents, $matches);
        $tempPassword = $matches[1];

        // First login records device_code and redirects to forced password change
        $loginResp = $this->withHeaders(['User-Agent' => 'TraceS-WinClient/2.0'])
            ->post('/login', [
                'trace_id' => $user->trace_id,
                'password' => $tempPassword,
            ]);

        $loginResp->assertRedirect(route('password.change'));

        $user->refresh();
        $this->assertNotNull($user->last_device_code);
        $this->assertStringStartsWith('DEV-', $user->last_device_code);

        $loginLog = SystemLog::where('user_id', $user->id)->where('action', 'LOGIN_SUCCESS')->latest('id')->first();
        $this->assertNotNull($loginLog);
        $this->assertSame('INST-A#01', $loginLog->metadata['organization_code'] ?? null);
        $this->assertSame($user->last_device_code, $loginLog->metadata['device_code'] ?? null);
    }

    public function test_multi_instansi_group_isolation_and_shared_intra_agency_access(): void
    {
        $orgA = Organization::firstOrCreate(['name' => 'Instansi Alpha'], ['code' => 'INST-A#01', 'status' => 'ACTIVE']);
        $orgB = Organization::firstOrCreate(['name' => 'Instansi Beta'], ['code' => 'INST-B#02', 'status' => 'ACTIVE']);

        $adminA = $this->makeUser('ADMINISTRATOR', $orgA);
        $opA = $this->makeUser('OPERATOR', $orgA);
        $adminB = $this->makeUser('ADMINISTRATOR', $orgB);
        $opB = $this->makeUser('OPERATOR', $orgB);

        // Operator A creates a case, narrative & report belonging to INST-A#01
        $caseA = InvestigationCase::create([
            'case_code' => 'TS-2026-7701',
            'title' => 'Kasus Rahasia Instansi Alpha',
            'claim_text' => 'Klaim terisolasi milik Alpha.',
            'category' => 'OSINT',
            'priority' => 'TINGGI',
            'status' => 'OPEN',
            'is_simulation' => false,
            'organization_id' => $orgA->id,
            'created_by' => $opA->id,
        ]);

        Narrative::create([
            'title' => 'Narasi Eksklusif Alpha',
            'category' => 'DISINFORMASI',
            'region' => 'Jakarta',
            'sentiment' => 'NEGATIF',
            'severity' => 'TINGGI',
            'organization_id' => $orgA->id,
            'captured_at' => now(),
            'is_simulation' => false,
        ]);

        $reportA = Report::create([
            'report_code' => 'RPT-2026-7701',
            'case_id' => $caseA->id,
            'generated_by' => $opA->id,
            'title' => 'Intel Brief Alpha Only',
            'content' => ['section_2_executive_summary' => ['summary' => 'Intel Brief Alpha Only — Rahasia grup INST-A#01']],
            'is_simulation' => false,
        ]);

        // Intra-agency group sharing: Admin A (different user, same Kode Instansi) can see Case A, Narrative A & Report A
        $this->actingAs($adminA, 'web')->get("/cases/{$caseA->id}")->assertStatus(200)->assertSee('Kasus Rahasia Instansi Alpha');
        $this->actingAs($adminA, 'web')->get("/reports/{$reportA->id}")->assertStatus(200)->assertSee('Intel Brief Alpha Only');
        $this->actingAs($adminA, 'web')->get('/early-warning')->assertStatus(200)->assertSee('Narasi Eksklusif Alpha');

        // Cross-agency isolation: Beta users (admin & operator) are blocked (404) & cannot view Alpha data
        $this->actingAs($adminB, 'web')->get("/cases/{$caseA->id}")->assertStatus(404);
        $this->actingAs($opB, 'web')->get("/cases/{$caseA->id}")->assertStatus(404);
        $this->actingAs($adminB, 'web')->get("/reports/{$reportA->id}")->assertStatus(404);
        $this->actingAs($adminB, 'web')->get('/cases')->assertStatus(200)->assertDontSee('Kasus Rahasia Instansi Alpha');
        $this->actingAs($opB, 'web')->get('/early-warning')->assertStatus(200)->assertDontSee('Narasi Eksklusif Alpha');
    }

    public function test_cloud_ai_keys_are_shared_per_instansi_and_isolated_between_agencies(): void
    {
        $orgA = Organization::firstOrCreate(['name' => 'Instansi Alpha'], ['code' => 'INST-A#01', 'status' => 'ACTIVE']);
        $orgB = Organization::firstOrCreate(['name' => 'Instansi Beta'], ['code' => 'INST-B#02', 'status' => 'ACTIVE']);

        $adminA = $this->makeUser('ADMINISTRATOR', $orgA);
        $opA = $this->makeUser('OPERATOR', $orgA);
        $opB = $this->makeUser('OPERATOR', $orgB);

        // Operator A configures Cloud AI for INST-A#01 via /scanner/cloud-ai
        $this->actingAs($opA, 'web')
            ->post('/scanner/cloud-ai', [
                'engine_mode' => 'HYBRID_AUTO',
                'cloud_provider' => 'google_ai_studio',
                'cloud_timeout' => 14,
                'google_model' => 'gemini-2.0-flash',
                'google_api_key' => 'AIzaAlphaGroupSecret9988',
            ])
            ->assertRedirect();

        $ai = app(AiManager::class);
        $cfgA = $ai->resolvedConfig($orgA->id);
        $cfgB = $ai->resolvedConfig($orgB->id);

        $this->assertTrue((bool) $cfgA['has_google_key']);
        $this->assertSame(14, $cfgA['cloud_timeout']);
        $this->assertFalse((bool) $cfgB['has_google_key']);

        // Admin A sees the shared API key badge from Operator A on /admin/ai, while Operator B still gets 403 on /admin/ai
        $this->actingAs($adminA, 'web')->get('/admin/ai')->assertStatus(200)->assertSee('GEMINI KEY SAVED');
        $this->actingAs($opB, 'web')->get('/admin/ai')->assertStatus(403);
        $this->actingAs($opB, 'web')->get('/scanner')->assertStatus(200)->assertDontSee('GEMINI: AIza');
    }

    public function test_live_telemetry_endpoint_and_zero_simulation_ui_strings(): void
    {
        $orgA = Organization::firstOrCreate(['name' => 'Instansi Gamma'], ['code' => 'INST-G#77', 'status' => 'ACTIVE']);
        $admin = $this->makeUser('ADMINISTRATOR', $orgA);

        $telemetryResp = $this->actingAs($admin, 'web')->getJson('/api/telemetry/live');
        $telemetryResp->assertStatus(200)
            ->assertJsonPath('ok', true)
            ->assertJsonStructure([
                'ok',
                'timestamp',
                'telemetry' => ['available', 'os', 'hostname', 'gpus'],
                'ai_status' => ['driver', 'status', 'engine_mode'],
            ]);

        foreach (['/admin/dashboard', '/early-warning', '/scanner', '/console'] as $uri) {
            $resp = $this->actingAs($admin, 'web')->get($uri);
            $resp->assertStatus(200)
                ->assertSee('INST-G#77')
                ->assertDontSee('SIMULASI')
                ->assertDontSee('SIMULATION');
        }
    }
}
