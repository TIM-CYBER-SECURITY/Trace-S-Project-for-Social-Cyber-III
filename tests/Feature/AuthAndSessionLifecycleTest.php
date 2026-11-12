<?php

namespace Tests\Feature;

use App\Models\SystemLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\CreatesTraceFixtures;
use Tests\TestCase;

class AuthAndSessionLifecycleTest extends TestCase
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

    /** §19-C: Login awal PROVISIONED memaksa ganti kata sandi & mengaktifkan akun ke ACTIVE */
    public function test_provisioned_user_forced_to_change_password_transitions_to_active(): void
    {
        $tempPass = 'Init!Pass#987654';
        $user = $this->makeUser('OPERATOR', null, 'PROVISIONED', true, $tempPass);

        $login = $this->post('/login', [
            'trace_id' => $user->trace_id,
            'password' => $tempPass,
        ]);
        $login->assertRedirect('/password/change');

        // Akses dashboard sebelum ganti sandi dialihkan paksa ke /password/change
        $this->get('/dashboard')->assertRedirect('/password/change');

        // Kata sandi memuat Trace-ID harus ditolak
        $this->post('/password/change', [
            'current_password' => $tempPass,
            'password' => $user->trace_id . '!Aa123',
            'password_confirmation' => $user->trace_id . '!Aa123',
        ])->assertSessionHasErrors('password');

        // Kata sandi valid mengganti sandi & memindahkan PROVISIONED -> ACTIVE
        $newPass = 'N3w!Operational#2026';
        $this->post('/password/change', [
            'current_password' => $tempPass,
            'password' => $newPass,
            'password_confirmation' => $newPass,
        ])->assertRedirect('/dashboard');

        $user->refresh();
        $this->assertFalse($user->must_change_password);
        $this->assertEquals('ACTIVE', $user->account_status);
        $this->assertTrue(Hash::check($newPass, $user->password));

        // Sekarang dapat mengakses halaman operasional
        $this->get('/operator/dashboard')->assertStatus(200);
    }

    /** §19-C: Lockout brute-force login & penyensoran format Trace-ID tidak valid pada audit */
    public function test_login_lockout_and_safe_audit_label_on_malformed_trace_id(): void
    {
        $user = $this->makeUser('OPERATOR');
        $max = (int) config('trace.security.login_max_attempts', 5);

        // Bila seseorang mengetik rahasia ke kolom Trace-ID, audit harus mencatat [format-tidak-valid]
        $this->post('/login', [
            'trace_id' => 'MySecretPass123!',
            'password' => 'anything',
        ])->assertSessionHasErrors('trace_id');

        $badLog = SystemLog::where('action', 'LOGIN_FAILURE')->latest('id')->first();
        $this->assertEquals('[format-tidak-valid]', $badLog->target_id);

        for ($i = 0; $i < $max; $i++) {
            $this->post('/login', [
                'trace_id' => $user->trace_id,
                'password' => 'WrongPassword#123',
            ]);
        }

        // Percobaan ke-(max+1) memicu LOGIN_LOCKED
        $locked = $this->post('/login', [
            'trace_id' => $user->trace_id,
            'password' => 'Str0ng!Pass#2026',
        ]);
        $locked->assertSessionHasErrors('trace_id');
        $this->assertDatabaseHas('system_logs', ['action' => 'LOGIN_LOCKED', 'target_id' => $user->trace_id]);
    }

    /** §19-C: Akun yang ditangguhkan/dicabut/kedaluwarsa diblokir seketika di tengah sesi */
    public function test_suspended_revoked_and_expired_accounts_blocked_mid_session(): void
    {
        $op1 = $this->makeUser('OPERATOR', null, 'ACTIVE', false);
        $this->actingAs($op1, 'web')->get('/operator/dashboard')->assertStatus(200);

        // Tangguhkan di DB (simulasi aksi administrator) -> permintaan berikutnya langsung ditolak
        $op1->update(['account_status' => 'SUSPENDED']);
        $this->get('/operator/dashboard')->assertRedirect('/login');
        $this->assertGuest('web');

        // Akun dicabut (REVOKED)
        $op2 = $this->makeUser('OPERATOR', null, 'REVOKED', false);
        $this->actingAs($op2, 'web')->getJson('/records')->assertStatus(401);

        // Akun kedaluwarsa (expires_at di masa lalu)
        $op3 = $this->makeUser('OPERATOR', null, 'ACTIVE', false);
        $op3->update(['expires_at' => now()->subMinute()]);
        $this->actingAs($op3, 'web')->get('/operator/dashboard')->assertRedirect('/login');
    }

    /** §19-C: Pemisahan ketat peran ADMINISTRATOR vs OPERATOR & penonaktifan /platform */
    public function test_strict_role_isolation_and_password_visibility_controls(): void
    {
        $admin = $this->makeUser('ADMINISTRATOR');
        $operator = $this->makeUser('OPERATOR');

        // Rute pendaftaran/portal lama tidak tersedia
        $this->actingAs($admin, 'web')->get('/platform')->assertStatus(404);
        $this->actingAs($admin, 'web')->get('/register')->assertStatus(404);

        // Halaman ganti sandi menyediakan kontrol Show/Hide Password untuk aksesibilitas
        $this->actingAs($operator, 'web')->get('/password/change')
            ->assertStatus(200)
            ->assertSee('data-password-toggle="current_password"', false)
            ->assertSee('data-password-toggle="password"', false)
            ->assertSee('data-password-toggle="password_confirmation"', false);

        // Administrator masuk /admin/dashboard, Operator ditolak (403)
        $this->actingAs($admin, 'web')->get('/admin/dashboard')->assertStatus(200);
        $this->actingAs($operator, 'web')->get('/admin/dashboard')->assertStatus(403);
        $this->actingAs($operator, 'web')->get('/operator/dashboard')->assertStatus(200);
    }
}
