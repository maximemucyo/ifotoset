<?php

namespace App\Actions\Studio;

use App\Models\Client;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

class DeleteClient
{
    /**
     * Soft delete client ensuring photographer ownership.
     *
     * @throws AuthorizationException
     */
    public function execute(User $user, Client $client): void
    {
        if ($client->user_id !== $user->id) {
            throw new AuthorizationException('You are not authorized to delete this client.');
        }

        $client->delete();
    }
}
