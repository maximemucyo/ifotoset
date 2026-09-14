<?php

namespace App\Queries\Admin;

use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;

class AdminUsersQuery
{
    /**
     * Fetch paginated user accounts with optional search and role filtering.
     */
    public function paginate(?string $search = null, ?string $role = null, int $perPage = 20): LengthAwarePaginator
    {
        $query = User::with(['plan'])
            ->withCount(['galleries', 'bookings'])
            ->orderBy('created_at', 'desc');

        if (! empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('username', 'like', "%{$search}%");
            });
        }

        if (! empty($role) && in_array($role, ['admin', 'user'], true)) {
            $query->where('role', $role);
        }

        return $query->paginate($perPage)->withQueryString();
    }
}
