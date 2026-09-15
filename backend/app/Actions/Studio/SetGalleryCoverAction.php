<?php

namespace App\Actions\Studio;

use App\Models\Gallery;
use App\Models\Photo;
use Illuminate\Validation\ValidationException;

class SetGalleryCoverAction
{
    /**
     * Set a photo as the gallery cover.
     * Enforces that hidden photos cannot be chosen as cover.
     */
    public function execute(Gallery $gallery, Photo $photo): Gallery
    {
        if ($photo->gallery_id !== $gallery->id) {
            abort(404, 'Photo does not belong to this gallery.');
        }

        if ($photo->is_hidden) {
            throw ValidationException::withMessages([
                'photo' => 'A hidden photo cannot be set as the gallery cover.',
            ]);
        }

        $gallery->update([
            'cover_photo_id' => $photo->id,
        ]);

        return $gallery->fresh(['coverPhoto']);
    }
}
