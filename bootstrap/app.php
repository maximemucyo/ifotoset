<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

// Server-wide PawaPay credentials shared with the other sites on the host. Loaded
// before .env and immutable, so a token rotated there wins over any stale copy here.
if (@is_readable('/etc/pawapay/credentials.env')) {
    Dotenv\Dotenv::createImmutable('/etc/pawapay', 'credentials.env')->safeLoad();
}

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->statefulApi();
        $middleware->web(append: [
            \App\Http\Middleware\HandleImpersonationState::class,
            \App\Http\Middleware\SecurityHeaders::class,
        ]);
        $middleware->alias([
            'verified.api' => \App\Http\Middleware\EnsureEmailIsVerified::class,
            'admin' => \App\Http\Middleware\AdminMiddleware::class,
            'impersonation.prevent_admin' => \App\Http\Middleware\PreventAdminDuringImpersonation::class,
            'active' => \App\Http\Middleware\EnsureUserIsActive::class,
            'noindex' => \App\Http\Middleware\NoIndex::class,
            'studio.account' => \App\Http\Middleware\ResolveStudioAccount::class,
        ]);
        $middleware->replace(
            \Illuminate\Http\Middleware\TrustProxies::class,
            \App\Http\Middleware\TrustProxies::class
        );

        // Exempt public endpoints from CSRF verification
        $middleware->validateCsrfTokens(except: [
            'api/v1/callbacks/*',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (\Illuminate\Routing\Exceptions\InvalidSignatureException $e, \Illuminate\Http\Request $request) {
            if ($request->routeIs('verification.verify')) {
                return redirect()->route('login')->with('error', 'This verification link is invalid or has expired.');
            }
        });
    })->create();
