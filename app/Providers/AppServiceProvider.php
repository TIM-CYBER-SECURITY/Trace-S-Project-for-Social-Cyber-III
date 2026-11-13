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
        RateLimiter::for('trace-heavy', function (Request $request) {
            return Limit::perMinute(30)->by($request->user()?->id ?: $request->ip());
        });

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
