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
        if (!Auth::check()) {
            if ($request->expectsJson()) {
                return response()->json(['error' => 'Akses ditolak: sesi tidak terotentikasi.'], 401);
            }
            return redirect()->route('login')->with('warning', 'Akses Command Center tertutup. Silakan otentikasi identitas Anda.');
        }

        return $next($request);
    }
}
