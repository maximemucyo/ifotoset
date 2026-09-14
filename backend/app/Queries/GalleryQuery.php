<?php

namespace App\Queries;

use App\Models\Gallery;

class GalleryQuery
{
    /**
     * Find a public gallery by slug with essential relations.
     */
    public function findBySlug(string $slug): Gallery
    {
        return Gallery::where('slug', $slug)
            ->with(['stats', 'coverPhoto', 'user'])
            ->firstOrFail();
    }
}
