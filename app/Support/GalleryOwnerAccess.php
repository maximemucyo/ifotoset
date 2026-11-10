<?php

namespace App\Support;

use App\Models\Gallery;
use Illuminate\Http\Request;

/**
 * The gallery host's memory that this browser belongs to the gallery's photographer,
 * set by GalleryOwnerAccessController from a signed studio link.
 */
class GalleryOwnerAccess
{
    public const HOURS = 12;

    public static function grant(Request $request, Gallery $gallery): void
    {
        $request->session()->put(self::key($gallery), [
            'account' => (int) $gallery->user_id,
            'until' => now()->addHours(self::HOURS)->getTimestamp(),
        ]);
    }

    public static function viewing(Gallery $gallery, Request $request): bool
    {
        if (!$request->hasSession()) {
            return false;
        }

        $grant = $request->session()->get(self::key($gallery));

        return is_array($grant)
            && (int) ($grant['account'] ?? 0) === (int) $gallery->user_id
            && (int) ($grant['until'] ?? 0) > now()->getTimestamp();
    }

    private static function key(Gallery $gallery): string
    {
        return "gallery_owner_{$gallery->id}";
    }
}
