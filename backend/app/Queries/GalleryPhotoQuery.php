<?php

namespace App\Queries;

use App\Models\Gallery;
use Illuminate\Contracts\Pagination\CursorPaginator;

class GalleryPhotoQuery
{
    /**
     * Retrieve cursor-paginated photos for a gallery with deterministic sorting.
     * Optionally filtered by specific photo UUIDs (e.g. for server-side favorites).
     */
    public function getPaginatedForGallery(Gallery $gallery, int $perPage = 60, ?array $uuids = null): CursorPaginator
    {
        $perPage = max(1, min(100, $perPage));

        $query = $gallery->photos();

        if (!empty($uuids)) {
            $query->whereIn('uuid', $uuids);
        }

        return $query
            ->orderBy('sort_date', 'asc')
            ->orderBy('sort_order', 'asc')
            ->orderBy('id', 'asc')
            ->cursorPaginate($perPage);
    }
}
