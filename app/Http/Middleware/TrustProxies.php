<?php

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustProxies as Middleware;

/**
 * Trusts only the proxies listed in config/proxies.php (Cloudflare + loopback by default).
 */
class TrustProxies extends Middleware
{
    protected function proxies()
    {
        return config('proxies.trusted');
    }
}
