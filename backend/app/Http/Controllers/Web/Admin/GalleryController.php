<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use App\Models\Gallery;
use App\Queries\Admin\AdminGalleriesQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
     * Toggle public/private visibility of a gallery.
     */
    public function toggleVisibility(Request $request, string $uuid): RedirectResponse
    {
        $admin = $request->user();
        $gallery = Gallery::where('uuid', $uuid)->firstOrFail();

        $oldVisibility = $gallery->visibility;
        $newVisibility = ($gallery->visibility === 'public') ? 'private' : 'public';
        $gallery->update(['visibility' => $newVisibility]);

        AdminAuditLog::record(
            $admin,
            'gallery.visibility_changed',
            'Gallery',
            $uuid,
            ['old' => $oldVisibility, 'new' => $newVisibility, 'title' => $gallery->title]
        );

        return back()->with('success', "Gallery '{$gallery->title}' set to {$newVisibility}.");
    }

    /**
     * Take down an infringing/inappropriate gallery immediately (sets to private and archives).
     */
    public function takeDown(Request $request, string $uuid): RedirectResponse
    {
        $admin = $request->user();
        $gallery = Gallery::where('uuid', $uuid)->firstOrFail();

        $gallery->update(['visibility' => 'private']);

        AdminAuditLog::record(
            $admin,
            'gallery.taken_down',
            'Gallery',
            $uuid,
            ['title' => $gallery->title, 'photographer_id' => $gallery->user_id]
        );

        return back()->with('success', "Gallery '{$gallery->title}' has been taken down and made private.");
    }
}
