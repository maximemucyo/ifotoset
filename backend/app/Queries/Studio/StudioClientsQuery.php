<?php

namespace App\Queries\Studio;

use App\Models\Client;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;

class StudioClientsQuery
{
    /**
     * Fetch paginated clients strictly isolated to the given photographer.
     */
    public function paginate(User $user, ?string $search = null, int $perPage = 15): LengthAwarePaginator
    {
        $query = Client::where('user_id', $user->id)
            ->withCount('bookings')
            ->orderBy('created_at', 'desc');

        if (! empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('company_name', 'like', "%{$search}%");
            });
        }

        return $query->paginate($perPage);
    }

    /**
     * Compute CRM summary statistics for the photographer.
     */
    public function stats(User $user): array
    {
        $totalClients = Client::where('user_id', $user->id)->count();

        return [
            'total_clients' => $totalClients,
        ];
    }
}
