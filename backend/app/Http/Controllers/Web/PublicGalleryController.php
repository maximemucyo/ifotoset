<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Gallery;
use App\Models\Photo;
use App\Queries\GalleryPhotoQuery;
use App\Queries\GalleryQuery;
use App\Traits\VerifiesGalleryAccess;
use App\ValueObjects\GalleryAccessDecision;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
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
        $gallery = $this->galleryQuery->findBySlug($slug);

        if (strtolower($gallery->user->username) !== strtolower($username)) {
            abort(404);
        }

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

        // Pure evaluation of access decision
        $decision = $this->evaluateGalleryAccess($gallery, $request);

        $initialPhotos = collect();
        $nextCursor = null;
        $hasMore = false;
        $deepLinkedPhoto = null;
        $coverUrl = null;

        // Zero-leak security invariant: only query and hydrate photo data when access is granted
        if ($decision->isGranted()) {
            $paginated = $this->photoQuery->getPaginatedForGallery($gallery, 24);
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
                    $mediaTokenService = app(\App\Services\MediaTokenService::class);
                    $deepLinkedPhoto = [
                        'id' => $resolvePhoto->id,
                        'uuid' => $resolvePhoto->uuid,
                        'filename' => $resolvePhoto->original_filename,
                        'large' => $resolvePhoto->getUrl('lg'),
                        'full' => $resolvePhoto->getUrl('xl'),
                        'original' => $resolvePhoto->getOriginalDownloadUrl(),
                        'thumbnail' => $resolvePhoto->getThumbnailUrl('md'),
                        'is_video' => $resolvePhoto->isVideo(),
                        'duration' => $resolvePhoto->duration_formatted,
                        'delivery_url' => $resolvePhoto->isVideo() ? $mediaTokenService->getDeliveryUrl($resolvePhoto, $gallery) : null,
                        'delivery_download_url' => $resolvePhoto->isVideo() ? $resolvePhoto->getDeliveryDownloadUrl() : $resolvePhoto->getOriginalDownloadUrl(),
                        'width' => $resolvePhoto->width,
                        'height' => $resolvePhoto->height,
                        'blurhash' => $resolvePhoto->blurhash,
                    ];
                }
            }

            // Non-blocking telemetry view log
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

            // Hero cover image
            $coverUrl = $gallery->getCoverUrl('xl');
            if (!$coverUrl && $initialPhotos->isNotEmpty()) {
                $coverUrl = $initialPhotos->first()->getUrl('xl');
            }
        }

        return view('public.gallery', [
            'gallery' => $gallery,
            'photographer' => $gallery->user,
            'photos' => $initialPhotos,
            'coverUrl' => $coverUrl,
            'nextCursor' => $nextCursor,
            'hasMore' => $hasMore,
            'accessDecision' => $decision,
            'requiresPassword' => $decision->requiresPassword(),
            'requiresInvitation' => $decision->requiresInvitation(),
            'isInvitationInvalid' => $decision->isInvitationInvalid(),
            'accessErrorMessage' => $decision->message,
            'passwordHint' => $decision->passwordHint,
            'deepLinkedPhoto' => $deepLinkedPhoto,
            'seo' => \App\Support\Seo\SeoMetadata::forGallery($gallery, $coverUrl),
        ]);
    }

    /**
     * Lean cursor-paginated JSON stream for subsequent photo batches & favorites filter.
     */
    public function photos(Request $request, string $username, string $slug): JsonResponse
    {
        $gallery = $this->galleryQuery->findBySlug($slug);

        if (strtolower($gallery->user->username) !== strtolower($username)) {
            return response()->json(['code' => 'NOT_FOUND', 'message' => 'Gallery not found.'], 404);
        }

        $accessError = $this->verifyGalleryAccess($gallery, $request);
        if ($accessError) {
            return $accessError;
        }

        $mediaTokenService = app(\App\Services\MediaTokenService::class);

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

            return response()->json([
                'data' => [
                    'id' => $photo->id,
                    'uuid' => $photo->uuid,
                    'filename' => $photo->original_filename,
                    'large' => $photo->isVideo() ? $photo->getPosterUrl('lg') : $photo->getUrl('lg'),
                    'full' => $photo->isVideo() ? $photo->getPosterUrl('xl') : $photo->getUrl('xl'),
                    'original' => $photo->getOriginalDownloadUrl(),
                    'thumbnail' => $photo->getThumbnailUrl('md'),
                    'is_video' => $photo->isVideo(),
                    'duration' => $photo->duration_formatted,
                    'delivery_url' => $photo->isVideo() ? $mediaTokenService->getDeliveryUrl($photo, $gallery) : null,
                    'delivery_download_url' => $photo->isVideo() ? $photo->getDeliveryDownloadUrl() : $photo->getOriginalDownloadUrl(),
                    'width' => $photo->width,
                    'height' => $photo->height,
                    'blurhash' => $photo->blurhash,
                ]
            ]);
        }

        $perPage = $request->integer('per_page', 24);

        // Server-side favorites filtering with pagination
        $uuids = $request->input('uuids');
        if (is_string($uuids)) {
            $uuids = array_filter(explode(',', $uuids));
        }

        $paginated = $this->photoQuery->getPaginatedForGallery($gallery, $perPage, is_array($uuids) ? $uuids : null);

        $data = collect($paginated->items())->map(function (Photo $photo) use ($gallery, $mediaTokenService) {
            return [
                'id' => $photo->id,
                'uuid' => $photo->uuid,
                'filename' => $photo->original_filename,
                'large' => $photo->isVideo() ? $photo->getPosterUrl('lg') : $photo->getUrl('lg'),
                'full' => $photo->isVideo() ? $photo->getPosterUrl('xl') : $photo->getUrl('xl'),
                'original' => $photo->getOriginalDownloadUrl(),
                'thumbnail' => $photo->getThumbnailUrl('md'),
                'is_video' => $photo->isVideo(),
                'duration' => $photo->duration_formatted,
                'delivery_url' => $photo->isVideo() ? $mediaTokenService->getDeliveryUrl($photo, $gallery) : null,
                'delivery_download_url' => $photo->isVideo() ? $photo->getDeliveryDownloadUrl() : $photo->getOriginalDownloadUrl(),
                'width' => $photo->width,
                'height' => $photo->height,
                'blurhash' => $photo->blurhash,
            ];
        });

        return response()->json([
            'data' => $data,
            'next_cursor' => $paginated->nextCursor()?->encode(),
            'has_more' => $paginated->hasMorePages(),
        ]);
    }

    /**
     * Display export UI for ZIP downloads or Google Photos sync.
     * Note: This only renders the UI; actual export is handled asynchronously via queued jobs.
     */
    public function export(Request $request, string $username, string $slug): View|\Illuminate\Http\RedirectResponse
    {
        $gallery = $this->galleryQuery->findBySlug($slug);

        if (strtolower($gallery->user->username) !== strtolower($username)) {
            abort(404);
        }

        $accessError = $this->verifyGalleryAccess($gallery, $request);
        if ($accessError) {
            return redirect($gallery->public_url);
        }

        $type = $request->query('type', 'zip');
        $target = $request->query('target', 'all');

        return view('public.export', [
            'gallery' => $gallery,
            'photographer' => $gallery->user,
            'type' => in_array($type, ['zip', 'google-photos']) ? $type : 'zip',
            'target' => in_array($target, ['all', 'favorites']) ? $target : 'all',
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

        $gallery = $this->galleryQuery->findBySlug($slug);

        if (empty($gallery->password_hash)) {
            return response()->json(['success' => true]);
        }

        if (Hash::check($request->password, $gallery->password_hash)) {
            // Store password hash checksum so session is invalidated if password changes
            $request->session()->put("gallery_unlocked_{$gallery->id}", [
                'unlocked' => true,
                'hash_checksum' => md5($gallery->password_hash),
            ]);

            $token = hash_hmac('sha256', $gallery->uuid, config('app.key'));

            return response()->json([
                'success' => true,
                'token' => $token,
                'message' => 'Gallery unlocked successfully.',
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'The PIN you entered is incorrect.',
        ], 401);
    }

    /**
     * Download a single photo with original filename and Content-Disposition.
     * Enforces gallery access control, photo scoping to gallery, download permissions, and records telemetry.
     */
    public function downloadPhoto(Request $request, string $username, string $slug, string $uuid)
    {
        $gallery = $this->galleryQuery->findBySlug($slug);

        if (!$gallery || strtolower($gallery->user->username) !== strtolower($username)) {
            abort(404, 'Gallery not found.');
        }

        // 1. Reuse existing gallery access verification (public / password / token)
        $accessError = $this->verifyGalleryAccess($gallery, $request);
        if ($accessError) {
            return $accessError;
        }

        // 2. Verify photo downloads are permitted on this gallery
        if (!$gallery->allow_photo_downloads) {
            return response()->json([
                'code' => 'DOWNLOADS_DISABLED',
                'message' => 'Photo downloads are disabled for this gallery.',
            ], 403);
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
                'action' => 'download',
            ]),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'created_at' => now(),
        ]);

        $filename = $photo->original_filename ?: ($photo->filename ?: 'photo.jpg');
        if ($photo->isVideo()) {
            $storagePath = $photo->delivery_path ?: $photo->original_path ?: ltrim($photo->path . '/' . ($photo->filename ?? $photo->stored_filename), '/');
            if (!str_ends_with(strtolower($filename), '.mp4')) {
                $filename = pathinfo($filename, PATHINFO_FILENAME) . '.mp4';
            }
        } else {
            $storagePath = ltrim($photo->path . '/' . ($photo->filename ?? $photo->stored_filename), '/');
        }

        // Check if running on local storage disk or mocked storage without S3 credentials
        if (config('filesystems.default') === 'local' || !config('filesystems.disks.b2.key')) {
            if (Storage::disk('b2')->exists($storagePath)) {
                return Storage::disk('b2')->download($storagePath, $filename);
            }
        }

        $storageService = app(\App\Services\StorageService::class);
        $presignedUrl = $storageService->generatePresignedDownloadUrl($storagePath, $filename, now()->addMinutes(15));

        return redirect()->away($presignedUrl, 302, [
            'Referrer-Policy' => 'no-referrer',
        ]);
    }
}
