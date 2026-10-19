<?php

namespace App\Queries;

use App\Models\Gallery;
use Illuminate\Contracts\Pagination\CursorPaginator;

class GalleryPhotoQuery
{
    /**
     * Retrieve cursor-paginated photos for a public gallery view.
     * Strictly enforces that hidden photos are excluded.
     */
    public function public(Gallery $gallery, int $perPage = 60, ?array $uuids = null): CursorPaginator
    {
        $perPage = max(1, min(100, $perPage));

        $query = $gallery->photos()->where('is_hidden', false);

        if (!empty($uuids)) {
            $query->whereIn('uuid', $uuids);
        }

        return $query
            ->orderBy('sort_date', 'asc')
            ->orderBy('sort_order', 'asc')
            ->orderBy('id', 'asc')
            ->cursorPaginate($perPage);
    }

    /**
     * Retrieve cursor-paginated photos for Studio management.
     * Includes both hidden and visible photos with deterministic sorting.
     */
    public function studio(Gallery $gallery, int $perPage = 50): CursorPaginator
    {
        $perPage = max(1, min(100, $perPage));

        return $gallery->photos()
            ->orderBy('sort_order', 'asc')
            ->orderBy('id', 'desc')
            ->cursorPaginate($perPage);
    }

    /**
     * Backwards-compatible alias for public pagination.
     */
    public function getPaginatedForGallery(Gallery $gallery, int $perPage = 60, ?array $uuids = null): CursorPaginator
    {
        return $this->public($gallery, $perPage, $uuids);
    }
}
