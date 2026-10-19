<?php

namespace App\Actions\Studio;

use App\Models\Client;
use App\Models\User;
use Ramsey\Uuid\Uuid;

class CreateClient
{
    /**
     * Create a new client profile strictly assigned to the photographer.
     */
    public function execute(User $user, array $data): Client
    {
        return Client::create([
            'uuid'         => Uuid::uuid7()->toString(),
            'user_id'      => $user->id,
            'name'         => $data['name'],
            'email'        => $data['email'] ?? null,
            'phone'        => $data['phone'] ?? null,
            'company_name' => $data['company_name'] ?? null,
            'location'     => $data['location'] ?? null,
            'instagram'    => $data['instagram'] ?? null,
            'notes'        => $data['notes'] ?? null,
        ]);
    }
}
