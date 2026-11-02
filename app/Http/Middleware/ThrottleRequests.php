<?php

namespace App\Http\Middleware;

use Illuminate\Routing\Middleware\ThrottleRequests as Middleware;

/**
 * Gives every throttled route its own bucket. Laravel's default keys only by user or
 * domain|ip, so every `throttle:N,M` route on a host shared one counter: a few username
 * checks or status polls would spend the 5/min allowance of POST /register.
 */
class ThrottleRequests extends Middleware
{
    protected function resolveRequestSignature($request)
    {
        $route = $request->route();
        $routeKey = $route ? implode('|', $route->methods()) . ' ' . $route->getDomain() . '/' . $route->uri() : '';

        return sha1($routeKey . '|' . parent::resolveRequestSignature($request));
    }
}
