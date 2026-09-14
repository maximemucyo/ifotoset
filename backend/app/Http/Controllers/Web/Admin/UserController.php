<?php

namespace App\Http\Controllers\Web\Admin;

use App\Actions\Admin\ChangeUserRole;
use App\Actions\Admin\ToggleUserStatus;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Queries\Admin\AdminUsersQuery;
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

        return view('admin.users', [
            'users'  => $users,
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
}
