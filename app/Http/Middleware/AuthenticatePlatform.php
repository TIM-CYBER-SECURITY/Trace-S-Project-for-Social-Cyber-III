<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/** Gerbang Developer/Owner (guard 'platform'), terpisah total dari sesi pengguna. */
class AuthenticatePlatform
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = session('locale', 'en');
        if (in_array($locale, ['en', 'id'], true)) {
            app()->setLocale($locale);
        }

        $owner = Auth::guard('platform')->user();
        if (! $owner || $owner->status !== 'ACTIVE') {
            if ($owner) {
                Auth::guard('platform')->logout();
            }
            return redirect()->route('platform.login')->with('warning', 'Autentikasi Developer/Owner diperlukan.');
        }

        if ($owner->must_change_password && ! $request->routeIs('platform.password', 'platform.password.update', 'platform.logout')) {
            return redirect()->route('platform.password');
        }

        return $next($request);
    }
}
