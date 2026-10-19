<?php

namespace App\Services;

use App\Models\Gallery;
use App\Models\Photo;
use Illuminate\Support\Facades\DB;

class GalleryCoverService
{
    /**
     * Set automatic cover photo for a gallery if none is set.
     */
    public function setAutoCover(Gallery $gallery): void
    {
        DB::transaction(function () use ($gallery) {
            // Lock the gallery row for update to ensure concurrency safety
            $gallery = Gallery::where('id', $gallery->id)->lockForUpdate()->first();
            if (!$gallery) {
                return;
            }

            if ($gallery->has_explicit_cover) {
                return;
            }

            // Check if current cover is already set and valid (not deleted and not hidden)
            if ($gallery->cover_photo_id) {
                $currentCoverValid = Photo::where('id', $gallery->cover_photo_id)
                    ->where('gallery_id', $gallery->id)
                    ->where('is_hidden', false)
                    ->whereNull('deleted_at')
                    ->exists();

                if ($currentCoverValid) {
                    return;
                }
            }

            // Select the first non-deleted, visible photo in the gallery
            $firstPhoto = Photo::where('gallery_id', $gallery->id)
                ->where('is_hidden', false)
                ->whereNull('deleted_at')
                ->orderBy('sort_order', 'asc')
                ->orderBy('id', 'asc')
                ->first();

            $gallery->update([
                'cover_photo_id' => $firstPhoto ? $firstPhoto->id : null,
            ]);
        });
    }

    /**
     * Explicitly set a cover photo for a gallery.
     */
    public function setExplicitCover(Gallery $gallery, Photo $photo): void
    {
        if ($photo->is_hidden) {
            throw new \InvalidArgumentException('A hidden photo cannot be set as the gallery cover.');
        }

        DB::transaction(function () use ($gallery, $photo) {
            $gallery = Gallery::where('id', $gallery->id)->lockForUpdate()->first();
            if (!$gallery) {
                return;
            }

            $gallery->update([
                'cover_photo_id' => $photo->id,
                'has_explicit_cover' => true,
            ]);
        });
    }

    /**
     * Clear explicit cover photo, reverting to automatic cover selection.
     */
    public function clearExplicitCover(Gallery $gallery): void
    {
        DB::transaction(function () use ($gallery) {
            $gallery = Gallery::where('id', $gallery->id)->lockForUpdate()->first();
            if (!$gallery) {
                return;
            }

            $gallery->update([
                'cover_photo_id' => null,
                'has_explicit_cover' => false,
            ]);

            // Immediately set the auto cover fallback
            $this->setAutoCover($gallery);
        });
    }

    /**
     * Handles photo deletion and adjusts cover selection if necessary.
     */
    public function handlePhotoDeletion(Gallery $gallery, Photo $deletedPhoto): void
    {
        DB::transaction(function () use ($gallery, $deletedPhoto) {
            $gallery = Gallery::where('id', $gallery->id)->lockForUpdate()->first();
            if (!$gallery) {
                return;
            }

            if ((int) $gallery->cover_photo_id === (int) $deletedPhoto->id) {
                // Find the next available non-deleted, visible photo
                $nextPhoto = Photo::where('gallery_id', $gallery->id)
                    ->where('is_hidden', false)
                    ->whereNull('deleted_at')
                    ->where('id', '!=', $deletedPhoto->id)
                    ->orderBy('sort_order', 'asc')
                    ->orderBy('id', 'asc')
                    ->first();

                $gallery->update([
                    'cover_photo_id' => $nextPhoto ? $nextPhoto->id : null,
                    'has_explicit_cover' => false,
                ]);
            }
        });
    }

    /**
     * Handles photo hiding: if the hidden photo was the cover, selects another visible photo.
     */
    public function handlePhotoHidden(Gallery $gallery, Photo $hiddenPhoto): void
    {
        DB::transaction(function () use ($gallery, $hiddenPhoto) {
            $gallery = Gallery::where('id', $gallery->id)->lockForUpdate()->first();
            if (!$gallery) {
                return;
            }

            if ((int) $gallery->cover_photo_id === (int) $hiddenPhoto->id) {
                $nextPhoto = Photo::where('gallery_id', $gallery->id)
                    ->where('is_hidden', false)
                    ->whereNull('deleted_at')
                    ->where('id', '!=', $hiddenPhoto->id)
                    ->orderBy('sort_order', 'asc')
                    ->orderBy('id', 'asc')
                    ->first();

                $gallery->update([
                    'cover_photo_id' => $nextPhoto ? $nextPhoto->id : null,
                    'has_explicit_cover' => false,
                ]);
            }
        });
    }
}
?>
