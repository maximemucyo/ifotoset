<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\GalleryResource;
use App\Http\Resources\V1\PhotoResource;
use App\Jobs\PurgeGalleryJob;
use App\Jobs\PurgePhotoJob;
use App\Models\Gallery;
use App\Models\Photo;
use App\Services\TrashService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TrashController extends Controller
{
    public function __construct(
        protected TrashService $trashService
    ) {}

    /**
     * Display a paginated listing of soft-deleted galleries or photos.
     * GET /api/v1/trash
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $type = $request->query('type', 'gallery');
        $perPage = $request->integer('per_page', 10);
        $perPage = max(1, min(100, $perPage));

        if ($type === 'photo') {
            $photos = Photo::onlyTrashed()
                ->whereHas('gallery', function ($query) use ($user) {
                    $query->where('user_id', $user->id)
                        ->whereNull('deleted_at');
                })
                ->with('gallery')
                ->orderBy('deleted_at', 'desc')
                ->paginate($perPage);

            return PhotoResource::collection($photos)->response();
        } else {
            $galleries = Gallery::onlyTrashed()
                ->where('user_id', $user->id)
                ->with(['stats', 'coverPhoto'])
                ->orderBy('deleted_at', 'desc')
                ->paginate($perPage);

            return GalleryResource::collection($galleries)->response();
        }
    }

    /**
     * Restore a soft-deleted gallery or photo.
     * POST /api/v1/trash/restore
     */
    public function restore(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => ['required', 'string', 'in:gallery,photo'],
            'uuid' => ['required', 'string'],
        ]);

        $user = $request->user();

        if ($validated['type'] === 'gallery') {
            $gallery = Gallery::onlyTrashed()
                ->where('uuid', $validated['uuid'])
                ->where('user_id', $user->id)
                ->firstOrFail();

            $this->trashService->restoreGallery($gallery);

            return response()->json([
                'message' => 'Gallery restored successfully.',
                'data' => new GalleryResource($gallery->load('stats')),
            ]);
        } else {
            $photo = Photo::onlyTrashed()
                ->where('uuid', $validated['uuid'])
                ->whereHas('gallery', function ($query) use ($user) {
                    $query->where('user_id', $user->id);
                })
                ->firstOrFail();

            $this->trashService->restorePhoto($photo);

            return response()->json([
                'message' => 'Photo restored successfully.',
                'data' => new PhotoResource($photo),
            ]);
        }
    }

    /**
     * Dispatch background jobs to permanently delete a soft-deleted gallery or photo.
     * DELETE /api/v1/trash/purge
     */
    public function purge(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => ['required', 'string', 'in:gallery,photo'],
            'uuid' => ['required', 'string'],
        ]);

        $user = $request->user();

        if ($validated['type'] === 'gallery') {
            $gallery = Gallery::onlyTrashed()
                ->where('uuid', $validated['uuid'])
                ->where('user_id', $user->id)
                ->firstOrFail();

            PurgeGalleryJob::dispatch($gallery->id);

            return response()->json([
                'message' => 'Gallery purge initiated in the background.',
            ], 202);
        } else {
            $photo = Photo::onlyTrashed()
                ->where('uuid', $validated['uuid'])
                ->whereHas('gallery', function ($query) use ($user) {
                    $query->where('user_id', $user->id);
                })
                ->firstOrFail();

            PurgePhotoJob::dispatch($photo->id);

            return response()->json([
                'message' => 'Photo purge initiated in the background.',
            ], 202);
        }
    }

    /**
     * Dispatch background jobs to empty all trash for the authenticated user.
     * POST /api/v1/trash/empty
     */
    public function empty(Request $request): JsonResponse
    {
        $user = $request->user();
        
        $this->trashService->emptyTrash($user);

        return response()->json([
            'message' => 'Trash empty process initiated in the background.',
        ], 202);
    }
}
