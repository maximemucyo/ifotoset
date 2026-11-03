<?php

namespace App\Http\Controllers\Web\Studio;

use App\Http\Controllers\Controller;
use App\Models\Gallery;
use App\Models\GallerySet;
use App\Models\Photo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Sets inside a gallery ("Ceremony", "Reception"): add, rename, delete, reorder and move photos between them.
 * Every action answers with the gallery's sets and their photo counts, so the rail can redraw from one source.
 */
class GallerySetController extends Controller
{
    public const MAX_SETS = 30;

    public function store(Request $request, string $uuid): JsonResponse
    {
        $gallery = $this->gallery($uuid);
        $validated = $request->validate(['name' => ['required', 'string', 'max:80']]);

        if ($gallery->sets()->count() >= self::MAX_SETS) {
            return response()->json(['message' => 'A gallery can have up to ' . self::MAX_SETS . ' sets.'], 422);
        }

        $set = $gallery->sets()->create([
            'name' => trim($validated['name']),
            'sort_order' => (int) $gallery->sets()->max('sort_order') + 1,
        ]);

        return $this->respond($gallery, 'Set added', ['set' => $set->uuid]);
    }

    public function update(Request $request, string $uuid, string $setUuid): JsonResponse
    {
        $gallery = $this->gallery($uuid);
        $set = $this->set($gallery, $setUuid);
        $validated = $request->validate(['name' => ['required', 'string', 'max:80']]);

        $set->update(['name' => trim($validated['name'])]);

        return $this->respond($gallery, 'Set renamed');
    }

    /**
     * Deleting a set keeps its photos: they stay in the gallery without a set.
     */
    public function destroy(string $uuid, string $setUuid): JsonResponse
    {
        $gallery = $this->gallery($uuid);
        $set = $this->set($gallery, $setUuid);

        DB::transaction(function () use ($set) {
            $set->photos()->update(['set_id' => null]);
            $set->delete();
        });

        return $this->respond($gallery, 'Set deleted. Its photos are still in the gallery.');
    }

    public function reorder(Request $request, string $uuid): JsonResponse
    {
        $gallery = $this->gallery($uuid);
        $validated = $request->validate([
            'sets' => ['required', 'array', 'max:' . self::MAX_SETS],
            'sets.*' => ['string', 'max:64'],
        ]);

        $sets = $gallery->sets()->get()->keyBy('uuid');
        DB::transaction(function () use ($validated, $sets) {
            foreach (array_values($validated['sets']) as $position => $setUuid) {
                $sets->get($setUuid)?->update(['sort_order' => $position + 1]);
            }
        });

        return $this->respond($gallery, 'Order saved');
    }

    /**
     * Move photos into a set, or out of every set when `set` is empty.
     */
    public function movePhotos(Request $request, string $uuid): JsonResponse
    {
        $gallery = $this->gallery($uuid);
        $validated = $request->validate([
            'photos' => ['required', 'array', 'max:2000'],
            'photos.*' => ['string', 'max:64'],
            'set' => ['nullable', 'string', 'max:64'],
        ]);

        $set = empty($validated['set']) ? null : $this->set($gallery, $validated['set']);
        $moved = Photo::where('gallery_id', $gallery->id)
            ->whereIn('uuid', $validated['photos'])
            ->update(['set_id' => $set?->id]);

        $message = $set
            ? ($moved === 1 ? "1 photo moved to {$set->name}" : "{$moved} photos moved to {$set->name}")
            : ($moved === 1 ? '1 photo removed from its set' : "{$moved} photos removed from their sets");

        return $this->respond($gallery, $message, ['moved' => $moved]);
    }

    private function gallery(string $uuid): Gallery
    {
        $gallery = Gallery::where('uuid', $uuid)->firstOrFail();
        $this->authorize('update', $gallery);

        return $gallery;
    }

    private function set(Gallery $gallery, string $setUuid): GallerySet
    {
        return GallerySet::where('gallery_id', $gallery->id)->where('uuid', $setUuid)->firstOrFail();
    }

    private function respond(Gallery $gallery, string $message, array $extra = []): JsonResponse
    {
        // Set chips are part of the cached client page, and none of this touches the gallery row
        app(\App\Services\GalleryCachePurgeService::class)->queuePhotographer($gallery->user);

        return response()->json(['message' => $message, 'sets' => self::summary($gallery)] + $extra);
    }

    /**
     * The gallery's sets for the studio rail, with how many photos each holds (hidden ones included).
     *
     * @return list<array{uuid: string, name: string, count: int}>
     */
    public static function summary(Gallery $gallery): array
    {
        return $gallery->sets()->withCount('photos')->get()
            ->map(fn (GallerySet $set) => ['uuid' => $set->uuid, 'name' => $set->name, 'count' => $set->photos_count])
            ->values()
            ->all();
    }
}
