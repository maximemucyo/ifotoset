<?php

namespace App\Http\Controllers\Web\Admin;

use App\Actions\Admin\ChangeUserRole;
use App\Actions\Admin\ToggleUserStatus;
use App\Actions\Billing\AssignPlan;
use App\Actions\Billing\RevokePlan;
use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\User;
use App\Queries\Admin\AdminUsersQuery;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class UserController extends Controller
{
    /**
     * Display directory of users.
     */
    public function index(Request $request, AdminUsersQuery $query): View
    {
        $search = $request->input('search');
        $role = $request->input('role');

        $users = $query->paginate($search, $role, 20);
        $plans = Plan::orderBy('monthly_price')->get();

        return view('admin.users', [
            'users'  => $users,
            'plans'  => $plans,
            'search' => $search,
            'role'   => $role,
        ]);
    }

    /**
     * Toggle user active/suspended status with Last-Admin protection.
     */
    public function toggleStatus(Request $request, int $id, ToggleUserStatus $action): RedirectResponse
    {
        $admin = $request->user();
        $targetUser = User::findOrFail($id);

        try {
            $action->execute($admin, $targetUser);
            $status = $targetUser->is_active ? 'activated' : 'suspended';
            return back()->with('success', "User '{$targetUser->name}' has been {$status}.");
        } catch (ValidationException $e) {
            return back()->with('error', $e->validator->errors()->first());
        }
    }

    /**
     * Change user role with Last-Admin protection.
     */
    public function changeRole(Request $request, int $id, ChangeUserRole $action): RedirectResponse
    {
        $admin = $request->user();
        $targetUser = User::findOrFail($id);

        $validated = $request->validate([
            'role' => ['required', 'string', 'in:user,admin'],
        ]);

        try {
            $action->execute($admin, $targetUser, $validated['role']);
            return back()->with('success', "User '{$targetUser->name}' role updated to {$validated['role']}.");
        } catch (ValidationException $e) {
            return back()->with('error', $e->validator->errors()->first());
        }
    }

    /**
     * Admin manually assigns a plan to a user with audit trail.
     */
    public function assignPlan(Request $request, int $id, AssignPlan $action): RedirectResponse
    {
        $admin = $request->user();
        $targetUser = User::findOrFail($id);

        $validated = $request->validate([
            'plan_slug' => ['required', 'string', 'exists:plans,slug'],
            'billing_cycle' => ['required', 'string', 'in:monthly,annual'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $plan = Plan::where('slug', $validated['plan_slug'])->firstOrFail();

        try {
            $action->execute(
                admin: $admin,
                targetUser: $targetUser,
                newPlan: $plan,
                billingCycle: $validated['billing_cycle'],
                reason: $validated['reason'] ?? 'Admin manual assignment'
            );

            return back()->with('success', "User '{$targetUser->name}' has been assigned the '{$plan->name}' plan.");
        } catch (Exception $e) {
            return back()->with('error', "Failed to assign plan: {$e->getMessage()}");
        }
    }

    /**
     * Admin revokes a user's paid plan back to Free tier with audit trail.
     */
    public function revokePlan(Request $request, int $id, RevokePlan $action): RedirectResponse
    {
        $admin = $request->user();
        $targetUser = User::findOrFail($id);

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $action->execute(
                admin: $admin,
                targetUser: $targetUser,
                reason: $validated['reason'] ?? 'Admin manual revocation'
            );

            return back()->with('success', "Paid plan revoked for user '{$targetUser->name}'. Reverted to Free tier (2 GB).");
        } catch (Exception $e) {
            return back()->with('error', "Failed to revoke plan: {$e->getMessage()}");
        }
    }
}
