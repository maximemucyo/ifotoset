<?php

namespace App\Http\Controllers\Web\Auth;

use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ImpersonationController extends Controller
{
    /**
     * Leave impersonation mode and return securely to the original administrator account.
     */
    public function leave(Request $request): RedirectResponse
    {
        $impersonation = $request->session()->get('impersonation');

        if (!is_array($impersonation) || empty($impersonation['admin_id'])) {
            return redirect()->route('login')
                ->with('error', 'No active impersonation session found.');
        }

        $adminId = (int) $impersonation['admin_id'];
        $targetUserId = $impersonation['target_user_id'] ?? $request->user()?->id;

        $admin = User::where('role', 'admin')->find($adminId);
        if (!$admin) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            return redirect()->route('login')
                ->with('error', 'The original administrator account could not be found.');
        }

        // 1. Invalidate current target user authentication state
        Auth::logout();

        // 2. Clear impersonation context
        $request->session()->forget('impersonation');

        // 3. Mandatory session regeneration on exit to prevent state leakage
        $request->session()->regenerate();

        // 4. Authenticate original admin
        Auth::login($admin);

        // 5. Record immutable audit event
        AdminAuditLog::record(
            $admin,
            'impersonation.ended',
            'User',
            (string) $targetUserId,
            [
                'admin_id' => $admin->id,
                'target_user_id' => $targetUserId,
                'nonce' => $impersonation['nonce'] ?? null,
            ]
        );

        return redirect()->route('admin.users.index')
            ->with('success', "Exited impersonation mode. Returned to administrator account ({$admin->email}).");
    }
}
