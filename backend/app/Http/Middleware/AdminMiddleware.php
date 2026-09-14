<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()) {
            return redirect()->guest(route('login'));
        }

        if (! method_exists($request->user(), 'isAdmin') || ! $request->user()->isAdmin()) {
            return redirect()
                ->route('studio.dashboard')
                ->with('error', 'Access denied: Admin privileges required.')
                ->with('toast', [
                    'type'    => 'error',
                    'message' => 'Access denied: Admin privileges required.',
                ]);
        }

        return $next($request);
    }
}
