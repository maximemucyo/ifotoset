<?php

namespace App\Http\Controllers\Web\Studio;

use App\Http\Controllers\Controller;
use App\Models\Gallery;
use App\Models\Photo;
use App\Services\StorageStatisticsService;
use App\Services\TrashService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TrashController extends Controller
{
    public function __construct(
        protected TrashService $trashService,
        protected StorageStatisticsService $storageStatisticsService
    ) {}

    /**
     * List trashed galleries and individually trashed photos.
     */
    public function index(Request $request): View
    {
        $user = $request->user();
        $tab = $request->query('tab', 'galleries');
        if (!in_array($tab, ['galleries', 'photos'], true)) {
            $tab = 'galleries';
        }

        $trashedGalleries = Gallery::onlyTrashed()
            ->where('user_id', $user->id)
            ->withCount('photos')
            ->orderBy('deleted_at', 'desc')
            ->paginate(15, ['*'], 'galleries_page');

        // Only show photos whose parent gallery is NOT soft-deleted (to avoid duplicate display)
        $trashedPhotos = Photo::onlyTrashed()
            ->whereHas('gallery', function ($query) use ($user) {
                $query->where('user_id', $user->id)
                    ->whereNull('deleted_at');
            })
            ->with('gallery')
            ->orderBy('deleted_at', 'desc')
            ->paginate(15, ['*'], 'photos_page');

        $storageStats = $this->storageStatisticsService->getPhotographerStorageBreakdown($user);
        $trashBytes = (int) ($storageStats['trash_bytes'] ?? 0);
        $retentionDays = (int) config('filesystems.trash_retention_days', 7);
        $totalItems = $trashedGalleries->total() + $trashedPhotos->total();

        return view('studio.trash.index', [
            'tab' => $tab,
            'galleries' => $trashedGalleries,
            'photos' => $trashedPhotos,
            'totalItems' => $totalItems,
            'trashBytes' => $trashBytes,
            'retentionDays' => $retentionDays,
        ]);
    }

    /**
     * Restore a trashed gallery or individual photo.
     */
    public function restore(Request $request): RedirectResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'uuid' => ['required', 'string'],
            'type' => ['nullable', 'string', 'in:gallery,photo'],
        ]);

        $type = $validated['type'] ?? null;

        if ($type === 'photo') {
            $photo = Photo::onlyTrashed()
                ->where('uuid', $validated['uuid'])
                ->whereHas('gallery', fn($q) => $q->where('user_id', $user->id))
                ->firstOrFail();

            $this->trashService->restorePhoto($photo);
            return back()->with('success', 'Photo restored successfully.');
        }

        if ($type === 'gallery') {
            $gallery = Gallery::onlyTrashed()
                ->where('uuid', $validated['uuid'])
                ->where('user_id', $user->id)
                ->firstOrFail();

            $this->trashService->restoreGallery($gallery);
            return back()->with('success', "Gallery '{$gallery->title}' restored successfully.");
        }

        // Fallback auto-detection if type was not provided
        $gallery = Gallery::onlyTrashed()
            ->where('uuid', $validated['uuid'])
            ->where('user_id', $user->id)
            ->first();

        if ($gallery) {
            $this->trashService->restoreGallery($gallery);
            return back()->with('success', "Gallery '{$gallery->title}' restored successfully.");
        }

        $photo = Photo::onlyTrashed()
            ->where('uuid', $validated['uuid'])
            ->whereHas('gallery', fn($q) => $q->where('user_id', $user->id))
            ->firstOrFail();

        $this->trashService->restorePhoto($photo);
        return back()->with('success', 'Photo restored successfully.');
    }

    /**
     * Permanently delete a gallery or individual photo from trash.
     */
    public function purge(Request $request): RedirectResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'uuid' => ['required', 'string'],
            'type' => ['nullable', 'string', 'in:gallery,photo'],
        ]);

        $type = $validated['type'] ?? null;

        if ($type === 'photo') {
            $photo = Photo::onlyTrashed()
                ->where('uuid', $validated['uuid'])
                ->whereHas('gallery', fn($q) => $q->where('user_id', $user->id))
                ->firstOrFail();

            $this->trashService->purgePhoto($photo->id);
            return back()->with('success', 'Photo permanently deleted from cloud storage.');
        }

        if ($type === 'gallery') {
            $gallery = Gallery::onlyTrashed()
                ->where('uuid', $validated['uuid'])
                ->where('user_id', $user->id)
                ->firstOrFail();

            $title = $gallery->title;
            $this->trashService->purgeGallery($gallery->id);
            return back()->with('success', "Gallery '{$title}' and its photos permanently deleted from cloud storage.");
        }

        // Fallback auto-detection if type was not provided
        $gallery = Gallery::onlyTrashed()
            ->where('uuid', $validated['uuid'])
            ->where('user_id', $user->id)
            ->first();

        if ($gallery) {
            $title = $gallery->title;
            $this->trashService->purgeGallery($gallery->id);
            return back()->with('success', "Gallery '{$title}' and its photos permanently deleted from cloud storage.");
        }

        $photo = Photo::onlyTrashed()
            ->where('uuid', $validated['uuid'])
            ->whereHas('gallery', fn($q) => $q->where('user_id', $user->id))
            ->firstOrFail();

        $this->trashService->purgePhoto($photo->id);
        return back()->with('success', 'Photo permanently deleted from cloud storage.');
    }

    /**
     * Empty all trash for the photographer.
     */
    public function empty(Request $request): RedirectResponse
    {
        $user = $request->user();

        $this->trashService->emptyTrash($user);

        return back()->with('success', 'All trash emptied. Trashed galleries, photos, and variant files permanently removed from cloud storage.');
    }
}
