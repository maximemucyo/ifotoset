<?php

namespace App\Actions\Admin;

use App\Models\AdminAuditLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ToggleUserStatus
{
    /**
     * Toggle a user's active/suspended status with Last-Admin protection and audit logging.
     *
     * @throws ValidationException
     */
    public function execute(User $admin, User $targetUser): User
    {
        $targetIsActive = (bool) ($targetUser->is_active ?? true);

        // Prevent self-suspension
        if ($admin->id === $targetUser->id && $targetIsActive) {
            throw ValidationException::withMessages([
                'status' => 'You cannot suspend your own account.',
            ]);
        }

        return DB::transaction(function () use ($admin, $targetUser, $targetIsActive) {
            // If suspending an admin, ensure at least one other active admin remains
            if ($targetUser->role === 'admin' && $targetIsActive) {
                $activeAdminsCount = User::where('role', 'admin')
                    ->where(function ($q) {
                        $q->where('is_active', true)->orWhere('is_active', 1);
                    })
                    ->whereNull('deleted_at')
                    ->lockForUpdate()
                    ->count();

                if ($activeAdminsCount <= 1) {
                    throw ValidationException::withMessages([
                        'status' => 'Cannot suspend the last remaining active administrator on the platform.',
                    ]);
                }
            }

            $targetUser->is_active = ! $targetIsActive;
            $targetUser->save();

            $action = $targetUser->is_active ? 'user.activated' : 'user.suspended';

            AdminAuditLog::record(
                $admin,
                $action,
                'User',
                (string) $targetUser->id,
                ['is_active' => $targetUser->is_active, 'username' => $targetUser->username]
            );

            return $targetUser;
        });
    }
}
