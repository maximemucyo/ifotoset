<?php

namespace App\Queries;

use App\Models\Gallery;
use Illuminate\Database\Eloquent\Builder;

class GalleryQuery
{
    /**
     * Find a public gallery by its owner's username and slug.
     * Slugs are only unique per photographer, so the username is required.
     * Galleries of suspended or deleted accounts are never resolved.
     */
    public function findForUser(string $username, string $slug): Gallery
    {
        return $this->publicQuery($username, $slug)
            ->with(['stats', 'coverPhoto', 'user'])
            ->firstOrFail();
    }

    /**
     * Same lookup including soft-deleted galleries, so callers can tell
     * "removed" (410) apart from "never existed" (404).
     */
    public function findTrashedForUser(string $username, string $slug): ?Gallery
    {
        return $this->publicQuery($username, $slug)->onlyTrashed()->first();
    }

    private function publicQuery(string $username, string $slug): Builder
    {
        return Gallery::query()
            ->where('slug', $slug)
            ->whereHas('user', fn (Builder $q) => $q
                ->where('username', strtolower($username))
                ->where('is_active', true));
    }
}
