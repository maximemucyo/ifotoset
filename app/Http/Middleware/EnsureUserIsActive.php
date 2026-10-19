<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ends the session of an account that was suspended after it logged in.
 */
class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Strict check: a column default not yet loaded on the model reads as null, not suspended
        if ($user && $user->is_active === false) {
            // An admin impersonating a suspended user keeps access to inspect the account
            if ($request->attributes->get('impersonating') === true) {
                return $next($request);
            }

            Auth::guard('web')->logout();

            if ($request->hasSession()) {
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }

            if ($request->expectsJson()) {
                return response()->json([
                    'code' => 'ACCOUNT_SUSPENDED',
                    'message' => 'This account has been suspended.',
                ], 403);
            }

            return redirect()->route('login')->withErrors([
                'email' => 'This account has been suspended. Please contact support.',
            ]);
        }

        return $next($request);
    }
}
