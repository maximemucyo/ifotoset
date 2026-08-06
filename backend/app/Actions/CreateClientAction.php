<?php

namespace App\Actions;

use App\Models\Client;
use App\Models\User;

class CreateClientAction
{
    /**
     * Create a new client.
     */
    public function execute(User $user, array $data): Client
    {
        $normalizedTags = [];
        if (isset($data['tags']) && is_array($data['tags'])) {
            $normalizedTags = collect($data['tags'])
                ->map(fn($tag) => ucwords(strtolower(trim($tag))))
                ->filter()
                ->unique()
                ->values()
                ->toArray();
        }

        return Client::create([
            'user_id' => $user->id,
            'name' => $data['name'],
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'company_name' => $data['company_name'] ?? null,
            'location' => $data['location'] ?? null,
            'instagram' => $data['instagram'] ?? null,
            'notes' => $data['notes'] ?? null,
            'tags' => $normalizedTags ?: null,
            'last_contacted_at' => isset($data['last_contacted_at']) ? new \DateTime($data['last_contacted_at']) : null,
        ]);
    }
}
