<?php

namespace App\Actions\Studio;

use App\Models\Package;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

class UpdatePackage
{
    /**
     * Update pricing package ensuring photographer ownership.
     *
     * @throws AuthorizationException
     */
    public function execute(User $user, Package $package, array $data): Package
    {
        if ($package->user_id !== $user->id) {
            throw new AuthorizationException('You are not authorized to update this package.');
        }

        $deliverables = is_array($data['deliverables'] ?? null)
            ? $data['deliverables']
            : array_filter(array_map('trim', explode("\n", (string) ($data['deliverables_text'] ?? ''))));

        $package->update([
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

        return $package;
    }
}
