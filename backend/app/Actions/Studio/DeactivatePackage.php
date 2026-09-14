<?php

namespace App\Actions\Studio;

use App\Models\Package;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

class DeactivatePackage
{
    /**
     * Safely deactivate or soft-delete a package without breaking historical booking relationships.
     *
     * @throws AuthorizationException
     */
    public function execute(User $user, Package $package): void
    {
        if ($package->user_id !== $user->id) {
            throw new AuthorizationException('You are not authorized to delete this package.');
        }

        // If package has bookings, soft deactivate it so historical records remain intact
        if ($package->bookings()->exists()) {
            $package->update(['is_active' => false]);
            $package->delete(); // SoftDeletes
        } else {
            $package->delete(); // SoftDeletes
        }
    }
}
