<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gerbang sesi pengguna operasional. Pada SETIAP permintaan memverifikasi ulang
 * status akun di database, sehingga penangguhan/pencabutan berlaku seketika.
 */
class AuthenticateTrace
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = session('locale', 'en');
        if (in_array($locale, ['en', 'id'], true)) {
            app()->setLocale($locale);
        }

        $user = Auth::guard('web')->user();

        if (! $user || ! $user->canAuthenticate()) {
            $reason = $user ? 'Akun tidak lagi aktif atau telah kedaluwarsa.' : 'Restricted access. Please sign in with your Trace-ID.';
            if ($user) {
                Auth::guard('web')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }
            if ($request->expectsJson()) {
                return response()->json(['error' => 'Access denied: session is not authenticated.'], 401);
            }
            return redirect()->route('login')->with('warning', $reason);
        }

        // Wajib ganti kata sandi sebelum mengakses fitur lain.
        if ($user->must_change_password
            && ! $request->routeIs('password.change', 'password.update', 'logout')) {
            if ($request->expectsJson()) {
                return response()->json(['error' => 'Password change required.'], 403);
            }
            return redirect()->route('password.change');
        }

        return $next($request);
    }
}
