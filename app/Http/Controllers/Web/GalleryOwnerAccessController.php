<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Queries\GalleryQuery;
use App\Services\PublicUrlService;
use App\Support\GalleryOwnerAccess;
use App\Support\StudioAccount;
use App\Models\Gallery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

/**
 * Lets the photographer open their own client gallery without its PIN or invitation.
 *
 * Session cookies stay on the host that set them, so being logged in to the studio means
 * nothing on {username}.ifotoset.com. The studio link hands the browser over with a
 * short-lived signed URL, and the gallery host remembers the owner in its own session.
 */
class GalleryOwnerAccessController extends Controller
{
    /**
     * Studio side: check the gallery belongs to the studio being worked in, then send the browser to the gallery host.
     */
    public function open(string $uuid, PublicUrlService $urls): RedirectResponse
    {
        $gallery = Gallery::where('uuid', $uuid)->with('user')->firstOrFail();
        abort_unless(StudioAccount::current()?->belongsTo($gallery->user_id), 403);
        abort_if(blank($gallery->user?->username), 404);

        $path = URL::temporarySignedRoute('subdomain.gallery.owner', now()->addMinutes(2), [
            'username' => $gallery->user->username,
            'slug' => $gallery->slug,
            'account' => $gallery->user_id,
        ], absolute: false);

        return redirect()->away($urls->photographerUrl($gallery->user->username) . $path);
    }

    /**
     * Gallery host side: remember the owner in this host's session and show the gallery at its clean address.
     */
    public function grant(Request $request, string $username, string $slug, GalleryQuery $galleries): RedirectResponse
    {
        $gallery = $galleries->findForUser($username, $slug);

        // An old or tampered link just opens the gallery the way a client sees it
        if ($request->hasValidSignature(false) && (int) $request->query('account') === (int) $gallery->user_id) {
            GalleryOwnerAccess::grant($request, $gallery);
        }

        return redirect()->to($gallery->public_url);
    }
}
