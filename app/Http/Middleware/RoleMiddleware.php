<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = Auth::user();
        if (!$user) {
            return redirect()->route('login');
        }

        if (!empty($roles) && !$user->hasRole(...$roles)) {
            if ($request->expectsJson()) {
                return response()->json([
                    'error' => 'Akses ditolak: Tingkat otorisasi (' . strtoupper($user->role) . ') tidak mencukupi untuk modul ini.',
                ], 403);
            }
            abort(403, 'Otorisasi ditolak. Peran Anda: ' . strtoupper($user->role) . ' tidak memiliki clearance untuk tindakan tersebut.');
        }

        return $next($request);
    }
}
