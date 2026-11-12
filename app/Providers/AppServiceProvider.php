<?php

namespace App\Providers;

use App\Services\Support\AppSettings;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(AppSettings::class);
    }

    public function boot(): void
    {
        RateLimiter::for('registration', fn (Request $r) => Limit::perMinutes(10, 5)->by($r->ip()));
        RateLimiter::for('status-lookup', fn (Request $r) => Limit::perMinute(10)->by($r->ip()));

        // Konfigurasi produksi tidak boleh berjalan dengan pengaturan pengembangan.
        if ($this->app->environment('production')) {
            if (config('app.debug')) {
                logger()->critical('APP_DEBUG aktif di produksi. Nonaktifkan segera.');
            }
            if (config('trace.ai.driver') === 'mock') {
                logger()->warning('AI_DRIVER=mock di produksi: seluruh keluaran AI berlabel SIMULASI.');
            }
        }
    }
}
