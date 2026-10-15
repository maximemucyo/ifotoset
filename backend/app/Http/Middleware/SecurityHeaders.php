<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Baseline security headers for every web response. Set here rather than in
 * the web server config so they apply whichever server fronts the app.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $headers = $response->headers;

        $headers->set('X-Content-Type-Options', 'nosniff', false);
        $headers->set('X-Frame-Options', 'SAMEORIGIN', false);
        $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin', false);
        $headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=()', false);

        if ($request->isSecure()) {
            $headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains', false);
        }

        // Report-only first: watch the browser console for violations before enforcing
        if (!$headers->has('Content-Security-Policy')) {
            $headers->set('Content-Security-Policy-Report-Only', $this->contentSecurityPolicy(), false);
        }

        return $response;
    }

    private function contentSecurityPolicy(): string
    {
        $cdn = 'https://' . config('filesystems.disks.b2.cdn_domain', 'cdn.ifotoset.com');
        $analytics = 'https://www.googletagmanager.com https://*.google-analytics.com https://*.analytics.google.com';

        return implode('; ', [
            "default-src 'self'",
            // Alpine.js evaluates expressions, which needs 'unsafe-eval' outside its CSP build
            "script-src 'self' 'unsafe-inline' 'unsafe-eval' {$analytics}",
            "style-src 'self' 'unsafe-inline'",
            "img-src 'self' data: blob: {$cdn} https://*.backblazeb2.com https://*.googleusercontent.com {$analytics}",
            "media-src 'self' blob: {$cdn}",
            "font-src 'self' data:",
            "connect-src 'self' {$cdn} https://*.backblazeb2.com {$analytics}",
            "frame-ancestors 'self'",
            "base-uri 'self'",
            "form-action 'self' https://*.ifotoset.com",
            "object-src 'none'",
        ]);
    }
}
