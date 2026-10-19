<?php

namespace App\Policies;

use App\Models\Photo;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class PhotoPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can delete the photo.
     */
    public function delete(User $user, Photo $photo): bool
    {
        $gallery = $photo->gallery;
        return $gallery && ($user->id === $gallery->user_id || $user->role === 'admin');
    }

    /**
     * Determine whether the user can update the photo (e.g. visibility).
     */
    public function update(User $user, Photo $photo): bool
    {
        $gallery = $photo->gallery;
        return $gallery && ($user->id === $gallery->user_id || $user->role === 'admin');
    }

    /**
     * Determine whether the user can set the photo as gallery cover.
     */
    public function setCover(User $user, Photo $photo): bool
    {
        $gallery = $photo->gallery;
        return $gallery && ($user->id === $gallery->user_id || $user->role === 'admin');
    }
}
