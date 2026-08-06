<?php

namespace App\Actions;

use App\Models\Package;
use App\Models\User;

class CreatePackageAction
{
    /**
     * Create a new pricing package.
     */
    public function execute(User $user, array $data): Package
    {
        if (!isset($data['sort_order'])) {
            $maxSortOrder = Package::where('user_id', $user->id)->max('sort_order') ?? -1;
            $data['sort_order'] = $maxSortOrder + 1;
        }

        return Package::create([
            'user_id' => $user->id,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'price' => $data['price'],
            'currency' => $data['currency'] ?? 'RWF',
            'duration_minutes' => $data['duration_minutes'],
            'deliverables' => $data['deliverables'],
            'sort_order' => $data['sort_order'],
            'is_active' => $data['is_active'] ?? true,
        ]);
    }
}
