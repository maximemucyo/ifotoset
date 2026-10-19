<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PreventAdminDuringImpersonation
{
    /**
     * Prevent administrators from accessing administrative functions while impersonating a regular user.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->attributes->get('impersonating') === true || $request->session()->has('impersonation')) {
            if ($request->expectsJson()) {
                return response()->json([
                    'code' => 'ADMIN_FORBIDDEN_WHILE_IMPERSONATING',
                    'message' => 'Administrative operations are disabled while impersonating a user. Please return to your admin account first.',
                ], 403);
            }

            return redirect()->route('studio.dashboard')
                ->with('error', 'Administrative operations are forbidden while impersonating a user. Please exit impersonation first.');
        }

        return $next($request);
    }
}
