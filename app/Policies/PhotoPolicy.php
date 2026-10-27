<?php

namespace App\Policies;

use App\Models\Photo;
use App\Models\User;
use App\Support\StudioAccount;
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
        return $gallery && (StudioAccount::current()?->owns($gallery->user_id, 'galleries.manage') || $user->role === 'admin');
    }

    /**
     * Determine whether the user can update the photo (e.g. visibility).
     */
    public function update(User $user, Photo $photo): bool
    {
        $gallery = $photo->gallery;
        return $gallery && (StudioAccount::current()?->owns($gallery->user_id, 'galleries.manage') || $user->role === 'admin');
    }

    /**
     * Determine whether the user can set the photo as gallery cover.
     */
    public function setCover(User $user, Photo $photo): bool
    {
        $gallery = $photo->gallery;
        return $gallery && (StudioAccount::current()?->owns($gallery->user_id, 'galleries.manage') || $user->role === 'admin');
    }
}
