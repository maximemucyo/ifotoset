<?php

namespace App\Http\Middleware;

use App\Support\EdgeCache;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Global middleware, so it runs after the session middleware has queued its cookies.
 *
 * For a cacheable page it drops every cookie (Cloudflare won't store a response
 * that sets one, and a shared page must not hand out anyone's session) and marks
 * the response as storable by shared caches only; browsers still revalidate.
 */
class ApplyEdgeCacheHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($response->getStatusCode() !== 200 || $request->user() || !EdgeCache::eligible($request)) {
            return $response;
        }

        foreach ($response->headers->getCookies() as $cookie) {
            $response->headers->removeCookie($cookie->getName(), $cookie->getPath(), $cookie->getDomain());
        }

        $response->headers->set('Cache-Control', 'public, max-age=0, s-maxage=' . config('edge_cache.ttl'));
        // Lets a deploy clear cached pages without touching cached photos and assets
        $response->headers->set('Cache-Tag', EdgeCache::PAGE_TAG);

        return $response;
    }
}
