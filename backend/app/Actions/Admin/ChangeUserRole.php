<?php

namespace App\Actions\Admin;

use App\Models\AdminAuditLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ChangeUserRole
{
    /**
     * Change a user's role with Last-Admin protection and audit logging.
     *
     * @throws ValidationException
     */
    public function execute(User $admin, User $targetUser, string $newRole): User
    {
        if (! in_array($newRole, ['user', 'admin'], true)) {
            throw ValidationException::withMessages(['role' => 'Invalid role specified.']);
        }

        // Prevent self-demotion
        if ($admin->id === $targetUser->id && $newRole !== 'admin') {
            throw ValidationException::withMessages([
                'role' => 'You cannot remove your own administrator privileges.',
            ]);
        }

        return DB::transaction(function () use ($admin, $targetUser, $newRole) {
            // If demoting an admin, ensure at least one other active admin exists (using row lock)
            if ($targetUser->role === 'admin' && $newRole !== 'admin') {
                $activeAdminsCount = User::where('role', 'admin')
                    ->where(function ($q) {
                        $q->where('is_active', true)->orWhere('is_active', 1);
                    })
                    ->whereNull('deleted_at')
                    ->lockForUpdate()
                    ->count();

                if ($activeAdminsCount <= 1) {
                    throw ValidationException::withMessages([
                        'role' => 'Cannot demote the last remaining active administrator on the platform.',
                    ]);
                }
            }

            $oldRole = $targetUser->role ?? 'user';
            $targetUser->role = $newRole;
            $targetUser->save();

            AdminAuditLog::record(
                $admin,
                'user.role_changed',
                'User',
                (string) $targetUser->id,
                ['old_role' => $oldRole, 'new_role' => $newRole, 'username' => $targetUser->username]
            );

            return $targetUser;
        });
    }
}
