<?php

use App\Http\Middleware\AuthenticatePlatform;
use App\Http\Middleware\AuthenticateTrace;
use App\Http\Middleware\PermissionMiddleware;
use App\Http\Middleware\RoleMiddleware;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Di belakang reverse proxy/load balancer: set TRUSTED_PROXIES (mis. "10.0.0.0/8" atau "*").
        if ($proxies = env('TRUSTED_PROXIES')) {
            $middleware->trustProxies(at: $proxies === '*' ? '*' : array_map('trim', explode(',', $proxies)));
        }

        $middleware->web(append: [SecurityHeaders::class]);

        $middleware->alias([
            'auth.trace' => AuthenticateTrace::class,
            'auth.platform' => AuthenticatePlatform::class,
            'permission' => PermissionMiddleware::class,
            'role' => RoleMiddleware::class,
        ]);

        // Tamu diarahkan ke halaman login yang sesuai.
        $middleware->redirectGuestsTo(fn (Request $r) => $r->is('platform*') ? route('platform.login') : route('login'));
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Sesi/CSRF kedaluwarsa: kembali ke login tanpa stack trace.
        $exceptions->render(function (TokenMismatchException $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json(['error' => 'Sesi kedaluwarsa. Muat ulang halaman.'], 419);
            }
            return redirect()->route($request->is('platform*') ? 'platform.login' : 'login')
                ->with('warning', 'Sesi Anda telah kedaluwarsa. Silakan masuk kembali.');
        });

        // Jangan pernah membocorkan detail internal pada galat server di luar mode debug.
        $exceptions->render(function (\Throwable $e, Request $request) {
            if (config('app.debug') || $e instanceof HttpExceptionInterface) {
                return null;
            }
            if ($e instanceof \Illuminate\Validation\ValidationException
                || $e instanceof \Illuminate\Auth\AuthenticationException
                || $e instanceof \Illuminate\Auth\Access\AuthorizationException
                || $e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException) {
                return null;
            }
            report($e);
            if ($request->expectsJson()) {
                return response()->json(['error' => 'Terjadi kesalahan internal. Hubungi administrator.'], 500);
            }
            return response()->view('errors.500', [], 500);
        });
    })->create();
