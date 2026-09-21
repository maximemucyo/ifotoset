<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class HandleImpersonationState
{
    /**
     * Handle an incoming request and expose impersonation context.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $impersonation = $request->session()->get('impersonation');

        if (is_array($impersonation) && !empty($impersonation['admin_id'])) {
            $admin = User::where('role', 'admin')->find($impersonation['admin_id']);

            if ($admin && $request->user()) {
                $request->attributes->set('impersonating', true);
                $request->attributes->set('impersonator_admin', $admin);
                $request->attributes->set('impersonation_started_at', $impersonation['started_at'] ?? null);

                View::share('impersonating', true);
                View::share('impersonatorAdmin', $admin);
                View::share('impersonatedUser', $request->user());
            } else {
                $request->session()->forget('impersonation');
                View::share('impersonating', false);
                View::share('impersonatorAdmin', null);
            }
        } else {
            View::share('impersonating', false);
            View::share('impersonatorAdmin', null);
        }

        return $next($request);
    }
}
