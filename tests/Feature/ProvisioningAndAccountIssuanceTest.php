<?php

namespace Tests\Feature;

use App\Models\SystemLog;
use App\Models\User;
use App\Services\Identity\AccountProvisioner;
use App\Services\Identity\RegistryStore;
use App\Services\Identity\RegistryValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\CreatesTraceFixtures;
use Tests\TestCase;

class ProvisioningAndAccountIssuanceTest extends TestCase
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

    public function test_public_self_registration_and_platform_portal_do_not_exist(): void
    {
        foreach (['/register', '/status', '/platform', '/platform/login', '/platform/registrations'] as $uri) {
            $this->get($uri)->assertStatus(404);
        }

        $this->post('/register', ['full_name' => 'Unauthorized'])->assertStatus(404);
        $this->post('/status', ['request_reference' => 'REQ-1'])->assertStatus(404);
    }

    public function test_developer_cli_issue_account_validates_writes_registry_provisions_db_and_creates_private_credential(): void
    {
        $store = app(RegistryStore::class);

        // 1. Dry-run tidak menulis registry atau DB
        $this->artisan('trace:issue-account', [
            'full_name' => 'Mayor Inf Arisandi',
            'organization' => 'PUSSIBERAD TNI AD',
            '--unit' => 'Subdit Analisis Kognitif',
            '--position' => 'Kepala Tim',
            '--role' => 'ADMINISTRATOR',
            '--approved-by' => 'DevLead-01',
            '--notes' => 'Terverifikasi via surat otorisasi eksternal.',
            '--dry-run' => true,
        ])->assertExitCode(0);

        $this->assertEmpty($store->approved());
        $this->assertSame(0, User::where('name', 'Mayor Inf Arisandi')->count());

        // 2. Eksekusi nyata mencatat ke registry eksternal + DB PROVISIONED + berkas credential privat
        Artisan::call('trace:issue-account', [
            'full_name' => 'Mayor Inf Arisandi',
            'organization' => 'PUSSIBERAD TNI AD',
            '--unit' => 'Subdit Analisis Kognitif',
            '--position' => 'Kepala Tim',
            '--role' => 'ADMINISTRATOR',
            '--approved-by' => 'DevLead-01',
            '--notes' => 'Terverifikasi via surat otorisasi eksternal.',
        ]);
        $cliOut = Artisan::output();

        $entries = $store->approved();
        $this->assertCount(1, $entries);
        $traceId = $entries[0]['trace_id'];
        $this->assertMatchesRegularExpression('/^TRS-[A-Z0-9]{4}-[A-Z0-9]{4}$/', $traceId);
        $this->assertEquals('ADMINISTRATOR', $entries[0]['role']);
        $this->assertArrayNotHasKey('password', $entries[0]);
        $this->assertArrayNotHasKey('password_hash', $entries[0]);

        $user = User::where('trace_id', $traceId)->firstOrFail();
        $this->assertTrue($user->isAdministrator());
        $this->assertEquals('PROVISIONED', $user->account_status);
        $this->assertTrue($user->must_change_password);

        $credFile = $store->issuedDir() . DIRECTORY_SEPARATOR . $traceId . '.credential.txt';
        $this->assertFileExists($credFile);
        $credContents = file_get_contents($credFile);
        $this->assertMatchesRegularExpression('/Kata sandi sementara\s*:\s*(\S+)/', $credContents);
        preg_match('/Kata sandi sementara\s*:\s*(\S+)/', $credContents, $matches);
        $tempPassword = $matches[1];
        $this->assertGreaterThanOrEqual(16, strlen($tempPassword));
        $this->assertTrue(Hash::check($tempPassword, $user->password));
        $this->assertStringNotContainsString($tempPassword, $cliOut, 'Kata sandi sementara tidak boleh dicetak ke output terminal.');

        // 3. Validasi CLI sukses pada registry yang baru diterbitkan
        $this->artisan('trace:validate-accounts')->assertExitCode(0);

        // 4. Provisioning ulang bersifat idempoten
        Artisan::call('trace:provision-accounts');
        $this->assertStringContainsString(AccountProvisioner::EXISTS, Artisan::output());
        $this->assertSame(1, User::where('trace_id', $traceId)->count());
    }

    public function test_registry_validator_accepts_canonical_and_legacy_role_fields_and_rejects_malformed_entries(): void
    {
        $validator = app(RegistryValidator::class);

        $validErrors = $validator->validate([
            [
                'trace_id' => 'TRS-11AA-22BB',
                'full_name' => 'Operator Kanonik',
                'organization' => 'Direktorat Uji Siber',
                'role' => 'OPERATOR',
                'approval_status' => 'APPROVED',
                'account_status' => 'PROVISIONED',
                'approved_by' => 'DevLead-01',
                'approved_at' => now()->toIso8601String(),
            ],
            [
                'trace_id' => 'TRS-33CC-44DD',
                'full_name' => 'Admin Kompatibel',
                'organization' => 'Direktorat Uji Siber',
                'approved_role' => 'ADMINISTRATOR',
                'approval_status' => 'APPROVED',
                'account_status' => 'PROVISIONED',
                'approved_by' => 'DevLead-01',
                'approved_at' => now()->toIso8601String(),
            ],
        ]);
        $this->assertEmpty($validErrors);

        $badErrors = $validator->validate([
            [
                'trace_id' => 'INVALID-FORMAT',
                'full_name' => 'A',
                'organization' => 'Org',
                'role' => 'SUPERADMIN',
                'approved_role' => 'OPERATOR',
                'approval_status' => 'APPROVED',
                'account_status' => 'PROVISIONED',
                'approved_by' => '',
                'approved_at' => 'not-a-date',
                'password' => 'leaked-secret',
            ],
            [
                'trace_id' => 'TRS-AB12-CD34',
                'full_name' => 'Rejected Candidate',
                'organization' => 'Org Test',
                'role' => 'OPERATOR',
                'approval_status' => 'REJECTED',
                'account_status' => 'ACTIVE',
                'approved_by' => null,
                'approved_at' => null,
            ],
        ]);

        $this->assertCount(2, $badErrors);
        $joined = json_encode($badErrors, JSON_UNESCAPED_SLASHES);
        $this->assertStringContainsString('Properti tidak dikenal: password', $joined);
        $this->assertStringContainsString('approved_by wajib diisi', $joined);
        $this->assertStringContainsString('Entri REJECTED tidak boleh berstatus PROVISIONED/ACTIVE', $joined);
    }

    public function test_cli_provision_accounts_dry_run_and_batch_import_work_with_validate_accounts(): void
    {
        $store = app(RegistryStore::class);
        $store->append($store->approvedFile(), [
            'trace_id' => 'TRS-K7M2-P9Q4',
            'full_name' => 'Dina Marlina',
            'organization' => 'Direktorat Uji Siber',
            'unit' => 'Subdit Validasi',
            'position' => 'Administrator',
            'role' => 'ADMINISTRATOR',
            'approval_status' => 'APPROVED',
            'account_status' => 'PROVISIONED',
            'approved_by' => 'dev-authority',
            'approved_at' => now()->toIso8601String(),
            'provisioned_at' => null,
            'expires_at' => null,
        ]);

        $this->artisan('trace:validate-accounts')->assertExitCode(0);
        $this->artisan('trace:provision-accounts --dry-run')->assertExitCode(0);
        $this->assertDatabaseMissing('users', ['trace_id' => 'TRS-K7M2-P9Q4']);

        $this->artisan('trace:provision-accounts')->assertExitCode(0);
        $user = User::where('trace_id', 'TRS-K7M2-P9Q4')->firstOrFail();
        $this->assertTrue($user->isAdministrator());
        $this->assertEquals('PROVISIONED', $user->account_status);

        $credFile = $store->issuedDir() . DIRECTORY_SEPARATOR . 'TRS-K7M2-P9Q4.credential.txt';
        $this->assertFileExists($credFile);
        $this->assertStringContainsString('TRS-K7M2-P9Q4', file_get_contents($credFile));
        $this->assertNotNull(SystemLog::where('action', 'TRACE_ID_PROVISIONED')->first());
    }
}
