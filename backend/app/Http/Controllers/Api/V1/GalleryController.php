<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\Visibility;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\GalleryResource;
use App\Models\Gallery;
use App\Models\GalleryStats;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Services\GalleryZipDownloadService;

use App\Traits\VerifiesGalleryAccess;

class GalleryController extends Controller
{
    use VerifiesGalleryAccess;
    /**
     * List authenticated photographer's galleries.
     * GET /api/v1/galleries
     */
    public function index(Request $request): JsonResponse
    {
        $galleries = Gallery::where('user_id', $request->user()->id)
            ->with(['stats', 'coverPhoto'])
            ->orderBy('created_at', 'desc')
            ->paginate($request->integer('per_page', 20));

        return GalleryResource::collection($galleries)->response();
    }

    /**
     * Create a new gallery with per-user unique slug handling.
     * POST /api/v1/galleries
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255'],
            'client_name' => ['nullable', 'string', 'max:255'],
            'event_date' => ['nullable', 'date'],
            'visibility' => ['nullable', 'string', 'in:public,private'],
            'allow_photo_downloads' => ['nullable', 'boolean'],
            'allow_gallery_downloads' => ['nullable', 'boolean'],
            'allow_google_photos' => ['nullable', 'boolean'],
            'password' => ['nullable', 'string', 'min:6'],
            'password_hint' => ['nullable', 'string', 'max:255'],
            'expires_at' => ['nullable', 'date', 'after:now'],
            'invite_emails' => ['nullable', 'array'],
            'invite_emails.*' => ['required', 'email:rfc,dns', 'distinct'],
            'access_method' => ['nullable', 'string', 'in:password,invite'],
        ]);

        $user = $request->user();

        // Ensure slug is unique for this specific photographer
        $slug = $validated['slug'];
        $count = Gallery::where('user_id', $user->id)->where('slug', $slug)->count();
        if ($count > 0) {
            $slug = "{$slug}-" . time();
        }

        $isPrivate = ($validated['visibility'] ?? null) === 'private';
        $accessMethod = $validated['access_method'] ?? null;

        $gallery = null;
        $invitationDataList = [];

        \Illuminate\Support\Facades\DB::transaction(function () use (&$gallery, &$invitationDataList, $user, $validated, $slug, $isPrivate, $accessMethod) {
            $gallery = Gallery::create([
                'user_id' => $user->id,
                'title' => $validated['title'],
                'slug' => $slug,
                'client_name' => $validated['client_name'] ?? null,
                'event_date' => $validated['event_date'] ?? null,
                'visibility' => $validated['visibility'] ?? Visibility::Public->value,
                'allow_photo_downloads' => $validated['allow_photo_downloads'] ?? true,
                'allow_gallery_downloads' => $validated['allow_gallery_downloads'] ?? true,
                'allow_google_photos' => $validated['allow_google_photos'] ?? true,
                'password_hash' => ($isPrivate && $accessMethod === 'password' && !empty($validated['password'])) ? bcrypt($validated['password']) : null,
                'password_hint' => ($isPrivate && $accessMethod === 'password') ? ($validated['password_hint'] ?? null) : null,
                'expires_at' => $validated['expires_at'] ?? null,
                'version' => 1,
            ]);

            // Create materialized stats row
            GalleryStats::create(['gallery_id' => $gallery->id]);

            // If private and access method is invite, create invitations
            if ($isPrivate && $accessMethod === 'invite' && !empty($validated['invite_emails'])) {
                foreach ($validated['invite_emails'] as $email) {
                    $rawToken = bin2hex(random_bytes(32));
                    $hashedToken = hash('sha256', $rawToken);

                    $invitation = \App\Models\GalleryInvitation::create([
                        'gallery_id' => $gallery->id,
                        'email' => strtolower(trim($email)),
                        'token' => $hashedToken,
                        'invited_by' => $user->id,
                        'expires_at' => $gallery->expires_at,
                    ]);

                    $invitationDataList[] = [
                        'raw_token' => $rawToken,
                        'email' => strtolower(trim($email)),
                    ];
                }
            }
        });

        // Dispatch queued emails after transaction commits
        if (!empty($invitationDataList) && $gallery) {
            $publicUrlService = app(\App\Services\PublicUrlService::class);
            foreach ($invitationDataList as $data) {
                try {
                    $inviteUrl = $publicUrlService->galleryUrl($user->username, $gallery->slug) . "?invite={$data['raw_token']}";
                    \Illuminate\Support\Facades\Mail::to($data['email'])->queue(
                        new \App\Mail\GalleryInvitation($gallery, $inviteUrl, $user->name)
                    );
                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\Log::warning(
                        'Gallery invitation email failed to queue',
                        [
                            'gallery_id' => $gallery->id,
                            'recipient' => $data['email'],
                            'error' => $e->getMessage(),
                        ]
                    );
                }
            }
        }

        return (new GalleryResource($gallery->load('stats')))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Display gallery details.
     * GET /api/v1/galleries/{uuid}
     */
    public function show(Request $request, string $uuid): JsonResponse
    {
        $gallery = Gallery::where('uuid', $uuid)->firstOrFail();

        $this->authorize('view', $gallery);

        $gallery->load(['stats', 'coverPhoto', 'invitations']);

        return (new GalleryResource($gallery))->response();
    }

    /**
     * Display public gallery details (slug-based).
     * GET /api/v1/public/galleries/{slug}
     */
    public function showPublic(Request $request, string $slug): GalleryResource
    {
        $gallery = Gallery::where('slug', $slug)
            ->with(['stats', 'coverPhoto', 'user'])
            ->firstOrFail();

        $errorResponse = $this->verifyGalleryAccess($gallery, $request);
        
        if ($errorResponse) {
            $errData = $errorResponse->getData(true);
            $gallery->access_granted = false;
            $gallery->error_code = $errData['code'] ?? 'ACCESS_DENIED';
            $gallery->error_message = $errData['message'] ?? 'Access denied.';
            $gallery->requires_password = $errData['requires_password'] ?? false;
            $gallery->password_hint = $errData['password_hint'] ?? null;
            $gallery->requires_invitation = $errData['requires_invitation'] ?? false;
        } else {
            $gallery->access_granted = true;

            \Illuminate\Support\Facades\DB::table('activity_logs')->insert([
                'gallery_id' => $gallery->id,
                'event' => 'gallery_viewed',
                'visitor_session_id' => $request->header('X-Visitor-Session-ID') ?: $request->cookie('visitor_session_id') ?: session()->getId(),
                'source' => $request->query('source') ?: $request->query('utm_source') ?: 'direct',
                'referrer' => $request->header('referer'),
                'campaign' => $request->query('utm_campaign'),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'created_at' => now(),
            ]);
        }

        return new GalleryResource($gallery);
    }

    /**
     * Get paginated photos of a public gallery.
     * GET /api/v1/public/galleries/{slug}/photos
     */
    public function publicPhotos(Request $request, string $slug): JsonResponse
    {
        $gallery = Gallery::where('slug', $slug)->firstOrFail();

        $errorResponse = $this->verifyGalleryAccess($gallery, $request);
        if ($errorResponse) {
            return $errorResponse;
        }

        $perPage = $request->integer('per_page', 60);
        $perPage = max(1, min(100, $perPage));

        $photos = $gallery->photos()
            ->orderBy('sort_date', 'asc')
            ->orderBy('sort_order', 'asc')
            ->orderBy('id', 'asc')
            ->cursorPaginate($perPage);

        return response()->json([
            'data' => \App\Http\Resources\V1\PhotoResource::collection($photos->items()),
            'next_cursor' => $photos->nextCursor() ? $photos->nextCursor()->encode() : null,
            'has_more' => $photos->hasMorePages(),
        ]);
    }

    /**
     * Get paginated photos of a gallery (private/studio dashboard).
     * GET /api/v1/galleries/{uuid}/photos
     */
    public function photos(Request $request, string $uuid): JsonResponse
    {
        $gallery = Gallery::where('uuid', $uuid)->firstOrFail();

        $this->authorize('view', $gallery);

        $perPage = $request->integer('per_page', 60);
        $perPage = max(1, min(100, $perPage));

        $photos = $gallery->photos()
            ->orderBy('sort_date', 'asc')
            ->orderBy('sort_order', 'asc')
            ->orderBy('id', 'asc')
            ->cursorPaginate($perPage);

        return response()->json([
            'data' => \App\Http\Resources\V1\PhotoResource::collection($photos->items()),
            'next_cursor' => $photos->nextCursor() ? $photos->nextCursor()->encode() : null,
            'has_more' => $photos->hasMorePages(),
        ]);
    }



    /**
     * Unlock a password-protected public gallery.
     * POST /api/v1/public/galleries/{slug}/unlock
     */
    public function unlockPublic(Request $request, string $slug): JsonResponse
    {
        $request->validate([
            'password' => ['required', 'string'],
        ]);

        $gallery = Gallery::where('slug', $slug)->firstOrFail();

        if (empty($gallery->password_hash)) {
            return response()->json([
                'code' => 'NOT_PASSWORD_PROTECTED',
                'message' => 'This gallery does not require a password.',
            ], 400);
        }

        if ($gallery->expires_at && $gallery->expires_at->isPast()) {
            return response()->json([
                'code' => 'GALLERY_EXPIRED',
                'message' => 'This gallery has expired.',
            ], 403);
        }

        if (\Illuminate\Support\Facades\Hash::check($request->password, $gallery->password_hash)) {
            $token = hash_hmac('sha256', $gallery->uuid, config('app.key'));
            return response()->json([
                'token' => $token,
                'message' => 'Gallery unlocked successfully.',
            ]);
        }

        return response()->json([
            'code' => 'INVALID_PASSWORD',
            'message' => 'The password you entered is incorrect.',
        ], 401);
    }

    /**
     * Update gallery with optimistic locking version protection.
     * PATCH /api/v1/galleries/{uuid}
     */
    public function update(Request $request, string $uuid): JsonResponse
    {
        $gallery = Gallery::where('uuid', $uuid)->firstOrFail();

        $this->authorize('update', $gallery);

        $validated = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'client_name' => ['nullable', 'string', 'max:255'],
            'visibility' => ['sometimes', 'string', 'in:public,private'],
            'allow_photo_downloads' => ['sometimes', 'boolean'],
            'allow_gallery_downloads' => ['sometimes', 'boolean'],
            'allow_google_photos' => ['sometimes', 'boolean'],
            'cover_photo_uuid' => ['nullable', 'string'],
            'clear_cover' => ['nullable', 'boolean'],
            'version' => ['required', 'integer'],
        ]);

        // Optimistic locking check
        if ((int) $validated['version'] !== $gallery->version) {
            return response()->json([
                'code' => 'CONCURRENCY_CONFLICT',
                'message' => 'The gallery was updated in another session. Please reload before saving changes.',
            ], 409);
        }

        $coverService = app(\App\Services\GalleryCoverService::class);

        if (!empty($validated['clear_cover'])) {
            $coverService->clearExplicitCover($gallery);
        } elseif (array_key_exists('cover_photo_uuid', $validated)) {
            $coverPhotoUuid = $validated['cover_photo_uuid'];
            if (empty($coverPhotoUuid)) {
                $coverService->clearExplicitCover($gallery);
            } else {
                $photo = \App\Models\Photo::where('gallery_id', $gallery->id)
                    ->where('uuid', $coverPhotoUuid)
                    ->whereNull('deleted_at')
                    ->first();
                if (!$photo) {
                    return response()->json([
                        'code' => 'INVALID_COVER_PHOTO',
                        'message' => 'The selected photo does not exist in this gallery.',
                    ], 422);
                }
                $coverService->setExplicitCover($gallery, $photo);
            }
        }

        $updateData = collect($validated)->except(['cover_photo_uuid', 'clear_cover', 'version'])->toArray();

        $gallery->update([
            ...$updateData,
            'version' => $gallery->version + 1,
        ]);

        return (new GalleryResource($gallery->load(['stats', 'coverPhoto'])))->response();
    }

    /**
     * Soft-delete a gallery.
     * DELETE /api/v1/galleries/{uuid}
     */
    public function destroy(Request $request, string $uuid): JsonResponse
    {
        $gallery = Gallery::where('uuid', $uuid)->firstOrFail();

        $this->authorize('delete', $gallery);

        $gallery->delete();

        return response()->json(null, 204);
    }

    /**
     * Record download analytics for a public gallery.
     * POST /api/v1/public/galleries/{slug}/download
     */
    public function recordDownload(Request $request, string $slug): JsonResponse
    {
        $gallery = Gallery::where('slug', $slug)->firstOrFail();

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
     * POST /api/v1/public/galleries/{slug}/favorite
     */
    public function toggleFavorite(Request $request, string $slug): JsonResponse
    {
        $gallery = Gallery::where('slug', $slug)->firstOrFail();

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
     * GET /api/v1/public/galleries/{slug}/download-zip
     */
    /**
     * Trigger background ZIP generation.
     * POST /public/galleries/{slug}/download-zip
     */
    public function triggerZipDownload(Request $request, string $slug): JsonResponse
    {
        $gallery = Gallery::where('slug', $slug)->firstOrFail();

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

        // Read email and opt-in settings from POST body
        $email = $request->input('email');
        $notifyWhenReady = (bool) $request->input('notify_when_ready', false);

        if ($notifyWhenReady && (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL))) {
            return response()->json([
                'code' => 'INVALID_EMAIL',
                'message' => 'A valid email address is required for notifications.',
            ], 422);
        }

        if ($download && ($download->status === 'ready' || $download->status === 'ready_with_errors') && $download->storage_path) {
            if (Storage::disk('b2')->exists($download->storage_path)) {
                // If it is already ready, but the user requested notifications and we haven't sent it yet, let's update email settings
                if ($notifyWhenReady && is_null($download->notification_sent_at)) {
                    $rawToken = bin2hex(random_bytes(32));
                    $download->update([
                        'email' => $email,
                        'notify_when_ready' => true,
                        'download_token_hash' => hash('sha256', $rawToken),
                    ]);
                    
                    // Since it's ready, we can trigger the notification immediately
                    \Illuminate\Support\Facades\DB::transaction(function () use ($download, $rawToken) {
                        $lockedDownload = \App\Models\GalleryDownload::where('id', $download->id)->lockForUpdate()->first();
                        if ($lockedDownload && is_null($lockedDownload->notification_sent_at)) {
                            $lockedDownload->update(['notification_sent_at' => now()]);
                            $downloadUrl = url("/api/v1/public/galleries/{$lockedDownload->gallery->slug}/download-zip/{$lockedDownload->id}/download?token={$rawToken}");
                            $emailAddr = $lockedDownload->email;
                            \Illuminate\Support\Facades\DB::afterCommit(function () use ($lockedDownload, $downloadUrl, $emailAddr) {
                                \Illuminate\Support\Facades\Mail::to($emailAddr)->queue(
                                    new \App\Mail\GalleryZipReadyMail($lockedDownload, $downloadUrl)
                                );
                            });
                        }
                    });
                }

                $queryParams = [];
                if ($request->query('invite')) {
                    $queryParams['invite'] = $request->query('invite');
                }
                if ($request->query('token')) {
                    $queryParams['token'] = $request->query('token');
                } elseif ($request->header('X-Gallery-Token')) {
                    $queryParams['token'] = $request->header('X-Gallery-Token');
                }

                $downloadUrl = url("/api/v1/public/galleries/{$slug}/download-zip/{$download->id}/download") . (empty($queryParams) ? '' : '?' . http_build_query($queryParams));

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
            // Update email settings if the user is providing them now
            if ($notifyWhenReady) {
                $download->update([
                    'email' => $email,
                    'notify_when_ready' => true,
                ]);
            }
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
            'email' => $notifyWhenReady ? $email : null,
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
    public function getZipDownloadStatus(Request $request, string $slug, int $id): JsonResponse
    {
        $gallery = Gallery::where('slug', $slug)->firstOrFail();

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
            if ($request->query('token')) {
                $queryParams['token'] = $request->query('token');
            } elseif ($request->header('X-Gallery-Token')) {
                $queryParams['token'] = $request->header('X-Gallery-Token');
            }

            $downloadUrl = url("/api/v1/public/galleries/{$slug}/download-zip/{$download->id}/download") . (empty($queryParams) ? '' : '?' . http_build_query($queryParams));
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
    public function downloadZipFile(Request $request, string $slug, int $id)
    {
        $gallery = Gallery::with('user')->where('slug', $slug)->firstOrFail();
        
        $frontendUrl = config('app.frontend_url') ?: 'https://ifotoset.com';
        $exportUrl = rtrim($frontendUrl, '/') . '/p/' . rawurlencode(strtolower($gallery->user->username)) . '/' . rawurlencode($gallery->slug) . '/export';

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

    /**
     * Get analytics and visitor logs for a specific gallery.
     * GET /api/v1/galleries/{uuid}/analytics
     */
    public function analytics(Request $request, string $uuid): JsonResponse
    {
        $gallery = Gallery::where('uuid', $uuid)->firstOrFail();
        $this->authorize('view', $gallery);

        // 1. Overview stats
        $uniqueVisitors = \Illuminate\Support\Facades\DB::table('activity_logs')
            ->where('gallery_id', $gallery->id)
            ->whereNotNull('visitor_session_id')
            ->distinct()
            ->count('visitor_session_id');

        $totalViews = \Illuminate\Support\Facades\DB::table('activity_logs')
            ->where('gallery_id', $gallery->id)
            ->where('event', 'gallery_viewed')
            ->count();

        $totalDownloads = \Illuminate\Support\Facades\DB::table('activity_logs')
            ->where('gallery_id', $gallery->id)
            ->whereIn('event', ['photo_downloaded', 'gallery_zip_file_downloaded'])
            ->count();

        $totalFavorites = \Illuminate\Support\Facades\DB::table('activity_logs')
            ->where('gallery_id', $gallery->id)
            ->where('event', 'photo_favorited')
            ->count();

        // 2. Visitor Activity & Emails
        $visitors = \Illuminate\Support\Facades\DB::table('activity_logs')
            ->where('gallery_id', $gallery->id)
            ->select(
                \Illuminate\Support\Facades\DB::raw("JSON_UNQUOTE(JSON_EXTRACT(properties, '$.email')) as email"),
                \Illuminate\Support\Facades\DB::raw("SUM(CASE WHEN event = 'photo_downloaded' OR event = 'gallery_zip_file_downloaded' THEN 1 ELSE 0 END) as downloads_count"),
                \Illuminate\Support\Facades\DB::raw("SUM(CASE WHEN event = 'photo_favorited' THEN 1 ELSE 0 END) as favorites_count"),
                \Illuminate\Support\Facades\DB::raw("MAX(created_at) as last_active_at")
            )
            ->groupBy(\Illuminate\Support\Facades\DB::raw("JSON_UNQUOTE(JSON_EXTRACT(properties, '$.email'))"))
            ->orderBy('last_active_at', 'desc')
            ->get()
            ->map(function ($row) {
                return [
                    'email' => ($row->email === 'null' || !$row->email) ? null : $row->email,
                    'downloads' => (int) $row->downloads_count,
                    'favorites' => (int) $row->favorites_count,
                    'last_active' => $row->last_active_at,
                ];
            });

        // 3. Recent Activity logs
        $recentActivity = \Illuminate\Support\Facades\DB::table('activity_logs')
            ->where('gallery_id', $gallery->id)
            ->select('event', 'properties', 'created_at')
            ->orderBy('created_at', 'desc')
            ->take(30)
            ->get()
            ->map(function ($row) {
                $properties = json_decode($row->properties, true) ?: [];
                return [
                    'event' => $row->event,
                    'email' => $properties['email'] ?? null,
                    'photo_uuid' => $properties['photo_uuid'] ?? null,
                    'created_at' => $row->created_at,
                ];
            });

        return response()->json([
            'overview' => [
                'views' => $totalViews,
                'downloads' => $totalDownloads,
                'favorites' => $totalFavorites,
                'visitors' => $uniqueVisitors,
            ],
            'visitors' => $visitors,
            'recent_activity' => $recentActivity,
        ]);
    }
}
?>
