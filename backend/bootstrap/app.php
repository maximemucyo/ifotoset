<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->statefulApi();
        $middleware->alias([
            'verified.api' => \App\Http\Middleware\EnsureEmailIsVerified::class
        ]);
        $middleware->trustProxies(
            at: ['127.0.0.1', '::1']
        );

        // Exempt public endpoints from CSRF verification
        $middleware->validateCsrfTokens(except: [
            'api/v1/callbacks/*',
            'api/v1/public/booking/*',
            'api/v1/public/bookings/*/payments',
            'api/v1/public/galleries/*/unlock',
            'api/v1/public/galleries/*/download',
            'api/v1/public/galleries/*/favorite',
            'api/v1/public/photographers/*/reviews',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (\Illuminate\Routing\Exceptions\InvalidSignatureException $e, \Illuminate\Http\Request $request) {
            if ($request->routeIs('verification.verify')) {
                return redirect(config('app.frontend_url') . '/login?verified=0&error=invalid');
            }
        });
    })->create();
