<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Gallery;
use App\Models\Photo;
use App\Queries\GalleryPhotoQuery;
use App\Support\EdgeCache;
use App\Support\GalleryOwnerAccess;
use App\Queries\GalleryQuery;
use App\Traits\VerifiesGalleryAccess;
use App\ValueObjects\GalleryAccessDecision;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;

class PublicGalleryController extends Controller
{
    use VerifiesGalleryAccess;

    public function __construct(
        protected GalleryQuery $galleryQuery,
        protected GalleryPhotoQuery $photoQuery
    ) {}

    /**
     * Display public gallery view with server-rendered initial batch.
     */
    public function show(Request $request, string $username, string $slug): View|RedirectResponse
    {
        $gallery = $this->resolveGallery($username, $slug);
        Photo::showWatermarkFor($gallery);

        $gallery->loadMissing(['coverPhoto', 'stats', 'user.plan']);

        // Check for invitation link and execute canonical redirect if valid
        $rawInvite = (string) $request->query('invite');
        if (!empty($rawInvite)) {
            $accepted = $this->acceptInvitation($gallery, $rawInvite, $request);
            if ($accepted) {
                // Canonical redirect: 302 to URL without secret token in query string
                return redirect()->to($request->url(), 302);
            }
        }

        // The photographer's own visit, handed over from the studio: no PIN, and not counted as a client view
        $viewingAsOwner = GalleryOwnerAccess::viewing($gallery, $request);

        // Only galleries anyone with the link can open are shared from Cloudflare's cache
        if (!$gallery->hasPublicLink() || $viewingAsOwner) {
            EdgeCache::skip($request);
        }
        // A cached page is shared, so its view is counted by a beacon from the browser instead
        $trackViewInBrowser = EdgeCache::eligible($request);

        // Pure evaluation of access decision
        $decision = $this->evaluateGalleryAccess($gallery, $request);
        $downloadsUnlocked = $this->downloadsUnlocked($gallery, $request);

        $initialPhotos = collect();
        $nextCursor = null;
        $hasMore = false;
        $deepLinkedPhoto = null;
        $cover = $this->coverViewData(null, $gallery);
        $sets = collect();
        $activeSet = null;

        // Zero-leak security invariant: only query and hydrate photo data when access is granted
        if ($decision->isGranted()) {
            [$sets, $activeSet] = $this->setsFor($gallery, $request);
            $paginated = $this->photoQuery->getPaginatedForGallery($gallery, 24, null, $activeSet);
            $initialPhotos = collect($paginated->items());
            $nextCursor = $paginated->nextCursor()?->encode();
            $hasMore = $paginated->hasMorePages();

            // Pre-resolve deep-linked photo if requested (?photo=UUID)
            $photoUuid = $request->query('photo');
            if ($photoUuid) {
                $foundInBatch = $initialPhotos->firstWhere('uuid', $photoUuid);
                $resolvePhoto = $foundInBatch ?: Photo::where('gallery_id', $gallery->id)
                    ->where('uuid', $photoUuid)
                    ->where('is_hidden', false)
                    ->whereNull('deleted_at')
                    ->first();

                if ($resolvePhoto) {
                    $deepLinkedPhoto = $this->photoPayload($resolvePhoto, $gallery, $this->exposesOriginals($gallery, $request));
                }
            }

            // Non-blocking telemetry view log
            if (!$trackViewInBrowser && !$viewingAsOwner) {
                try {
                    DB::table('activity_logs')->insert([
                        'gallery_id' => $gallery->id,
                        'event' => 'gallery_viewed',
                        'visitor_session_id' => $request->session()->getId(),
                        'source' => $request->query('source', 'direct'),
                        'referrer' => $request->header('referer'),
                        'ip_address' => $request->ip(),
                        'user_agent' => $request->userAgent(),
                        'created_at' => now(),
                    ]);
                } catch (\Throwable $e) {
                    // Telemetry failure should never break gallery rendering
                }
            }

            $coverPhoto = ($gallery->coverPhoto && !$gallery->coverPhoto->is_hidden)
                ? $gallery->coverPhoto
                : $initialPhotos->first();
            $cover = $this->coverViewData($coverPhoto, $gallery);
        }

        return view('public.gallery', $cover + [
            'gallery' => $gallery,
            'sets' => $sets,
            'activeSet' => $activeSet,
            'photographer' => $gallery->user,
            'photos' => $initialPhotos,
            'nextCursor' => $nextCursor,
            'hasMore' => $hasMore,
            'accessDecision' => $decision,
            'downloadsUnlocked' => $downloadsUnlocked,
            'passwordHint' => $decision->passwordHint,
            'deepLinkedPhoto' => $deepLinkedPhoto,
            'trackViewInBrowser' => $trackViewInBrowser && $decision->isGranted() && !$viewingAsOwner,
            'viewingAsOwner' => $viewingAsOwner,
            'seo' => \App\Support\Seo\SeoMetadata::forGallery(
                $gallery,
                app(\App\Services\GalleryShareImageService::class)->url($gallery),
                $decision->isGranted()
            ),
        ]);
    }

    /**
     * The client gallery as the photographer's client will see it, with unsaved cover settings applied
     * from the query (?cover=<photo uuid>&focal=0.3,0.6). Studio only: skips the access gate, records
     * nothing and ignores clicks, so the photographer's own visits stay out of their analytics.
     */
    public function preview(Request $request, string $uuid): View
    {
        $gallery = Gallery::where('uuid', $uuid)->firstOrFail();
        $this->authorize('update', $gallery);
        Photo::showWatermarkFor($gallery);
        $gallery->loadMissing(['coverPhoto', 'stats', 'user.plan']);

        [$sets, $activeSet] = $this->setsFor($gallery, $request);
        $initialPhotos = collect($this->photoQuery->getPaginatedForGallery($gallery, 24, null, $activeSet)->items());

        $coverPhoto = ($gallery->coverPhoto && !$gallery->coverPhoto->is_hidden) ? $gallery->coverPhoto : $initialPhotos->first();
        if ($coverUuid = $request->query('cover')) {
            $coverPhoto = Photo::where('gallery_id', $gallery->id)->where('uuid', $coverUuid)->where('is_hidden', false)->first() ?? $coverPhoto;
        }

        $focal = array_map('floatval', explode(',', (string) $request->query('focal', '')));
        $focal = count($focal) === 2 ? array_map(fn ($v) => max(0.0, min(1.0, $v)), $focal) : [null, null];

        return view('public.gallery', $this->coverViewData($coverPhoto, $gallery, $focal[0], $focal[1]) + [
            'gallery' => $gallery,
            'sets' => $sets,
            'activeSet' => $activeSet,
            'photographer' => $gallery->user,
            'photos' => $initialPhotos,
            'nextCursor' => null,
            'hasMore' => false,
            'accessDecision' => GalleryAccessDecision::granted(),
            'downloadsUnlocked' => !$gallery->requiresDownloadPin(),
            'passwordHint' => null,
            'deepLinkedPhoto' => null,
            'trackViewInBrowser' => false,
            'isPreview' => true,
            'seo' => null,
            'title' => 'Preview: ' . $gallery->title,
            'robots' => 'noindex, nofollow',
        ]);
    }

    /**
     * The sets clients can open, and the one picked with ?set=<uuid> (an unknown or empty set shows everything).
     *
     * @return array{0: \Illuminate\Support\Collection<int, \App\Models\GallerySet>, 1: ?\App\Models\GallerySet}
     */
    private function setsFor(Gallery $gallery, Request $request): array
    {
        $sets = $this->photoQuery->publicSets($gallery);
        $picked = (string) $request->query('set', '');

        return [$sets, $picked === '' ? null : $sets->firstWhere('uuid', $picked)];
    }

    /**
     * Hero cover image variables for public/gallery: url, responsive srcset and sizes, and focal position.
     */
    private function coverViewData(?Photo $coverPhoto, Gallery $gallery, ?float $focalX = null, ?float $focalY = null): array
    {
        if (!$coverPhoto) {
            return ['coverUrl' => null, 'coverSrcset' => null, 'coverSizes' => null, 'coverPosition' => null];
        }

        // object-cover: when the viewport is narrower than the photo, height drives the rendered width
        $coverRatio = ($coverPhoto->width && $coverPhoto->height) ? $coverPhoto->width / $coverPhoto->height : 1.5;

        // The saved focal point belongs to the saved cover; a fallback cover stays centred
        $isSavedCover = $coverPhoto->id === $gallery->cover_photo_id;

        return [
            'coverUrl' => $coverPhoto->getThumbnailUrl('xl') ?: $coverPhoto->getThumbnailUrl('lg'),
            'coverSrcset' => $coverPhoto->getSrcset(['md', 'lg', 'xl']),
            'coverSizes' => sprintf('(max-aspect-ratio: %d/%d) %svh, 100vw', $coverPhoto->width ?: 3, $coverPhoto->height ?: 2, round($coverRatio * 100, 1)),
            'coverPosition' => $isSavedCover || $focalX !== null
                ? $gallery->coverObjectPosition($focalX, $focalY)
                : $gallery->coverObjectPosition(0.5, 0.5),
        ];
    }

    /**
     * View beacon sent by gallery pages served from Cloudflare's cache.
     */
    public function recordView(Request $request, string $username, string $slug): \Illuminate\Http\Response
    {
        $gallery = $this->galleryQuery->findForUser($username, $slug);
        abort_unless($gallery->hasPublicLink(), 404);

        $validated = $request->validate([
            'visitor_id' => ['nullable', 'string', 'max:64', 'regex:/^[A-Za-z0-9-]+$/'],
            'source' => ['nullable', 'string', 'max:100'],
            'campaign' => ['nullable', 'string', 'max:100'],
            'referrer' => ['nullable', 'string', 'max:2048'],
        ]);

        DB::table('activity_logs')->insert([
            'gallery_id' => $gallery->id,
            'event' => 'gallery_viewed',
            'visitor_session_id' => $validated['visitor_id'] ?? null,
            'source' => $validated['source'] ?? 'direct',
            'campaign' => $validated['campaign'] ?? null,
            'referrer' => $validated['referrer'] ?? null,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'created_at' => now(),
        ]);

        return response()->noContent();
    }

    /**
     * Link-preview image for galleries anyone with the link can open; 404 for PIN and private ones.
     */
    public function shareImage(string $username, string $slug, \App\Services\GalleryShareImageService $shareImages): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $path = $shareImages->path($this->galleryQuery->findForUser($username, $slug));
        abort_unless($path, 404);

        return response()->file($path, [
            'Content-Type' => 'image/jpeg',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }

    /**
     * Resolve a gallery for an HTML page: 410 Gone when it was deleted, so
     * search engines drop it, and 404 when it never existed.
     */
    private function resolveGallery(string $username, string $slug): Gallery
    {
        try {
            return $this->galleryQuery->findForUser($username, $slug);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            abort_if($this->galleryQuery->findTrashedForUser($username, $slug), 410);
            throw $e;
        }
    }

    /**
     * Lean cursor-paginated JSON stream for subsequent photo batches & favorites filter.
     */
    public function photos(Request $request, string $username, string $slug): JsonResponse
    {
        $gallery = $this->galleryQuery->findForUser($username, $slug);
        Photo::showWatermarkFor($gallery);

        $accessError = $this->verifyGalleryAccess($gallery, $request);
        if ($accessError) {
            return $accessError;
        }

        // Single photo resolver
        if ($singleUuid = $request->query('uuid')) {
            $photo = Photo::where('gallery_id', $gallery->id)
                ->where('uuid', $singleUuid)
                ->where('is_hidden', false)
                ->whereNull('deleted_at')
                ->first();

            if (!$photo) {
                return response()->json(['code' => 'PHOTO_NOT_FOUND', 'message' => 'Photo not found.'], 404);
            }

            return response()->json(['data' => $this->photoPayload($photo, $gallery, $this->exposesOriginals($gallery, $request))]);
        }

        $perPage = $request->integer('per_page', 24);

        // Server-side favorites filtering with pagination
        $uuids = $request->input('uuids');
        if (is_string($uuids)) {
            $uuids = array_filter(explode(',', $uuids));
        }

        [, $activeSet] = $this->setsFor($gallery, $request);
        $paginated = $this->photoQuery->getPaginatedForGallery($gallery, $perPage, is_array($uuids) ? $uuids : null, $activeSet);

        $withOriginals = $this->exposesOriginals($gallery, $request);
        $data = collect($paginated->items())->map(fn (Photo $photo) => $this->photoPayload($photo, $gallery, $withOriginals));

        return response()->json([
            'data' => $data,
            'next_cursor' => $paginated->nextCursor()?->encode(),
            'has_more' => $paginated->hasMorePages(),
        ]);
    }

    /**
     * Presigned original-file links bypass the download route, so only hand them out when this visitor may download.
     */
    private function exposesOriginals(Gallery $gallery, Request $request): bool
    {
        return $gallery->photoDownloadsAllowed() && $this->downloadsUnlocked($gallery, $request);
    }

    /**
     * Client payload for one photo; mirrors the data-* attributes of components/gallery/photo-card.
     */
    private function photoPayload(Photo $photo, Gallery $gallery, bool $withOriginals): array
    {
        $isVideo = $photo->isVideo();
        $originalUrl = $withOriginals ? $photo->getOriginalDownloadUrl() : null;

        return [
            'id' => $photo->id,
            'uuid' => $photo->uuid,
            'filename' => $photo->original_filename,
            'large' => $isVideo ? $photo->getPosterUrl('lg') : $photo->getUrl('lg'),
            // Video posters stop at 'lg'
            'full' => $isVideo ? $photo->getPosterUrl('lg') : $photo->getUrl('xl'),
            'original' => $originalUrl,
            'thumbnail' => $photo->getThumbnailUrl('md'),
            'srcset' => $photo->getSrcset(),
            'is_video' => $isVideo,
            'duration' => $photo->duration_formatted,
            'delivery_url' => $isVideo ? app(\App\Services\MediaTokenService::class)->getStreamUrl($photo, $gallery) : null,
            'delivery_download_url' => $withOriginals ? ($isVideo ? $photo->getDeliveryDownloadUrl() : $originalUrl) : null,
            'width' => $photo->width,
            'height' => $photo->height,
            'blurhash' => $photo->blurhash,
        ];
    }

    /**
     * Display export UI for ZIP downloads or Google Photos sync.
     * Note: This only renders the UI; actual export is handled asynchronously via queued jobs.
     */
    public function export(Request $request, string $username, string $slug): View|\Illuminate\Http\RedirectResponse
    {
        $gallery = $this->resolveGallery($username, $slug);
        Photo::showWatermarkFor($gallery);

        $accessError = $this->verifyGalleryAccess($gallery, $request);
        if ($accessError || !$this->downloadsUnlocked($gallery, $request)) {
            return redirect($gallery->public_url);
        }

        $type = $request->query('type', 'zip');
        $target = $request->query('target', 'all');

        $errorMessage = match ((string) $request->query('error')) {
            'not_found', 'unavailable' => 'That download is no longer available. Please request a new one.',
            'expired' => 'That download link has expired. Please request a new one.',
            'unauthorized' => 'You do not have access to that download.',
            'denied' => 'Google Photos access was not granted.',
            'used', 'token' => 'We could not connect to Google Photos. Please try again.',
            default => null,
        };

        $syncUuid = $request->query('sync');

        // Same cover as the gallery hero, shown blurred behind the card
        $coverPhoto = ($gallery->coverPhoto && !$gallery->coverPhoto->is_hidden)
            ? $gallery->coverPhoto
            : ($this->photoQuery->getPaginatedForGallery($gallery, 1)->items()[0] ?? null);

        return view('public.export', [
            'gallery' => $gallery,
            'photographer' => $gallery->user,
            'coverUrl' => $coverPhoto?->getThumbnailUrl('md') ?: $coverPhoto?->getThumbnailUrl('lg'),
            'type' => $type === 'google-photos' && $gallery->googlePhotosExportEnabled() ? $type : 'zip',
            'target' => in_array($target, ['all', 'favorites']) ? $target : 'all',
            'syncUuid' => is_string($syncUuid) && \Illuminate\Support\Str::isUuid($syncUuid) ? $syncUuid : null,
            'errorMessage' => $errorMessage,
        ]);
    }

    /**
     * Unlock password-protected gallery and record version-aware session.
     */
    public function unlock(Request $request, string $username, string $slug): JsonResponse
    {
        $request->validate([
            'password' => ['required', 'string'],
        ]);

        $gallery = $this->galleryQuery->findForUser($username, $slug);

        if (empty($gallery->password_hash)) {
            return response()->json(['success' => true]);
        }

        // Per-gallery limit on top of the per-IP route throttle, so rotating IPs can't brute-force a password
        $limiterKey = "gallery-unlock:{$gallery->id}";
        if (RateLimiter::tooManyAttempts($limiterKey, 30)) {
            return response()->json([
                'success' => false,
                'message' => 'Too many attempts. Please try again in a few minutes.',
            ], 429);
        }

        if (Hash::check($request->password, $gallery->password_hash)) {
            // Store password hash checksum so session is invalidated if password changes
            $request->session()->put("gallery_unlocked_{$gallery->id}", [
                'unlocked' => true,
                'hash_checksum' => md5($gallery->password_hash),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Gallery unlocked successfully.',
            ]);
        }

        RateLimiter::hit($limiterKey, 600);

        return response()->json([
            'success' => false,
            'message' => 'That password is incorrect.',
        ], 401);
    }

    /**
     * Unlock downloads on a gallery with a download PIN; the session keeps a checksum so changing the PIN re-locks.
     */
    public function unlockDownloads(Request $request, string $username, string $slug): JsonResponse
    {
        $request->validate([
            'pin' => ['required', 'string'],
        ]);

        $gallery = $this->galleryQuery->findForUser($username, $slug);

        if ($accessError = $this->verifyGalleryAccess($gallery, $request)) {
            return $accessError;
        }

        if (!$gallery->requiresDownloadPin()) {
            return response()->json(['success' => true]);
        }

        // Four digits is a small space: cap guesses per gallery on top of the per-IP route throttle
        $limiterKey = "gallery-download-unlock:{$gallery->id}";
        if (RateLimiter::tooManyAttempts($limiterKey, 30)) {
            return response()->json([
                'success' => false,
                'message' => 'Too many attempts. Please try again in a few minutes.',
            ], 429);
        }

        if (hash_equals($gallery->download_pin, trim($request->pin))) {
            $request->session()->put("gallery_download_unlocked_{$gallery->id}", md5($gallery->download_pin));

            return response()->json([
                'success' => true,
                'message' => 'Downloads unlocked.',
            ]);
        }

        RateLimiter::hit($limiterKey, 600);

        return response()->json([
            'success' => false,
            'message' => 'The download PIN you entered is incorrect.',
        ], 401);
    }

    /**
     * Download a single photo with original filename and Content-Disposition.
     * Enforces gallery access control, photo scoping to gallery, download permissions, and records telemetry.
     */
    public function downloadPhoto(Request $request, string $username, string $slug, string $uuid)
    {
        $gallery = $this->galleryQuery->findForUser($username, $slug);

        // 1. Reuse existing gallery access verification (public / password / token)
        $accessError = $this->verifyGalleryAccess($gallery, $request);
        if ($accessError) {
            return $accessError;
        }

        // 2. Verify photo downloads are permitted on this gallery
        if (!$gallery->photoDownloadsAllowed()) {
            return response()->json([
                'code' => 'DOWNLOADS_DISABLED',
                'message' => 'Photo downloads are disabled for this gallery.',
            ], 403);
        }

        if ($pinError = $this->verifyDownloadPin($gallery, $request)) {
            return $pinError;
        }

        // 3. Strictly scope photo lookup to this gallery
        $photo = Photo::where('gallery_id', $gallery->id)
            ->where('uuid', $uuid)
            ->where('is_hidden', false)
            ->whereNull('deleted_at')
            ->first();

        if (!$photo) {
            return response()->json([
                'code' => 'PHOTO_NOT_FOUND',
                'message' => 'Photo not found.',
            ], 404);
        }

        // The photographer's own downloads stay out of their analytics
        if (GalleryOwnerAccess::viewing($gallery, $request)) {
            return \App\Support\PhotoDownload::response($photo);
        }

        // 4. Telemetry: record download only after all authorizations succeed
        $gallery->stats()->increment('downloads_count');
        $gallery->stats()->update(['updated_at' => now()]);

        $visitorSession = $request->header('X-Visitor-Session-ID') ?: $request->cookie('visitor_session_id') ?: (session()->isStarted() ? session()->getId() : null);
        $source = $request->query('source') ?: $request->query('utm_source') ?: 'direct';
        $referrer = $request->header('referer');
        $campaign = $request->query('utm_campaign');

        DB::table('activity_logs')->insert([
            'gallery_id' => $gallery->id,
            'event' => 'photo_downloaded',
            'visitor_session_id' => $visitorSession,
            'source' => $source,
            'referrer' => $referrer,
            'campaign' => $campaign,
            'properties' => json_encode([
                'photo_uuid' => $photo->uuid,
                'filename' => $photo->original_filename,
                'email' => $this->visitorEmail($request),
                'action' => 'download',
            ]),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'created_at' => now(),
        ]);

        return \App\Support\PhotoDownload::response($photo);
    }

    /**
     * The email the visitor gave for favorites, sent along with a download so the photographer sees who it was.
     */
    private function visitorEmail(Request $request): ?string
    {
        $email = strtolower(trim((string) $request->query('email')));

        return filter_var($email, FILTER_VALIDATE_EMAIL) && strlen($email) <= 191 ? $email : null;
    }
}
