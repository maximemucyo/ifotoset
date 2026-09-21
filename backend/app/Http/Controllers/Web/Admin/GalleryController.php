<?php

namespace App\Http\Controllers\Web\Admin;

use App\Actions\Admin\RestoreGalleryAction;
use App\Actions\Admin\TakeDownGalleryAction;
use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use App\Models\Gallery;
use App\Queries\Admin\AdminGalleriesQuery;
use App\Services\GalleryCachePurgeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class GalleryController extends Controller
{
    /**
     * Platform gallery overview & moderation index.
     */
    public function index(Request $request, AdminGalleriesQuery $query): View
    {
        $search = $request->input('search');
        $visibility = $request->input('visibility');

        $galleries = $query->paginate($search, $visibility, 20);

        return view('admin.galleries', [
            'galleries'  => $galleries,
            'search'     => $search,
            'visibility' => $visibility,
        ]);
    }

    /**
     * Preview any gallery in explicit Admin Moderation Mode.
     * Bypasses passwords, private invitations, and expiry while rendering a prominent moderation banner.
     */
    public function preview(Request $request, string $uuid): View
    {
        // Explicitly establish server-side admin moderation context
        $request->attributes->set('admin_moderation', true);

        $gallery = Gallery::where('uuid', $uuid)
            ->with(['user', 'stats', 'coverPhoto'])
            ->firstOrFail();

        $photos = $gallery->photos()
            ->whereNull('deleted_at')
            ->orderBy('sort_date', 'asc')
            ->orderBy('sort_order', 'asc')
            ->paginate(48);

        return view('admin.galleries.preview', [
            'gallery' => $gallery,
            'photographer' => $gallery->user,
            'photos' => $photos,
            'isAdminModeration' => true,
        ]);
    }

    /**
     * Toggle public/private visibility of a gallery.
     */
    public function toggleVisibility(Request $request, string $uuid, GalleryCachePurgeService $cachePurge): RedirectResponse
    {
        $admin = $request->user();
        $gallery = Gallery::where('uuid', $uuid)->firstOrFail();

        $oldVisibility = $gallery->visibility;
        $newVisibility = ($gallery->visibility === 'public') ? 'private' : 'public';
        $gallery->update(['visibility' => $newVisibility]);

        $cachePurge->purge($gallery);

        AdminAuditLog::record(
            $admin,
            'gallery.visibility_changed',
            'Gallery',
            $uuid,
            [
                'old' => $oldVisibility,
                'new' => $newVisibility,
                'title' => $gallery->title,
                'photographer_id' => $gallery->user_id,
            ]
        );

        return back()->with('success', "Gallery '{$gallery->title}' visibility set to {$newVisibility}.");
    }

    /**
     * Take down an infringing/inappropriate gallery with required reason.
     * Preserves owner's original visibility configuration.
     */
    public function takeDown(Request $request, string $uuid, TakeDownGalleryAction $action): RedirectResponse
    {
        $admin = $request->user();
        $gallery = Gallery::where('uuid', $uuid)->firstOrFail();

        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ]);

        try {
            $action->execute($admin, $gallery, $validated['reason']);
            return back()->with('success', "Gallery '{$gallery->title}' has been taken down for content moderation.");
        } catch (ValidationException $e) {
            return back()->with('error', $e->validator->errors()->first());
        }
    }

    /**
     * Restore a taken down gallery to normal moderation status.
     * Retains the photographer's original visibility untouched.
     */
    public function restore(Request $request, string $uuid, RestoreGalleryAction $action): RedirectResponse
    {
        $admin = $request->user();
        $gallery = Gallery::where('uuid', $uuid)->firstOrFail();

        $action->execute($admin, $gallery);

        return back()->with('success', "Gallery '{$gallery->title}' has been restored to normal moderation status.");
    }
}
