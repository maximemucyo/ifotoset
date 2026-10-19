<?php

namespace App\Actions\Studio;

use App\Models\Gallery;
use App\Models\Photo;

class DeleteGalleryPhotoAction
{
    /**
     * Soft-delete a photo and handle cover reassignment if necessary.
     */
    public function execute(Gallery $gallery, Photo $photo): bool
    {
        if ($photo->gallery_id !== $gallery->id) {
            abort(404, 'Photo does not belong to this gallery.');
        }

        // If the deleted photo was the cover, reassign to next available visible photo
        if ($gallery->cover_photo_id === $photo->id) {
            $replacementCover = $gallery->photos()
                ->where('is_hidden', false)
                ->where('id', '!=', $photo->id)
                ->orderBy('sort_date', 'asc')
                ->orderBy('sort_order', 'asc')
                ->orderBy('id', 'asc')
                ->first();

            $gallery->update([
                'cover_photo_id' => $replacementCover?->id,
            ]);
        }

        return (bool) $photo->delete();
    }
}
