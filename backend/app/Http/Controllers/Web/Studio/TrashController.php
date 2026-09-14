<?php

namespace App\Http\Controllers\Web\Studio;

use App\Http\Controllers\Controller;
use App\Models\Gallery;
use App\Services\TrashService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TrashController extends Controller
{
    public function __construct(
        protected TrashService $trashService
    ) {}

    /**
     * List trashed galleries.
     */
    public function index(Request $request): View
    {
        $user = $request->user();

        $trashedGalleries = Gallery::onlyTrashed()
            ->where('user_id', $user->id)
            ->withCount('photos')
            ->orderBy('deleted_at', 'desc')
            ->paginate(15);

        return view('studio.trash.index', [
            'galleries' => $trashedGalleries,
        ]);
    }

    /**
     * Restore a trashed gallery.
     */
    public function restore(Request $request): RedirectResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'uuid' => ['required', 'string'],
        ]);

        $gallery = Gallery::onlyTrashed()
            ->where('uuid', $validated['uuid'])
            ->where('user_id', $user->id)
            ->firstOrFail();

        $this->trashService->restoreGallery($gallery);

        return back()->with('success', "Gallery '{$gallery->title}' has been restored successfully.");
    }

    /**
     * Permanently delete a gallery from trash.
     */
    public function purge(Request $request): RedirectResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'uuid' => ['required', 'string'],
        ]);

        $gallery = Gallery::onlyTrashed()
            ->where('uuid', $validated['uuid'])
            ->where('user_id', $user->id)
            ->firstOrFail();

        $title = $gallery->title;
        $this->trashService->purgeGallery($gallery->id);

        return back()->with('success', "Gallery '{$title}' and its photos have been permanently removed.");
    }

    /**
     * Empty all trash for the photographer.
     */
    public function empty(Request $request): RedirectResponse
    {
        $user = $request->user();

        $this->trashService->emptyTrash($user);

        return back()->with('success', 'Trash cleanup initiated. Trashed galleries and photos will be permanently deleted.');
    }
}
