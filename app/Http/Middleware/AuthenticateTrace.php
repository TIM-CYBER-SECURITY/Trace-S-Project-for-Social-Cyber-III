<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateTrace
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = session('locale', 'en');
        if (in_array($locale, ['en', 'id'], true)) {
            app()->setLocale($locale);
        }

        if (!Auth::check()) {
            if ($request->expectsJson()) {
                return response()->json(['error' => 'Access denied: session is not authenticated.'], 401);
            }
            return redirect()->route('login')->with('warning', 'Restricted access. Please sign in with your official credentials.');
        }

        return $next($request);
    }
}
