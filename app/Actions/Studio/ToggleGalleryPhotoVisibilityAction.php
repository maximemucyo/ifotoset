<?php

namespace App\Actions\Studio;

use App\Models\Gallery;
use App\Models\Photo;
use Illuminate\Validation\ValidationException;

class ToggleGalleryPhotoVisibilityAction
{
    /**
     * Toggle or set the hidden status of a photo within a gallery.
     * Enforces that hidden photos cannot remain as the gallery cover.
     */
    public function execute(Gallery $gallery, Photo $photo, ?bool $hide = null): Photo
    {
        if ($photo->gallery_id !== $gallery->id) {
            abort(404, 'Photo does not belong to this gallery.');
        }

        $newHiddenState = $hide !== null ? $hide : !$photo->is_hidden;

        // Cover-photo invariant: A hidden photo must never be used as the public gallery cover
        if ($newHiddenState && $gallery->cover_photo_id === $photo->id) {
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

        $photo->update([
            'is_hidden' => $newHiddenState,
        ]);

        return $photo->fresh();
    }
}
