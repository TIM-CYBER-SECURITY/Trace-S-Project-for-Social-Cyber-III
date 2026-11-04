<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Support\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function __construct(private AuditLogger $audit)
    {
    }

    public function showLogin()
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }

        $demoUsers = User::orderBy('id')->get();
        return view('auth.login', compact('demoUsers'));
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if (Auth::attempt($credentials, (bool) $request->boolean('remember'))) {
            $request->session()->regenerate();
            $user = Auth::user();
            $user->update(['last_login_at' => now()]);

            $this->audit->log('AUTH_LOGIN_SUCCESS', $user, [
                'role' => $user->role,
                'clearance' => $user->clearance_level,
            ], $user->id);

            return redirect()->intended(route('dashboard'))
                ->with('success', 'Selamat bertugas, ' . $user->name . ' [' . strtoupper($user->role) . '].');
        }

        $this->audit->log('AUTH_LOGIN_FAILED', null, [
            'attempted_email' => $request->input('email'),
        ]);

        return back()->withErrors([
            'email' => 'Identitas kredensial tidak cocok dengan arsip personel siber.',
        ])->onlyInput('email');
    }

    /**
     * Quick Switch login untuk evaluasi juri / pengembang
     */
    public function quickLogin(Request $request)
    {
        $role = $request->input('role', 'operator');
        $user = User::where('role', $role)->first();
        if (!$user) {
            return back()->with('error', "User dengan peran {$role} belum terdaftar.");
        }

        Auth::login($user);
        $request->session()->regenerate();
        $user->update(['last_login_at' => now()]);

        $this->audit->log('AUTH_QUICK_LOGIN', $user, [
            'role' => $user->role,
            'mode' => 'DEMO_EVALUATION',
        ], $user->id);

        return redirect()->route('dashboard')
            ->with('success', 'Beralih peran ke: ' . strtoupper($user->role) . ' (' . $user->name . ')');
    }

    public function logout(Request $request)
    {
        $user = Auth::user();
        if ($user) {
            $this->audit->log('AUTH_LOGOUT', $user, [], $user->id);
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('info', 'Sesi terminal ditutup dengan aman.');
    }
}
