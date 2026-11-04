<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\GalleryShareLink;
use App\Models\Photo;
use App\Queries\GalleryPhotoQuery;
use App\Support\PhotoDownload;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Quick share links: the photos a photographer picked, opened with the link alone.
 */
class ShareLinkController extends Controller
{
    public function __construct(protected GalleryPhotoQuery $photoQuery) {}

    public function show(string $token): View
    {
        $link = $this->link($token);
        $gallery = $link->gallery;

        $link->increment('views_count', 1, ['last_viewed_at' => now()]);

        $photos = $this->photoQuery->applyOrder(
            $gallery->photos()->whereIn('id', $link->photos()->select('photos.id'))->where('is_hidden', false),
            $gallery
        )->get();

        return view('public.share-link', [
            'link' => $link,
            'gallery' => $gallery,
            'photographer' => $gallery->user,
            'photos' => $photos,
            'canDownload' => $this->canDownload($link),
        ]);
    }

    public function download(Request $request, string $token, string $uuid)
    {
        $link = $this->link($token);
        abort_unless($this->canDownload($link), 403);

        $photo = $link->photos()->where('photos.uuid', $uuid)->where('photos.is_hidden', false)->firstOrFail();
        $gallery = $link->gallery;

        $gallery->stats()->increment('downloads_count');
        DB::table('activity_logs')->insert([
            'gallery_id' => $gallery->id,
            'event' => 'photo_downloaded',
            'visitor_session_id' => session()->isStarted() ? session()->getId() : null,
            'source' => 'share_link',
            'referrer' => $request->header('referer'),
            'properties' => json_encode([
                'photo_uuid' => $photo->uuid,
                'filename' => $photo->original_filename,
                'share_link_id' => $link->id,
                'action' => 'download',
            ]),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'created_at' => now(),
        ]);

        return PhotoDownload::response($photo);
    }

    private function link(string $token): GalleryShareLink
    {
        $link = GalleryShareLink::where('token', $token)->with('gallery.user')->first();
        abort_unless($link?->isOpen(), 404);

        return $link;
    }

    /**
     * The gallery's download choice applies: with a Download PIN the link only shows the photos.
     */
    private function canDownload(GalleryShareLink $link): bool
    {
        return $link->gallery->photoDownloadsAllowed() && !$link->gallery->requiresDownloadPin();
    }
}
