<?php

namespace App\Actions\Studio;

use App\Models\Package;
use App\Models\User;
use Ramsey\Uuid\Uuid;

class CreatePackage
{
    /**
     * Create a photography pricing package strictly for the photographer.
     */
    public function execute(User $user, array $data): Package
    {
        $deliverables = is_array($data['deliverables'] ?? null)
            ? $data['deliverables']
            : array_filter(array_map('trim', explode("\n", (string) ($data['deliverables_text'] ?? ''))));

        return Package::create([
            'uuid'             => Uuid::uuid7()->toString(),
            'user_id'          => $user->id,
            'name'             => $data['name'],
            'description'      => $data['description'] ?? null,
            'price'            => $data['price'],
            'currency'         => $data['currency'] ?? 'RWF',
            'duration_minutes' => (int) ($data['duration_minutes'] ?? 60),
            'deliverables'     => array_values($deliverables),
            'deposit_type'     => $data['deposit_type'] ?? 'none',
            'deposit_amount'   => ! empty($data['deposit_amount']) ? (float) $data['deposit_amount'] : null,
            'is_active'        => ! empty($data['is_active']),
            'sort_order'       => (int) ($data['sort_order'] ?? 0),
        ]);
    }
}
