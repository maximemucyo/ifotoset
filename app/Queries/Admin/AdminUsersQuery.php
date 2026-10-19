<?php

namespace App\Queries\Admin;

use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;

class AdminUsersQuery
{
    /**
     * Fetch paginated user accounts with optional search, role, and verification filtering.
     */
    public function paginate(?string $search = null, ?string $role = null, ?string $verified = null, int $perPage = 20): LengthAwarePaginator
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

        if ($verified === 'verified') {
            $query->whereNotNull('email_verified_at');
        } elseif ($verified === 'unverified') {
            $query->whereNull('email_verified_at');
        }

        return $query->paginate($perPage)->withQueryString();
    }
}
