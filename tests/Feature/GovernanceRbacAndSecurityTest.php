<?php

namespace Tests\Feature;

use App\Models\ConsoleSession;
use App\Models\InformationRecord;
use App\Models\Skill;
use App\Models\SystemLog;
use App\Services\DataAcquisition\EvidenceCollectorService;
use App\Services\Skills\SkillService;
use App\Services\Support\AuditLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;
use Tests\Concerns\CreatesTraceFixtures;
use Tests\TestCase;

class GovernanceRbacAndSecurityTest extends TestCase
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

    /** §19-D: Isolasi lintas organisasi & batas kewenangan Administrator */
    public function test_cross_organization_isolation_and_administrator_boundaries(): void
    {
        $orgA = $this->makeOrg('Organisasi Alpha');
        $orgB = $this->makeOrg('Organisasi Beta');

        $adminA1 = $this->makeUser('ADMINISTRATOR', $orgA);
        $adminA2 = $this->makeUser('ADMINISTRATOR', $orgA);
        $opA = $this->makeUser('OPERATOR', $orgA);
        $opB = $this->makeUser('OPERATOR', $orgB);

        // Admin Alpha tidak dapat melihat atau mengubah Operator Beta (404 anti-enumerasi)
        $this->actingAs($adminA1, 'web')->get("/admin/accounts/{$opB->trace_id}")->assertStatus(404);
        $this->actingAs($adminA1, 'web')->post("/admin/accounts/{$opB->trace_id}", [
            'action' => 'suspend',
            'reason' => 'Coba lintas org',
        ])->assertStatus(404);

        // Admin tidak dapat mengubah akun dirinya sendiri atau sesama Administrator
        $this->actingAs($adminA1, 'web')->post("/admin/accounts/{$adminA1->trace_id}", [
            'action' => 'suspend',
            'reason' => 'Self test',
        ])->assertSessionHas('error');

        $this->actingAs($adminA1, 'web')->post("/admin/accounts/{$adminA2->trace_id}", [
            'action' => 'suspend',
            'reason' => 'Peer admin test',
        ])->assertSessionHas('error');
        $this->assertEquals('ACTIVE', $adminA2->refresh()->account_status);

        // Rute registrasi dalam aplikasi sudah dihapus (404); Admin tetap mengelola akun org sendiri di /admin/accounts
        $this->actingAs($adminA1, 'web')->get('/admin/registrations')->assertStatus(404);
        $this->actingAs($adminA1, 'web')->get('/admin/dashboard')->assertStatus(200);
        $this->actingAs($adminA1, 'web')->get('/admin/accounts')->assertStatus(200);
        $this->actingAs($adminA1, 'web')->post("/admin/accounts/{$opA->trace_id}", [
            'action' => 'suspend',
            'reason' => 'Rotasi penugasan.',
        ])->assertSessionHas('success');
        $this->assertEquals('SUSPENDED', $opA->refresh()->account_status);
    }

    /** §19-D & §19-E: Alur Informasi, Pemisahan Tugas, Versi Imutabel, dan Retensi Arsip */
    public function test_information_workflow_separation_of_duties_versions_and_retention(): void
    {
        $org = $this->makeOrg('Organisasi Alpha');
        $op1 = $this->makeUser('OPERATOR', $org);
        $op2 = $this->makeUser('OPERATOR', $org);
        $admin = $this->makeUser('ADMINISTRATOR', $org);

        // 1. Operator 1 membuat & mengajukan draf
        $this->actingAs($op1, 'web')->post('/records', [
            'title' => 'Laporan Observasi Narasi Publik',
            'description' => 'Catatan awal narasi regional.',
            'category' => 'narrative_observation',
            'content' => 'Konten awal observasi narasi mengenai distribusi logistik.',
            'submit_now' => '1',
        ])->assertRedirect();

        $record = InformationRecord::firstOrFail();
        $this->assertEquals('SUBMITTED', $record->status);
        $this->assertSame(1, $record->current_version);

        // Operator 2 tidak dapat melihat pengajuan yang belum disetujui (404) dan tidak bisa review (403)
        $this->actingAs($op2, 'web')->get("/records/{$record->record_code}")->assertStatus(404);
        $this->actingAs($op2, 'web')->post("/records/{$record->record_code}/review", [
            'decision' => 'APPROVED',
        ])->assertStatus(403);

        // Admin tidak dapat menyetujui pengajuan buatan sendiri (separation of duties)
        $this->actingAs($admin, 'web')->post('/records', [
            'title' => 'Draf Milik Admin',
            'category' => 'general',
            'content' => 'Isi draf yang dibuat admin untuk menguji pemisahan tugas.',
            'submit_now' => '1',
        ]);
        $adminRecord = InformationRecord::where('created_by', $admin->id)->firstOrFail();
        $this->actingAs($admin, 'web')->post("/records/{$adminRecord->record_code}/review", [
            'decision' => 'APPROVED',
        ])->assertSessionHas('error');
        $this->assertEquals('SUBMITTED', $adminRecord->refresh()->status);

        // 2. Permintaan revisi tanpa alasan ditolak oleh validasi/service
        $this->actingAs($admin, 'web')->post("/records/{$record->record_code}/review", [
            'decision' => 'REVISION REQUIRED',
            'comment' => '',
        ])->assertSessionHas('error');

        $this->actingAs($admin, 'web')->post("/records/{$record->record_code}/review", [
            'decision' => 'REVISION REQUIRED',
            'comment' => 'Tambahkan sumber primer terverifikasi.',
        ])->assertSessionHas('success');
        $this->assertEquals('REVISION REQUIRED', $record->refresh()->status);

        // 3. Operator 1 merevisi -> menghasilkan versi 2, versi 1 tetap utuh
        $this->actingAs($op1, 'web')->put("/records/{$record->record_code}", [
            'title' => 'Laporan Observasi Narasi Publik (Revisi)',
            'description' => 'Versi diperkaya rujukan primer.',
            'category' => 'narrative_observation',
            'content' => 'Konten revisi observasi narasi dengan bukti primer yang terverifikasi.',
            'change_reason' => 'Menambahkan rujukan primer',
        ])->assertRedirect("/records/{$record->record_code}");

        $this->assertSame(2, $record->versions()->count());
        $this->actingAs($op1, 'web')->post("/records/{$record->record_code}/submit")->assertSessionHas('success');

        // 4. Admin menyetujui versi 2 -> Operator 2 sekarang boleh melihat versi disetujui
        $this->actingAs($admin, 'web')->post("/records/{$record->record_code}/review", [
            'decision' => 'APPROVED',
            'comment' => 'Disetujui untuk rujukan internal.',
        ])->assertSessionHas('success');
        $this->assertEquals(2, $record->refresh()->approved_version);

        $this->actingAs($op2, 'web')->get("/records/{$record->record_code}")->assertStatus(200);

        // 5. Pengarsipan & retensi: hapus lunak ditolak sebelum masa retensi 90 hari terpenuhi
        $this->actingAs($admin, 'web')->post("/records/{$record->record_code}/archive", [
            'reason' => 'Arsip pasca evaluasi.',
        ])->assertSessionHas('success');

        $this->actingAs($op1, 'web')->delete("/records/{$record->record_code}", [
            'reason' => 'Unauthorized',
        ])->assertStatus(403);

        $this->actingAs($admin, 'web')->delete("/records/{$record->record_code}", [
            'reason' => 'Uji retensi dini',
        ])->assertSessionHas('error');
        $this->assertNull($record->refresh()->deleted_at);

        // Simulasikan arsip sudah berumur 95 hari -> soft delete berhasil
        $record->update(['archived_at' => now()->subDays(95)]);
        $this->actingAs($admin, 'web')->delete("/records/{$record->record_code}", [
            'reason' => 'Masa retensi 90 hari terpenuhi.',
        ])->assertRedirect('/records');
        $this->assertNotNull(InformationRecord::withTrashed()->find($record->id)->deleted_at);
    }

    /** §19-D & §19-E: Tata kelola Skills teks-murni & batas Operator */
    public function test_skills_governance_execution_and_operator_boundaries(): void
    {
        $org = $this->makeOrg('Organisasi Alpha');
        $admin = $this->makeUser('ADMINISTRATOR', $org);
        $opAssigned = $this->makeUser('OPERATOR', $org);
        $opUnassigned = $this->makeUser('OPERATOR', $org);

        // Manifest tool non-teks / di luar allowlist harus ditolak validator
        $badErrors = app(SkillService::class)->validateManifest([
            'skill_key' => 'unsafe-case-dump',
            'name' => 'Unsafe Tool',
            'version' => '1.0.0',
            'tool' => 'search_case_evidence', // mengakses data kasus, bukan teks-murni!
            'input_schema' => [['name' => 'q']],
        ]);
        $this->assertNotEmpty($badErrors);

        // Admin memasang, mengonfigurasi, mengaktifkan, dan menugaskan hanya ke opAssigned
        $this->actingAs($admin, 'web')->post('/admin/skills/claim-extractor', ['action' => 'install'])->assertSessionHas('success');
        $skill = Skill::where('skill_key', 'claim-extractor')->firstOrFail();
        $this->assertEquals('DISABLED', $skill->status);

        $this->actingAs($admin, 'web')->post('/admin/skills/claim-extractor', ['action' => 'configure', 'max_input_chars' => 2000]);
        $this->actingAs($admin, 'web')->post('/admin/skills/claim-extractor', ['action' => 'enable']);
        $this->actingAs($admin, 'web')->post('/admin/skills/claim-extractor', ['action' => 'assign', 'user_ids' => [$opAssigned->id]]);

        // Operator yg ditugaskan bisa mengeksekusi
        $this->actingAs($opAssigned, 'web')->post('/skills/claim-extractor/execute', [
            'text' => 'Beredar klaim bahwa kebijakan subsidi dihentikan mulai pekan depan. Pihak resmi menyatakan program tetap berjalan.',
        ])->assertStatus(200);
        $this->assertDatabaseHas('skill_executions', ['skill_id' => $skill->id, 'user_id' => $opAssigned->id, 'status' => 'SUCCESS']);

        // Operator tanpa penugasan ditolak (403)
        $this->actingAs($opUnassigned, 'web')->post('/skills/claim-extractor/execute', [
            'text' => 'Percobaan tanpa penugasan.',
        ])->assertStatus(403);

        // Batas akses Operator pada rute admin
        $this->actingAs($opAssigned, 'web')->get('/admin/skills')->assertStatus(403);
        $this->actingAs($opAssigned, 'web')->get('/admin/accounts')->assertStatus(403);
        $this->actingAs($opAssigned, 'web')->get('/admin/system')->assertStatus(403);
        $this->actingAs($opAssigned, 'web')->get('/admin/audit')->assertStatus(403);
        $this->actingAs($opAssigned, 'web')->get('/operator/history')->assertStatus(200);
    }

    /** §19-D: Append-only SystemLog (ORM + DB trigger) & verifikasi rantai hash SHA-256 */
    public function test_append_only_audit_log_orm_db_triggers_and_hash_chain_verify(): void
    {
        $log = app(AuditLogger::class)->log('AUDIT_CHAIN_TEST', ['type' => 'Test', 'id' => '1'], ['ok' => true]);

        // 1. Eloquent block
        $this->expectException(LogicException::class);
        $log->update(['action' => 'TAMPERED']);
    }

    public function test_db_triggers_block_raw_sql_mutation_and_cli_audit_verify_passes(): void
    {
        $log = app(AuditLogger::class)->log('AUDIT_TRIGGER_CHECK', ['type' => 'Test', 'id' => '2']);

        $updateFailed = false;
        try {
            DB::table('system_logs')->where('id', $log->id)->update(['action' => 'TAMPERED']);
        } catch (\Throwable $e) {
            $updateFailed = str_contains($e->getMessage(), 'append-only');
        }
        $this->assertTrue($updateFailed, 'DB trigger system_logs_no_update harus menolak UPDATE langsung.');

        $deleteFailed = false;
        try {
            DB::table('system_logs')->where('id', $log->id)->delete();
        } catch (\Throwable $e) {
            $deleteFailed = str_contains($e->getMessage(), 'append-only');
        }
        $this->assertTrue($deleteFailed, 'DB trigger system_logs_no_delete harus menolak DELETE langsung.');

        $this->artisan('trace:audit-verify')->assertExitCode(0);
    }

    /** §19-F: Security headers, IDOR console, upload magic bytes, SSRF & redaksi audit log */
    public function test_security_controls_headers_idor_upload_ssrf_and_log_redaction(): void
    {
        // 1. Security Headers pada response HTTP
        $res = $this->get('/login');
        $res->assertHeader('X-Content-Type-Options', 'nosniff');
        $res->assertHeader('X-Frame-Options', 'DENY');
        $res->assertHeader('Referrer-Policy', 'same-origin');
        $res->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');

        // 2. Console IDOR: User B mencoba mengirim pesan ke session milik User A -> 403
        $org = $this->makeOrg();
        $userA = $this->makeUser('OPERATOR', $org);
        $userB = $this->makeUser('OPERATOR', $org);
        $sessionA = ConsoleSession::create(['user_id' => $userA->id, 'title' => 'Private Session A']);

        $this->actingAs($userB, 'web')->postJson('/console/message', [
            'session_id' => $sessionA->id,
            'message' => 'Coba akses sesi user lain',
        ])->assertStatus(403);
        $this->assertDatabaseHas('system_logs', ['action' => 'CONSOLE_SESSION_DENIED', 'user_id' => $userB->id]);

        // 3. Upload validation menolak file palsu (ekstensi .jpg berisi binary MZ executable)
        $fakeJpg = UploadedFile::fake()->createWithContent('payload.jpg', "MZ\x90\x00ThisIsNotAJpeg");
        $uploadRejected = false;
        try {
            EvidenceCollectorService::assertSafeUpload($fakeJpg);
        } catch (ValidationException) {
            $uploadRejected = true;
        }
        $this->assertTrue($uploadRejected, 'File .jpg dengan magic bytes executable MZ wajib ditolak.');

        // 4. Anti-SSRF menolak loopback, private network, metadata cloud & skema file://
        $this->assertNotNull(EvidenceCollectorService::validatePublicUrl('http://127.0.0.1/admin'));
        $this->assertNotNull(EvidenceCollectorService::validatePublicUrl('http://localhost:8000'));
        $this->assertNotNull(EvidenceCollectorService::validatePublicUrl('http://169.254.169.254/latest/meta-data/'));
        $this->assertNotNull(EvidenceCollectorService::validatePublicUrl('http://10.0.0.5/internal'));
        $this->assertNotNull(EvidenceCollectorService::validatePublicUrl('file:///etc/passwd'));

        // 5. AuditLogger menyensor kunci sensitif
        $log = app(AuditLogger::class)->log('REDACTION_TEST', null, [
            'safe_field' => 'visible',
            'password' => 'SecretPassword!',
            'api_key' => 'sk-secret-key',
            'token' => 'Bearer abc',
            'nested' => ['authorization' => 'hidden', 'note' => 'kept'],
        ]);
        $this->assertEquals('visible', $log->metadata['safe_field']);
        $this->assertArrayNotHasKey('password', $log->metadata);
        $this->assertArrayNotHasKey('api_key', $log->metadata);
        $this->assertArrayNotHasKey('token', $log->metadata);
        $this->assertArrayNotHasKey('authorization', $log->metadata['nested']);
        $this->assertEquals('kept', $log->metadata['nested']['note']);
    }
}
