<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Identity\AccountLifecycle;
use App\Services\Support\AuditLogger;
use App\Services\Support\Notifier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    public function __construct(private AuditLogger $audit, private AccountLifecycle $lifecycle, private Notifier $notifier)
    {
    }

    public function showLogin()
    {
        $this->applyLocale();
        if (Auth::guard('web')->check()) {
            return redirect()->route('dashboard');
        }
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $this->applyLocale();
        $data = $request->validate([
            'trace_id' => ['required', 'string', 'max:20'],
            'password' => ['required', 'string', 'max:200'],
        ]);

        $traceId = strtoupper(trim($data['trace_id']));
        $key = 'login:' . $traceId . '|' . $request->ip();
        $max = (int) config('trace.security.login_max_attempts', 5);

        if (RateLimiter::tooManyAttempts($key, $max)) {
            $this->audit->log('LOGIN_LOCKED', ['type' => 'TraceID', 'id' => $this->safeLabel($traceId)], [], null, 'DENIED', ['type' => 'ANON']);
            return back()->withErrors([
                'trace_id' => 'Terlalu banyak percobaan. Coba lagi dalam ' . RateLimiter::availableIn($key) . ' detik.',
            ])->onlyInput('trace_id');
        }

        $user = User::where('trace_id', $traceId)->first();
        $valid = $this->passwordMatches($user, $data['password']);

        if (! $valid) {
            RateLimiter::hit($key, (int) config('trace.security.login_decay_seconds', 300));
            $this->audit->log('LOGIN_FAILURE', ['type' => 'TraceID', 'id' => $this->safeLabel($traceId)], ['reason' => 'invalid_credentials'], null, 'FAILURE', ['type' => 'ANON']);
            return back()->withErrors(['trace_id' => 'Trace-ID atau kata sandi tidak valid.'])->onlyInput('trace_id');
        }

        if (! $user->canAuthenticate()) {
            $reason = $user->isExpired() ? 'EXPIRED' : $user->account_status;
            $this->audit->log('LOGIN_DENIED', $user, ['reason' => $reason], $user->id, 'DENIED');
            return back()->withErrors(['trace_id' => 'Akun tidak aktif (' . $reason . '). Hubungi administrator.'])->onlyInput('trace_id');
        }

        RateLimiter::clear($key);
        Auth::guard('web')->login($user, false);
        $request->session()->regenerate();
        $deviceCode = User::computeDeviceCode($request->ip(), $request->userAgent());
        $user->forceFill([
            'last_login_at' => now(),
            'last_device_code' => $deviceCode,
            'last_login_ip' => (string) $request->ip(),
            'last_user_agent' => mb_substr((string) $request->userAgent(), 0, 255),
        ])->save();

        $this->audit->log('LOGIN_SUCCESS', $user, [
            'role' => $user->roleLabel(),
            'device_code' => $deviceCode,
            'organization_id' => $user->organization_id,
            'organization_code' => $user->organization?->code,
            'organization_name' => $user->organization?->name,
        ], $user->id);

        if ($user->must_change_password) {
            return redirect()->route('password.change')->with('warning', 'Anda wajib mengganti kata sandi sementara sebelum melanjutkan.');
        }

        return redirect()->intended(route('dashboard'));
    }

    public function logout(Request $request)
    {
        $user = Auth::guard('web')->user();
        if ($user) {
            $this->audit->log('LOGOUT', $user, [
                'device_code' => User::computeDeviceCode($request->ip(), $request->userAgent()),
                'organization_id' => $user->organization_id,
                'organization_code' => $user->organization?->code,
            ], $user->id);
        }
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('info', 'Sesi ditutup dengan aman.');
    }

    public function showChangePassword()
    {
        return view('auth.change-password', ['forced' => (bool) Auth::user()->must_change_password]);
    }

    public function updatePassword(Request $request)
    {
        $user = $request->user();
        $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', Password::min((int) config('trace.security.password_min_length', 12))->mixedCase()->numbers()->symbols()],
        ]);

        if (! Hash::check($request->input('current_password'), $user->password)) {
            $this->audit->log('PASSWORD_CHANGE_FAILED', $user, ['reason' => 'wrong_current'], $user->id, 'FAILURE');
            return back()->withErrors(['current_password' => 'Kata sandi saat ini tidak benar.']);
        }
        if (Hash::check($request->input('password'), $user->password)) {
            return back()->withErrors(['password' => 'Kata sandi baru harus berbeda dari yang lama.']);
        }
        if (stripos($request->input('password'), (string) $user->trace_id) !== false) {
            return back()->withErrors(['password' => 'Kata sandi tidak boleh memuat Trace-ID.']);
        }

        $user->forceFill([
            'password' => $request->input('password'),
            'must_change_password' => false,
            'password_changed_at' => now(),
        ])->save();

        // Cabut sesi lain milik akun ini; pertahankan sesi sekarang.
        DB::table('sessions')->where('user_id', $user->id)->where('id', '!=', $request->session()->getId())->delete();
        $request->session()->regenerate();

        $this->audit->log('PASSWORD_CHANGED', $user, [], $user->id);
        $this->notifier->notify($user, 'SECURITY', 'Kata sandi diganti', 'Kata sandi akun Anda berhasil diganti.');
        $this->lifecycle->markFirstLoginComplete($user);

        return redirect()->route('dashboard')->with('success', 'Kata sandi berhasil diganti.');
    }

    private function passwordMatches(?User $user, string $plain): bool
    {
        static $dummy = null;
        try {
            if (! $user) {
                $dummy ??= Hash::make(Str::random(24)); // menyamakan waktu respons
                Hash::check($plain, $dummy);
                return false;
            }
            return Hash::check($plain, $user->password);
        } catch (\RuntimeException) {
            return false;
        }
    }

    /** Mencegah kata sandi yang salah ketik di kolom Trace-ID tersimpan di audit log. */
    private function safeLabel(string $traceId): string
    {
        return preg_match('/^TRS-[A-Z0-9]{4}-[A-Z0-9]{4}$/', $traceId) ? $traceId : '[format-tidak-valid]';
    }

    private function applyLocale(): void
    {
        $locale = session('locale', 'en');
        if (in_array($locale, ['en', 'id'], true)) {
            app()->setLocale($locale);
        }
    }
}
