<?php

namespace App\Policies;

use App\Models\Gallery;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class GalleryPolicy
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
    public function view(User $user, Gallery $gallery): bool
    {
        return $user->id === $gallery->user_id || $user->role === 'admin';
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
    public function update(User $user, Gallery $gallery): bool
    {
        return $user->id === $gallery->user_id || $user->role === 'admin';
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Gallery $gallery): bool
    {
        return $user->id === $gallery->user_id || $user->role === 'admin';
    }
}
