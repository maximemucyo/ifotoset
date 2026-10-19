<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps machine endpoints (photo JSON, downloads, exports) out of search results.
 */
class NoIndex
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');

        return $response;
    }
}
