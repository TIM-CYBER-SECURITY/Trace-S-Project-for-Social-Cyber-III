<?php

namespace App\Http\Controllers;

use App\Services\Support\SystemHealth;

class HealthController extends Controller
{
    /** Endpoint publik minimal: tanpa detail internal. */
    public function __invoke(SystemHealth $health)
    {
        $ok = $health->isHealthy();
        return response()->json(['status' => $ok ? 'ok' : 'degraded'], $ok ? 200 : 503);
    }
}
