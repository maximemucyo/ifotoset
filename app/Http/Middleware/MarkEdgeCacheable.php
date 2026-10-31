<?php

namespace App\Http\Middleware;

use App\Support\EdgeCache;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Opts a public page into Cloudflare caching; ApplyEdgeCacheHeaders does the rest.
 */
class MarkEdgeCacheable
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!$request->attributes->has(EdgeCache::ATTRIBUTE)) {
            $request->attributes->set(EdgeCache::ATTRIBUTE, true);
        }

        return $next($request);
    }
}
