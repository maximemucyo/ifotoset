<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\Visibility;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\GalleryResource;
use App\Models\Gallery;
use App\Models\GalleryStats;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GalleryController extends Controller
{
    /**
     * List authenticated photographer's galleries.
     * GET /api/v1/galleries
     */
    public function index(Request $request): JsonResponse
    {
        $galleries = Gallery::where('user_id', $request->user()->id)
            ->with(['stats', 'coverPhoto'])
            ->orderBy('created_at', 'desc')
            ->paginate($request->integer('per_page', 20));

        return GalleryResource::collection($galleries)->response();
    }

    /**
     * Create a new gallery with per-user unique slug handling.
     * POST /api/v1/galleries
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255'],
            'client_name' => ['nullable', 'string', 'max:255'],
            'event_date' => ['nullable', 'date'],
            'visibility' => ['nullable', 'string', 'in:public,private'],
            'password' => ['nullable', 'string', 'min:6'],
            'password_hint' => ['nullable', 'string', 'max:255'],
            'expires_at' => ['nullable', 'date', 'after:now'],
        ]);

        $user = $request->user();

        // Ensure slug is unique for this specific photographer
        $slug = $validated['slug'];
        $count = Gallery::where('user_id', $user->id)->where('slug', $slug)->count();
        if ($count > 0) {
            $slug = "{$slug}-" . time();
        }

        $gallery = Gallery::create([
            'user_id' => $user->id,
            'title' => $validated['title'],
            'slug' => $slug,
            'client_name' => $validated['client_name'] ?? null,
            'event_date' => $validated['event_date'] ?? null,
            'visibility' => $validated['visibility'] ?? Visibility::Public->value,
            'password_hash' => !empty($validated['password']) ? bcrypt($validated['password']) : null,
            'password_hint' => $validated['password_hint'] ?? null,
            'expires_at' => $validated['expires_at'] ?? null,
            'version' => 1,
        ]);

        // Create materialized stats row
        GalleryStats::create(['gallery_id' => $gallery->id]);

        return response()->json(new GalleryResource($gallery->load('stats')), 201);
    }

    /**
     * Display gallery details.
     * GET /api/v1/galleries/{uuid}
     */
    public function show(Request $request, string $uuid): JsonResponse
    {
        $gallery = Gallery::where('uuid', $uuid)
            ->where('user_id', $request->user()->id)
            ->with(['stats', 'coverPhoto', 'photos'])
            ->firstOrFail();

        return response()->json(new GalleryResource($gallery));
    }

    /**
     * Update gallery with optimistic locking version protection.
     * PATCH /api/v1/galleries/{uuid}
     */
    public function update(Request $request, string $uuid): JsonResponse
    {
        $gallery = Gallery::where('uuid', $uuid)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $validated = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'client_name' => ['nullable', 'string', 'max:255'],
            'visibility' => ['sometimes', 'string', 'in:public,private'],
            'version' => ['required', 'integer'],
        ]);

        // Optimistic locking check
        if ((int) $validated['version'] !== $gallery->version) {
            return response()->json([
                'code' => 'CONCURRENCY_CONFLICT',
                'message' => 'The gallery was updated in another session. Please reload before saving changes.',
            ], 409);
        }

        $gallery->update([
            ...$validated,
            'version' => $gallery->version + 1,
        ]);

        return response()->json(new GalleryResource($gallery->load('stats')));
    }

    /**
     * Soft-delete a gallery.
     * DELETE /api/v1/galleries/{uuid}
     */
    public function destroy(Request $request, string $uuid): JsonResponse
    {
        $gallery = Gallery::where('uuid', $uuid)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $gallery->delete();

        return response()->json(null, 204);
    }
}
?>
