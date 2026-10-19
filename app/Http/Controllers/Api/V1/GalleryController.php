<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Queries\GalleryQuery;
use App\Services\GalleryZipDownloadService;
use App\Traits\VerifiesGalleryAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Public gallery endpoints used by the gallery page JavaScript
 * (download telemetry, favorites, ZIP exports).
 */
class GalleryController extends Controller
{
    use VerifiesGalleryAccess;

    public function __construct(
        protected GalleryQuery $galleryQuery
    ) {}


    /**
     * Record download analytics for a public gallery.
     * POST /api/v1/public/galleries/{username}/{slug}/download
     */
    public function recordDownload(Request $request, string $username, string $slug): JsonResponse
    {
        $gallery = $this->galleryQuery->findForUser($username, $slug);

        $errorResponse = $this->verifyGalleryAccess($gallery, $request);
        if ($errorResponse) {
            return $errorResponse;
        }

        $validated = $request->validate([
            'photo_uuid' => ['nullable', 'string'],
            'email' => ['nullable', 'string', 'email'],
        ]);

        $gallery->stats()->increment('downloads_count');
        $gallery->stats()->update(['updated_at' => now()]);

        $email = isset($validated['email']) ? strtolower(trim($validated['email'])) : null;

        $visitorSession = $request->header('X-Visitor-Session-ID') ?: $request->cookie('visitor_session_id') ?: session()->getId();
        $source = $request->query('source') ?: $request->query('utm_source') ?: 'direct';
        $referrer = $request->header('referer');
        $campaign = $request->query('utm_campaign');

        \Illuminate\Support\Facades\DB::table('activity_logs')->insert([
            'gallery_id' => $gallery->id,
            'event' => 'photo_downloaded',
            'visitor_session_id' => $visitorSession,
            'source' => $source,
            'referrer' => $referrer,
            'campaign' => $campaign,
            'properties' => json_encode([
                'photo_uuid' => $validated['photo_uuid'] ?? null,
                'email' => $email,
                'action' => 'download',
            ]),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'created_at' => now(),
        ]);

        return response()->json([
            'message' => 'Download recorded successfully.',
            'downloads_count' => $gallery->stats->fresh()->downloads_count,
        ]);
    }

    /**
     * Increment or decrement favorites count for a public gallery.
     * POST /api/v1/public/galleries/{username}/{slug}/favorite
     */
    public function toggleFavorite(Request $request, string $username, string $slug): JsonResponse
    {
        $gallery = $this->galleryQuery->findForUser($username, $slug);

        $errorResponse = $this->verifyGalleryAccess($gallery, $request);
        if ($errorResponse) {
            return $errorResponse;
        }

        $validated = $request->validate([
            'photo_uuid' => ['nullable', 'string'],
            'is_favorite' => ['required', 'boolean'],
            'email' => ['nullable', 'string', 'email'],
        ]);

        $isFavorite = $validated['is_favorite'];
        $email = isset($validated['email']) ? strtolower(trim($validated['email'])) : null;

        if ($isFavorite) {
            $gallery->stats()->increment('favorites_count');
        } else {
            // Ensure we don't decrement below 0
            if ($gallery->stats->favorites_count > 0) {
                $gallery->stats()->decrement('favorites_count');
            }
        }
        $gallery->stats()->update(['updated_at' => now()]);

        $visitorSession = $request->header('X-Visitor-Session-ID') ?: $request->cookie('visitor_session_id') ?: session()->getId();
        $source = $request->query('source') ?: $request->query('utm_source') ?: 'direct';
        $referrer = $request->header('referer');
        $campaign = $request->query('utm_campaign');

        \Illuminate\Support\Facades\DB::table('activity_logs')->insert([
            'gallery_id' => $gallery->id,
            'event' => $isFavorite ? 'photo_favorited' : 'photo_unfavorited',
            'visitor_session_id' => $visitorSession,
            'source' => $source,
            'referrer' => $referrer,
            'campaign' => $campaign,
            'properties' => json_encode([
                'photo_uuid' => $validated['photo_uuid'] ?? null,
                'email' => $email,
                'action' => $isFavorite ? 'favorite' : 'unfavorite',
            ]),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'created_at' => now(),
        ]);

        return response()->json([
            'message' => $isFavorite ? 'Favorite added.' : 'Favorite removed.',
            'favorites_count' => $gallery->stats->fresh()->favorites_count,
        ]);
    }

    /**
     * Download the entire gallery packaged as a ZIP file.
     * GET /api/v1/public/galleries/{username}/{slug}/download-zip
     */
    /**
     * Trigger background ZIP generation.
     * POST /public/galleries/{slug}/download-zip
     */
    public function triggerZipDownload(Request $request, string $username, string $slug): JsonResponse
    {
        $gallery = $this->galleryQuery->findForUser($username, $slug);

        $errorResponse = $this->verifyGalleryAccess($gallery, $request);
        if ($errorResponse) {
            return $errorResponse;
        }

        if (!$gallery->allow_gallery_downloads) {
            return response()->json([
                'code' => 'DOWNLOADS_DISABLED',
                'message' => 'Full gallery downloads are disabled for this gallery.',
            ], 403);
        }

        $photos = $gallery->photos()
            ->where('status', \App\Enums\PhotoStatus::Ready->value)
            ->orderBy('id')
            ->get(['id', 'updated_at']);

        if ($photos->isEmpty()) {
            return response()->json([
                'status' => 'empty',
                'message' => 'No ready photos in this gallery to pack.',
            ], 400);
        }

        // Deterministic photo snapshot hash calculation
        $hashInput = $photos->map(fn($p) => $p->id . '-' . $p->updated_at->timestamp)->join(',');
        $snapshotHash = md5($hashInput);

        $download = \App\Models\GalleryDownload::where('gallery_id', $gallery->id)
            ->where('photo_snapshot_hash', $snapshotHash)
            ->where('status', '!=', 'expired')
            ->first();

        $zipService = app(GalleryZipDownloadService::class);
        if ($download) {
            if ($zipService->expireIfNecessary($download)) {
                $download = null;
            }
        }

        $storageService = app(\App\Services\StorageService::class);

        // 1. Strictly require a valid RFC email address before issuing any download URL or scheduling packaging
        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'email' => ['required', 'string', 'email:rfc'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'code' => 'EMAIL_REQUIRED',
                'message' => 'A valid email address is required to prepare and download this gallery archive.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $email = strtolower(trim((string) $request->input('email')));

        // 2. Canonical notification preference resolution with explicit precedence
        $notifyWhenReady = $request->has('notify_when_ready')
            ? $request->boolean('notify_when_ready')
            : $request->boolean('notify');

        if ($download && ($download->status === 'ready' || $download->status === 'ready_with_errors') && $download->storage_path) {
            if (Storage::disk('b2')->exists($download->storage_path)) {
                // Ensure email and notification preference are persisted on the record
                $updatePayload = [];
                if (empty($download->email)) {
                    $updatePayload['email'] = $email;
                }
                if ($notifyWhenReady && !$download->notify_when_ready) {
                    $updatePayload['notify_when_ready'] = true;
                }

                // Preserve existing token if already tokenized, otherwise create one
                $rawToken = null;
                if (!$download->download_token_hash) {
                    $rawToken = bin2hex(random_bytes(32));
                    $updatePayload['download_token_hash'] = hash('sha256', $rawToken);
                }

                if (!empty($updatePayload)) {
                    $download->update($updatePayload);
                }

                // If user opted into notifications and notification has not yet been sent, queue it
                if ($notifyWhenReady && is_null($download->notification_sent_at)) {
                    \Illuminate\Support\Facades\DB::transaction(function () use ($download, $email, $rawToken) {
                        $lockedDownload = \App\Models\GalleryDownload::where('id', $download->id)->lockForUpdate()->first();
                        if ($lockedDownload && is_null($lockedDownload->notification_sent_at)) {
                            $lockedDownload->update(['notification_sent_at' => now()]);
                            $mailUrl = $lockedDownload->downloadUrl($rawToken);
                            \Illuminate\Support\Facades\DB::afterCommit(function () use ($lockedDownload, $mailUrl, $email) {
                                \Illuminate\Support\Facades\Mail::to($email)->queue(
                                    new \App\Mail\GalleryZipReadyMail($lockedDownload, $mailUrl)
                                );
                            });
                        }
                    });
                }

                // Record download email captured telemetry
                $visitorSession = $request->header('X-Visitor-Session-ID') ?: $request->cookie('visitor_session_id') ?: (session()->isStarted() ? session()->getId() : null);
                \Illuminate\Support\Facades\DB::table('activity_logs')->insert([
                    'gallery_id' => $gallery->id,
                    'event' => 'gallery_download_email_captured',
                    'visitor_session_id' => $visitorSession,
                    'properties' => json_encode([
                        'download_id' => $download->id,
                        'email' => $email,
                        'status' => 'ready',
                    ]),
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'created_at' => now(),
                ]);

                $queryParams = [];
                // Only the per-download token goes in the link; gallery access
                // otherwise comes from the visitor's session.
                if ($rawToken) {
                    $queryParams['token'] = $rawToken;
                }
                if ($request->query('invite')) {
                    $queryParams['invite'] = $request->query('invite');
                }

                $downloadUrl = route('api.public.gallery.zip.file', [
                    'username' => strtolower($gallery->user->username),
                    'slug' => $gallery->slug,
                    'id' => $download->id,
                    ...$queryParams,
                ]);

                return response()->json([
                    'status' => 'ready',
                    'download_id' => $download->id,
                    'download_url' => $downloadUrl,
                    'size' => $download->size,
                ]);
            } else {
                // ZIP was deleted in B2, reset mapping
                $download->update([
                    'status' => 'expired',
                    'expired_at' => now(),
                    'storage_path' => null,
                ]);
                $download = null;
            }
        }

        if ($download && ($download->status === 'processing' || $download->status === 'pending')) {
            // Update email settings if provided
            $download->update([
                'email' => $download->email ?: $email,
                'notify_when_ready' => $notifyWhenReady ? true : $download->notify_when_ready,
            ]);

            return response()->json([
                'status' => $download->status,
                'download_id' => $download->id,
            ]);
        }

        // Dispatch background packaging job if missing or stale
        $rawToken = bin2hex(random_bytes(32));
        $download = \App\Models\GalleryDownload::create([
            'gallery_id' => $gallery->id,
            'status' => 'pending',
            'photo_snapshot_hash' => $snapshotHash,
            'email' => $email,
            'notify_when_ready' => $notifyWhenReady,
            'download_token_hash' => hash('sha256', $rawToken),
        ]);

        \App\Jobs\GenerateGalleryZipJob::dispatch($gallery->id, $download->id, $rawToken);

        return response()->json([
            'status' => 'pending',
            'download_id' => $download->id,
        ]);
    }

    /**
     * Retrieve status of a ZIP generation.
     * GET /public/galleries/{slug}/download-zip/{id}
     */
    public function getZipDownloadStatus(Request $request, string $username, string $slug, int $id): JsonResponse
    {
        $gallery = $this->galleryQuery->findForUser($username, $slug);

        $errorResponse = $this->verifyGalleryAccess($gallery, $request);
        if ($errorResponse) {
            return $errorResponse;
        }

        $download = \App\Models\GalleryDownload::where('gallery_id', $gallery->id)
            ->where('id', $id)
            ->firstOrFail();

        // Expire check
        $zipService = app(GalleryZipDownloadService::class);
        if ($zipService->expireIfNecessary($download)) {
            return response()->json([
                'code' => 'ZIP_EXPIRED',
                'message' => 'This ZIP download has expired and is no longer available.',
            ], 410);
        }

        $progressService = app(\App\Services\ExportProgressService::class);
        $progress = $progressService->calculate(
            $download->started_at,
            $download->completed_at,
            $download->total_photos,
            $download->processed_photos,
            $download->failed_photos,
            $download->status
        );

        $downloadUrl = null;
        if (($download->status === 'ready' || $download->status === 'ready_with_errors') && $download->storage_path) {
            $queryParams = [];
            if ($request->query('invite')) {
                $queryParams['invite'] = $request->query('invite');
            }

            $downloadUrl = route('api.public.gallery.zip.file', [
                    'username' => strtolower($gallery->user->username),
                    'slug' => $gallery->slug,
                    'id' => $download->id,
                    ...$queryParams,
                ]);
        }

        return response()->json([
            'id' => $download->id,
            'status' => $download->status,
            'email' => $download->email,
            'notify_when_ready' => (bool)$download->notify_when_ready,
            'total_photos' => $download->total_photos,
            'processed_photos' => $download->processed_photos,
            'failed_photos' => $download->failed_photos,
            'error' => $download->error,
            'download_url' => $downloadUrl,
            'size' => $download->size,
            'percentage' => $progress['percentage'],
            'remaining_seconds' => $progress['remaining_seconds'],
            'estimated_finish_time' => $progress['estimated_finish_time'],
        ]);
    }

    /**
     * Endpoint to handle authorization check, download logging, filename sanitization,
     * and redirect (302) to the B2 signed url.
     * GET /public/galleries/{slug}/download-zip/{id}/download
     */
    public function downloadZipFile(Request $request, string $username, string $slug, int $id)
    {
        $gallery = $this->galleryQuery->findForUser($username, $slug);
        
        $exportUrl = route('public.gallery.export', [
            'username' => strtolower($gallery->user->username),
            'slug' => $gallery->slug,
        ]);

        $download = \App\Models\GalleryDownload::where('gallery_id', $gallery->id)
            ->where('id', $id)
            ->first();

        if (!$download) {
            return redirect()->to($exportUrl . '?error=not_found');
        }

        // Shared expiration check
        $zipService = app(GalleryZipDownloadService::class);
        if ($zipService->expireIfNecessary($download)) {
            return redirect()->to($exportUrl . '?error=expired');
        }

        if (!in_array($download->status, ['ready', 'ready_with_errors'])) {
            return redirect()->to($exportUrl . '?error=unavailable');
        }

        // Credential isolation rules
        $token = $request->query('token');
        if ($token) {
            $hashedInput = hash('sha256', $token);
            if (!$download->download_token_hash || !hash_equals($download->download_token_hash, $hashedInput)) {
                return redirect()->to($exportUrl . '?error=unauthorized');
            }
        } else {
            $errorResponse = $this->verifyGalleryAccess($gallery, $request);
            if ($errorResponse) {
                return redirect()->to($exportUrl . '?error=unauthorized');
            }
        }

        // Verify storage file exists
        if (!$download->storage_path || !Storage::disk('b2')->exists($download->storage_path)) {
            $download->update([
                'status' => 'expired',
                'expired_at' => now(),
                'storage_path' => null,
            ]);
            return redirect()->to($exportUrl . '?error=expired');
        }

        // Track download stats
        $gallery->stats()->increment('downloads_count');
        $gallery->stats()->update(['updated_at' => now()]);

        // Record public activity log
        $visitorSession = $request->header('X-Visitor-Session-ID') ?: $request->cookie('visitor_session_id') ?: session()->getId();
        \Illuminate\Support\Facades\DB::table('activity_logs')->insert([
            'gallery_id' => $gallery->id,
            'event' => 'gallery_zip_file_downloaded',
            'visitor_session_id' => $visitorSession,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'created_at' => now(),
            'properties' => json_encode([
                'download_id' => $download->id,
                'total_photos' => $download->total_photos,
                'size' => $download->size,
            ]),
        ]);

        // Deterministic filename sanitization
        $safeTitle = preg_replace('/[^A-Za-z0-9 _-]/', '', $gallery->title);
        $safeTitle = trim($safeTitle) ?: 'gallery-photos';
        $safeTitle = preg_replace('/-+/', '-', str_replace(' ', '-', $safeTitle));
        $safeTitle = trim($safeTitle, '-');
        if (strlen($safeTitle) > 100) {
            $safeTitle = substr($safeTitle, 0, 100);
        }
        $safeTitle = $safeTitle ?: 'gallery';
        $filename = "{$safeTitle}.zip";

        $storageService = app(\App\Services\StorageService::class);
        $presignedUrl = $storageService->generatePresignedDownloadUrl($download->storage_path, $filename, now()->addMinutes(15));

        return redirect()->away($presignedUrl, 302, [
            'Referrer-Policy' => 'no-referrer',
        ]);
    }
}
