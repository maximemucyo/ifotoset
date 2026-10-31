<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Decides whether a response may be shared from Cloudflare's cache.
 *
 * Routes opt in with the `edge.cache` middleware; a controller can opt a single
 * response back out with EdgeCache::skip(). Visitors carrying a session or a
 * remember-me cookie always get a private, freshly rendered page.
 */
class EdgeCache
{
    public const ATTRIBUTE = 'edge_cache';

    /**
     * Cloudflare Cache-Tag carried by every cached page.
     */
    public const PAGE_TAG = 'ifotoset-pages';

    public static function skip(Request $request): void
    {
        $request->attributes->set(self::ATTRIBUTE, false);
    }

    /**
     * True when this request's page will be cached and shared with other visitors.
     */
    public static function eligible(Request $request): bool
    {
        if (!config('edge_cache.enabled') || $request->attributes->get(self::ATTRIBUTE) !== true) {
            return false;
        }

        if (!$request->isMethod('GET') && !$request->isMethod('HEAD')) {
            return false;
        }

        // Invitation links set up a session on the way in
        if ($request->query->has('invite')) {
            return false;
        }

        foreach (array_keys($request->cookies->all()) as $name) {
            if ($name === config('session.cookie') || str_starts_with($name, 'remember_')) {
                return false;
            }
        }

        return true;
    }
}
