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
            $frontendUrl = env('FRONTEND_URL', 'http://localhost:3000');
            foreach ($invitationDataList as $data) {
                try {
                    $inviteUrl = "{$frontendUrl}/g/{$gallery->slug}?invite={$data['raw_token']}";
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
    public function showPublic(Request $request, string $slug): JsonResponse
    {
        $gallery = Gallery::where('slug', $slug)
            ->with(['stats', 'coverPhoto'])
            ->firstOrFail();

        $errorResponse = $this->verifyGalleryAccess($gallery, $request);
        if ($errorResponse) {
            return $errorResponse;
        }

        $visitorSession = $request->header('X-Visitor-Session-ID') ?: $request->cookie('visitor_session_id') ?: session()->getId();
        $source = $request->query('source') ?: $request->query('utm_source') ?: 'direct';
        $referrer = $request->header('referer');
        $campaign = $request->query('utm_campaign');

        \Illuminate\Support\Facades\DB::table('activity_logs')->insert([
            'gallery_id' => $gallery->id,
            'event' => 'gallery_viewed',
            'visitor_session_id' => $visitorSession,
            'source' => $source,
            'referrer' => $referrer,
            'campaign' => $campaign,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'created_at' => now(),
        ]);

        return (new GalleryResource($gallery))->response();
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
    public function downloadZip(Request $request, string $slug): JsonResponse
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
            ->first();

        $storageService = app(\App\Services\StorageService::class);

        if ($download && $download->status === 'ready' && $download->storage_path) {
            if (Storage::disk('b2')->exists($download->storage_path)) {
                // Increment downloads count when ZIP is served
                $gallery->stats()->increment('downloads_count');
                $gallery->stats()->update(['updated_at' => now()]);

                // Record public activity log
                $visitorSession = $request->header('X-Visitor-Session-ID') ?: $request->cookie('visitor_session_id') ?: session()->getId();
                \Illuminate\Support\Facades\DB::table('activity_logs')->insert([
                    'gallery_id' => $gallery->id,
                    'event' => 'gallery_zipped_downloaded',
                    'visitor_session_id' => $visitorSession,
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'created_at' => now(),
                ]);

                return response()->json([
                    'status' => 'ready',
                    'download_url' => $storageService->getCdnUrl(dirname($download->storage_path), null, basename($download->storage_path)),
                    'size' => $download->size,
                ]);
            } else {
                // ZIP was deleted in B2, reset mapping
                $download->delete();
                $download = null;
            }
        }

        if ($download && $download->status === 'processing') {
            return response()->json([
                'status' => 'processing',
            ]);
        }

        // Dispatch background packaging job if missing or stale
        if (!$download) {
            $download = \App\Models\GalleryDownload::create([
                'gallery_id' => $gallery->id,
                'status' => 'pending',
                'photo_snapshot_hash' => $snapshotHash,
            ]);
        } else {
            $download->update([
                'status' => 'pending',
            ]);
        }

        \App\Jobs\GenerateGalleryZipJob::dispatch($gallery->id, $download->id);

        return response()->json([
            'status' => 'processing',
        ]);
    }
}
?>
