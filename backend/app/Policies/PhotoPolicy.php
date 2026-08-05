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
}
