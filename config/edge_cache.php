<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Edge (Cloudflare) caching of public pages
    |--------------------------------------------------------------------------
    |
    | Pages marked with the `edge.cache` middleware are sent without cookies and
    | with a shared-cache lifetime when the visitor has no session, so Cloudflare
    | can serve them from the nearest data centre. Cloudflare only stores them
    | once a Cache Rule makes HTML eligible for caching.
    |
    | Keep the lifetime short unless CLOUDFLARE_ZONE_ID and CLOUDFLARE_API_TOKEN
    | are set: those let the app purge a gallery's cached page when it changes.
    |
    */

    'enabled' => (bool) env('EDGE_CACHE_ENABLED', true),

    'ttl' => (int) env('EDGE_CACHE_TTL', 300),

];
