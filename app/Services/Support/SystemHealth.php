<?php

namespace App\Services\Support;

use App\Services\Ai\AiManager;
use App\Services\Identity\RegistryStore;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Pemeriksaan kesehatan sistem. Detail hanya untuk Administrator; /health publik hanya ok/degraded. */
class SystemHealth
{
    public function __construct(private RegistryStore $registry)
    {
    }

    /** @return array<int,array{name:string,status:string,detail:string}> */
    public function checks(): array
    {
        $c = [];

        try {
            DB::select('select 1');
            $c[] = ['name' => 'Database', 'status' => 'OK', 'detail' => DB::connection()->getDriverName() . ' terhubung'];
        } catch (\Throwable) {
            $c[] = ['name' => 'Database', 'status' => 'FAIL', 'detail' => 'Tidak dapat terhubung'];
        }

        $c[] = ['name' => 'Storage', 'status' => is_writable(storage_path()) ? 'OK' : 'FAIL', 'detail' => is_writable(storage_path()) ? 'Dapat ditulis' : 'Tidak dapat ditulis'];

        try {
            $pending = Schema::hasTable('jobs') ? DB::table('jobs')->count() : 0;
            $failed = Schema::hasTable('failed_jobs') ? DB::table('failed_jobs')->count() : 0;
            $c[] = ['name' => 'Antrean (queue)', 'status' => $failed > 0 ? 'WARN' : 'OK', 'detail' => "{$pending} menunggu, {$failed} gagal"];
        } catch (\Throwable) {
            $c[] = ['name' => 'Antrean (queue)', 'status' => 'WARN', 'detail' => 'Tidak dapat diperiksa'];
        }

        $regDir = $this->registry->registryDir();
        $public = str_starts_with(realpath($regDir) ?: $regDir, realpath(public_path()) ?: public_path());
        $c[] = ['name' => 'Registry Trace-ID', 'status' => $public ? 'FAIL' : (is_dir($regDir) ? 'OK' : 'WARN'), 'detail' => $public ? 'Registry berada di web root!' : (is_dir($regDir) ? 'Di luar web root' : 'Direktori belum dibuat')];

        $c[] = ['name' => 'Mode aplikasi', 'status' => (app()->environment('production') && config('app.debug')) ? 'FAIL' : 'OK', 'detail' => app()->environment() . (config('app.debug') ? ' (debug AKTIF)' : '')];

        try {
            $ai = app(AiManager::class)->status();
            $sim = (bool) ($ai['is_simulation'] ?? true);
            $c[] = ['name' => 'Driver AI', 'status' => $sim ? 'WARN' : (($ai['status'] ?? '') === 'ONLINE' ? 'OK' : 'FAIL'), 'detail' => strtoupper($ai['driver'] ?? 'mock') . ($sim ? ' (SIMULASI)' : ' / ' . ($ai['status'] ?? '?'))];
        } catch (\Throwable) {
            $c[] = ['name' => 'Driver AI', 'status' => 'WARN', 'detail' => 'Status tidak tersedia'];
        }

        return $c;
    }

    public function isHealthy(): bool
    {
        foreach ($this->checks() as $x) {
            if ($x['status'] === 'FAIL') {
                return false;
            }
        }
        return true;
    }
}
