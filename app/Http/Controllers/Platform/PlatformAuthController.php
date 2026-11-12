<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\PlatformOwner;
use App\Services\Support\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rules\Password;

class PlatformAuthController extends Controller
{
    public function __construct(private AuditLogger $audit)
    {
    }

    public function showLogin()
    {
        if (Auth::guard('platform')->check()) {
            return redirect()->route('platform.dashboard');
        }
        return view('platform.login');
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'owner_id' => ['required', 'string', 'max:40'],
            'password' => ['required', 'string', 'max:200'],
        ]);
        $ownerId = strtolower(trim($data['owner_id']));
        $key = 'owner-login:' . $ownerId . '|' . $request->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $this->audit->log('OWNER_LOGIN_LOCKED', ['type' => 'PlatformOwner', 'id' => $ownerId], [], null, 'DENIED', ['type' => 'ANON']);
            return back()->withErrors(['owner_id' => 'Terlalu banyak percobaan. Coba lagi dalam ' . RateLimiter::availableIn($key) . ' detik.']);
        }

        $owner = PlatformOwner::where('owner_id', $ownerId)->first();
        $ok = false;
        try {
            $ok = $owner && $owner->status === 'ACTIVE' && Hash::check($data['password'], $owner->password);
        } catch (\RuntimeException) {
        }

        if (! $ok) {
            RateLimiter::hit($key, 600);
            $this->audit->log('OWNER_LOGIN_FAILURE', ['type' => 'PlatformOwner', 'id' => preg_match('/^[a-z0-9._-]{3,40}$/', $ownerId) ? $ownerId : '[invalid]'], [], null, 'FAILURE', ['type' => 'ANON']);
            return back()->withErrors(['owner_id' => 'Identitas atau kata sandi tidak valid.'])->onlyInput('owner_id');
        }

        RateLimiter::clear($key);
        Auth::guard('platform')->login($owner);
        $request->session()->regenerate();
        $owner->forceFill(['last_login_at' => now()])->save();
        $this->audit->log('OWNER_LOGIN_SUCCESS', $owner, [], null, 'SUCCESS');

        return redirect()->intended(route('platform.dashboard'));
    }

    public function logout(Request $request)
    {
        $owner = Auth::guard('platform')->user();
        if ($owner) {
            $this->audit->log('OWNER_LOGOUT', $owner);
        }
        Auth::guard('platform')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('platform.login');
    }

    public function showPassword()
    {
        return view('platform.password');
    }

    public function updatePassword(Request $request)
    {
        $owner = Auth::guard('platform')->user();
        $min = (int) config('trace.security.owner_password_min_length', 14);
        $request->validate([
            'current_password' => ['required'],
            'password' => ['required', 'confirmed', Password::min($min)->mixedCase()->numbers()->symbols()],
        ]);
        if (! Hash::check($request->input('current_password'), $owner->password)) {
            return back()->withErrors(['current_password' => 'Kata sandi saat ini tidak benar.']);
        }
        $owner->forceFill(['password' => $request->input('password'), 'must_change_password' => false])->save();
        $request->session()->regenerate();
        $this->audit->log('OWNER_PASSWORD_CHANGED', $owner);
        return redirect()->route('platform.dashboard')->with('success', 'Kata sandi diganti.');
    }
}
