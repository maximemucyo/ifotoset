<?php

namespace App\Policies;

use App\Models\Booking;
use App\Models\User;
use App\Support\StudioAccount;
use Illuminate\Auth\Access\HandlesAuthorization;

class BookingPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Booking $booking): bool
    {
        return StudioAccount::current()?->belongsTo($booking->user_id) || $user->role === 'admin';
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Booking $booking): bool
    {
        return StudioAccount::current()?->owns($booking->user_id, 'bookings.manage') || $user->role === 'admin';
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Booking $booking): bool
    {
        return StudioAccount::current()?->owns($booking->user_id, 'bookings.manage') || $user->role === 'admin';
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Booking $booking): bool
    {
        return StudioAccount::current()?->owns($booking->user_id, 'bookings.manage') || $user->role === 'admin';
    }
}
