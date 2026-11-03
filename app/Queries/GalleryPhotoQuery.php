<?php

namespace App\Queries;

use App\Models\Gallery;
use App\Models\GallerySet;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GalleryPhotoQuery
{
    /**
     * Retrieve cursor-paginated photos for a public gallery view.
     * Strictly enforces that hidden photos are excluded. A set narrows it to that part of the gallery.
     */
    public function public(Gallery $gallery, int $perPage = 60, ?array $uuids = null, ?GallerySet $set = null): CursorPaginator
    {
        $perPage = max(1, min(100, $perPage));

        $query = $gallery->photos()->where('is_hidden', false);

        if (!empty($uuids)) {
            $query->whereIn('uuid', $uuids);
        }
        if ($set) {
            $query->where('set_id', $set->id);
        }

        return $this->applyOrder($query, $gallery)->cursorPaginate($perPage);
    }

    /**
     * Retrieve cursor-paginated photos for Studio management.
     * Includes hidden photos, in the same order clients see.
     */
    public function studio(Gallery $gallery, int $perPage = 50, ?GallerySet $set = null, ?string $search = null): CursorPaginator
    {
        $perPage = max(1, min(100, $perPage));

        $query = $gallery->photos();
        if ($set) {
            $query->where('set_id', $set->id);
        }
        // Part of a file name, for when a client asks for "IMG_0412"
        if (($search = trim((string) $search)) !== '') {
            $query->where('original_filename', 'like', '%' . addcslashes($search, '%_\\') . '%');
        }

        return $this->applyOrder($query, $gallery)->cursorPaginate($perPage);
    }

    /**
     * Sets that clients can open: those with at least one photo they can see.
     *
     * @return \Illuminate\Support\Collection<int, GallerySet>
     */
    public function publicSets(Gallery $gallery): \Illuminate\Support\Collection
    {
        $visible = fn ($q) => $q->where('is_hidden', false);

        return $gallery->sets()->whereHas('photos', $visible)->withCount(['photos as visible_count' => $visible])->get();
    }

    /**
     * Order photos the way the photographer chose for this gallery.
     * Every branch ends on `id` so pagination cursors stay deterministic.
     */
    public function applyOrder(HasMany $query, Gallery $gallery): HasMany
    {
        return match ($gallery->photo_order) {
            'filename' => $query->orderBy('sort_name', 'asc')->orderBy('id', 'asc'),
            'uploaded' => $query->orderBy('id', 'asc'),
            // Date taken (falls back to upload time), then camera file number within the same second
            default => $query->orderBy('sort_date', 'asc')->orderBy('sort_name', 'asc')->orderBy('id', 'asc'),
        };
    }

    /**
     * Backwards-compatible alias for public pagination.
     */
    public function getPaginatedForGallery(Gallery $gallery, int $perPage = 60, ?array $uuids = null, ?GallerySet $set = null): CursorPaginator
    {
        return $this->public($gallery, $perPage, $uuids, $set);
    }
}
