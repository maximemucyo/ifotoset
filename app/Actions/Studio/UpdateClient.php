<?php

namespace App\Actions\Studio;

use App\Models\Client;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

class UpdateClient
{
    /**
     * Update client profile ensuring photographer ownership.
     *
     * @throws AuthorizationException
     */
    public function execute(User $user, Client $client, array $data): Client
    {
        if ($client->user_id !== $user->id) {
            throw new AuthorizationException('You are not authorized to update this client.');
        }

        $client->update([
            'name'         => $data['name'],
            'email'        => $data['email'] ?? null,
            'phone'        => $data['phone'] ?? null,
            'company_name' => $data['company_name'] ?? null,
            'location'     => $data['location'] ?? null,
            'instagram'    => $data['instagram'] ?? null,
            'notes'        => $data['notes'] ?? null,
        ]);

        return $client;
    }
}
