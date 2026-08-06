<?php

namespace App\Actions;

use App\Models\User;

class UpdateUserProfileAction
{
    /**
     * Update the user profile.
     */
    public function execute(User $user, array $data): User
    {
        if (isset($data['username'])) {
            $data['username'] = strtolower(trim($data['username']));
        }

        $user->update($data);

        return $user;
    }
}
