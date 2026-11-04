<?php

namespace App\Http\Controllers\Web\Studio;

use App\Http\Controllers\Controller;
use App\Models\Gallery;
use App\Models\GalleryShareLink;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Quick share links: make a link to the selected photos, and delete it to stop it working.
 */
class GalleryShareLinkController extends Controller
{
    public function store(Request $request, string $uuid): JsonResponse
    {
        $gallery = $this->gallery($uuid);
        $validated = $request->validate([
            'photos' => ['required', 'array', 'min:1', 'max:' . GalleryShareLink::MAX_PHOTOS],
            'photos.*' => ['string', 'max:64'],
        ], [
            'photos.max' => 'A link can have up to ' . GalleryShareLink::MAX_PHOTOS . ' photos.',
        ]);

        $photoIds = $gallery->photos()->whereIn('uuid', $validated['photos'])->pluck('id');
        if ($photoIds->isEmpty()) {
            return response()->json(['message' => 'Select photos from this gallery.'], 422);
        }

        $link = $gallery->shareLinks()->create(['created_by' => $request->user()?->id]);
        $link->photos()->attach($photoIds);

        return response()->json([
            'message' => 'Link copied',
            'url' => $link->url(),
            'count' => $photoIds->count(),
        ]);
    }

    public function destroy(string $uuid, int $id): RedirectResponse
    {
        $gallery = $this->gallery($uuid);
        $gallery->shareLinks()->whereKey($id)->firstOrFail()->delete();

        return back()->with('success', 'Link deleted. It no longer opens.');
    }

    private function gallery(string $uuid): Gallery
    {
        $gallery = Gallery::where('uuid', $uuid)->firstOrFail();
        $this->authorize('update', $gallery);

        return $gallery;
    }
}
