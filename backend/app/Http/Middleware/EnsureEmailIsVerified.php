<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Contracts\Auth\MustVerifyEmail;

class EnsureEmailIsVerified
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Bypass OPTIONS and HEAD requests to prevent CORS interference
        if ($request->isMethod('OPTIONS') || $request->isMethod('HEAD')) {
            return $next($request);
        }

        $user = $request->user();

        if (! $user ||
            ($user instanceof MustVerifyEmail &&
            ! $user->hasVerifiedEmail())) {

            return response()->json([
                'code' => 'EMAIL_NOT_VERIFIED',
                'message' => 'Your email address is not verified. Please verify your email to unlock all features.'
            ], 403);
        }

        return $next($request);
    }
}
