<?php

namespace App\Actions;

use App\Models\Client;

class UpdateClientAction
{
    /**
     * Update an existing client.
     */
    public function execute(Client $client, array $data): Client
    {
        if (isset($data['tags']) && is_array($data['tags'])) {
            $data['tags'] = collect($data['tags'])
                ->map(fn($tag) => ucwords(strtolower(trim($tag))))
                ->filter()
                ->unique()
                ->values()
                ->toArray();
        }

        // Touch last_contacted_at if email/phone changed
        $emailChanged = isset($data['email']) && $data['email'] !== $client->email;
        $phoneChanged = isset($data['phone']) && $data['phone'] !== $client->phone;
        if ($emailChanged || $phoneChanged) {
            $data['last_contacted_at'] = now();
        }

        $client->update($data);

        return $client;
    }
}
