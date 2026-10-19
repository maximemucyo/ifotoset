<?php

namespace App\Queries\Admin;

use App\Models\Gallery;
use Illuminate\Pagination\LengthAwarePaginator;

class AdminGalleriesQuery
{
    /**
     * Fetch platform-wide client galleries with optional search and visibility filters.
     */
    public function paginate(?string $search = null, ?string $visibility = null, int $perPage = 20): LengthAwarePaginator
    {
        $query = Gallery::with(['user', 'stats', 'coverPhoto'])
            ->orderBy('created_at', 'desc');

        if (! empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($uq) use ($search) {
                        $uq->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%")
                            ->orWhere('username', 'like', "%{$search}%");
                    });
            });
        }

        if (! empty($visibility) && in_array($visibility, ['public', 'password', 'private'], true)) {
            $query->where('visibility', $visibility);
        }

        return $query->paginate($perPage)->withQueryString();
    }
}
