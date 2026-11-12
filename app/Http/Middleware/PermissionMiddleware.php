<?php

namespace App\Http\Middleware;

use App\Services\Support\AuditLogger;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Otorisasi berbasis izin (deny-by-default). Pemakaian: ->middleware('permission:info.review')
 * atau beberapa izin (semua wajib): permission:a,b
 */
class PermissionMiddleware
{
    public function __construct(private AuditLogger $audit)
    {
    }

    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = Auth::guard('web')->user();
        if (! $user) {
            return redirect()->route('login');
        }

        foreach ($permissions as $p) {
            if (! $user->hasPermission($p)) {
                $this->audit->log('ACCESS_DENIED', ['type' => 'Route', 'id' => $request->path()], ['permission' => $p], $user->id, 'DENIED');
                if ($request->expectsJson()) {
                    return response()->json(['error' => 'Akses ditolak: izin tidak mencukupi.'], 403);
                }
                abort(403, 'Otorisasi ditolak: peran Anda (' . $user->roleLabel() . ') tidak memiliki izin untuk tindakan ini.');
            }
        }

        return $next($request);
    }
}
