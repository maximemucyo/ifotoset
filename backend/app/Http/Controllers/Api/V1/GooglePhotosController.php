<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Gallery;
use App\Models\GooglePhotoAuthorization;
use App\Models\GooglePhotoSync;
use App\Models\GooglePhotoCredential;
use App\Models\Photo;
use App\Traits\VerifiesGalleryAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class GooglePhotosController extends Controller
{
    use VerifiesGalleryAccess;

    /**
     * Generate Google Photos authorization url.
     * POST /api/v1/public/galleries/{slug}/google-photos/authorize
     */
    public function authorizePhotos(Request $request, string $slug): JsonResponse
    {
        $gallery = Gallery::where('slug', $slug)->firstOrFail();

        $errorResponse = $this->verifyGalleryAccess($gallery, $request);
        if ($errorResponse) {
            return $errorResponse;
        }

        if (!$gallery->allow_google_photos) {
            return response()->json([
                'code' => 'GOOGLE_PHOTOS_DISABLED',
                'message' => 'Google Photos integration is disabled for this gallery.',
            ], 403);
        }

        $photoCount = Photo::where('gallery_id', $gallery->id)
            ->where('status', \App\Enums\PhotoStatus::Ready->value)
            ->count();

        if ($photoCount === 0) {
            return response()->json([
                'status' => 'empty',
                'message' => 'This gallery has no ready photos to sync.',
            ], 400);
        }

        // Validate selected photos if provided
        $photoUuids = $request->input('photo_uuids');
        if ($photoUuids) {
            $validPhotos = Photo::where('gallery_id', $gallery->id)
                ->whereIn('uuid', $photoUuids)
                ->where('status', \App\Enums\PhotoStatus::Ready->value)
                ->get();

            if ($validPhotos->count() !== count($photoUuids)) {
                return response()->json([
                    'code' => 'INVALID_SELECTION',
                    'message' => 'Some of the selected photos are invalid or not ready.',
                ], 422);
            }
        }

        // Read email and opt-in settings from POST body
        $email = $request->input('email');
        $notifyWhenReady = (bool) $request->input('notify_when_ready', false);

        if ($notifyWhenReady && (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL))) {
            return response()->json([
                'code' => 'INVALID_EMAIL',
                'message' => 'A valid email address is required for notifications.',
            ], 422);
        }

        $state = Str::random(40);

        $auth = GooglePhotoAuthorization::create([
            'gallery_id' => $gallery->id,
            'state' => $state,
            'photo_uuids' => $photoUuids,
            'expires_at' => now()->addMinutes(15),
            'email' => $notifyWhenReady ? $email : null,
            'notify_when_ready' => $notifyWhenReady,
        ]);

        $clientId = config('services.google.client_id');
        $redirectUri = config('services.google.redirect_uri');

        $authUrl = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => 'https://www.googleapis.com/auth/photoslibrary.appendonly',
            'state' => $state,
            'access_type' => 'offline',
            'prompt' => 'consent',
        ]);

        return response()->json([
            'url' => $authUrl,
            'state' => $state,
        ]);
    }

    /**
     * Swap code and trigger background sync.
     * POST /api/v1/public/google-photos/callback
     */
    public function handleCallback(Request $request): JsonResponse
    {
        $request->validate([
            'code' => ['required', 'string'],
            'state' => ['required', 'string'],
        ]);

        // Find state session
        $auth = GooglePhotoAuthorization::where('state', $request->state)->first();

        if (!$auth) {
            return response()->json([
                'code' => 'INVALID_STATE',
                'message' => 'Authorization session not found.',
            ], 400);
        }

        if ($auth->expires_at->isPast()) {
            return response()->json([
                'code' => 'EXPIRED_SESSION',
                'message' => 'The authorization session has expired.',
            ], 400);
        }

        if ($auth->consumed_at) {
            return response()->json([
                'code' => 'REPLAY_ATTEMPT',
                'message' => 'This authorization session has already been used.',
            ], 400);
        }

        // Single-use enforcement
        $auth->update(['consumed_at' => now()]);

        $gallery = $auth->gallery;
        
        // Swapping code for tokens
        $tokenResponse = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'client_id' => config('services.google.client_id'),
            'client_secret' => config('services.google.client_secret'),
            'redirect_uri' => config('services.google.redirect_uri'),
            'code' => $request->code,
            'grant_type' => 'authorization_code',
        ]);

        if ($tokenResponse->failed()) {
            return response()->json([
                'code' => 'TOKEN_EXCHANGE_FAILED',
                'message' => 'Failed to retrieve access token from Google.',
            ], 400);
        }

        $tokens = $tokenResponse->json();

        // Create persistence-backed Google Photo Sync record
        $sync = GooglePhotoSync::create([
            'gallery_id' => $gallery->id,
            'status' => 'pending',
            'email' => $auth->email,
            'notify_when_ready' => (bool)$auth->notify_when_ready,
        ]);

        // Secure credential persistence
        GooglePhotoCredential::create([
            'sync_id' => $sync->id,
            'access_token' => $tokens['access_token'],
            'refresh_token' => $tokens['refresh_token'] ?? null,
            'expires_at' => isset($tokens['expires_in']) ? now()->addSeconds($tokens['expires_in']) : null,
        ]);

        // Dispatch background parent job passing ONLY the sync primary id
        \App\Jobs\SyncGooglePhotosJob::dispatch($sync->id, $auth->photo_uuids);

        return response()->json([
            'status' => 'processing',
            'sync_uuid' => $sync->uuid,
            'gallery_slug' => $gallery->slug,
        ]);
    }

    /**
     * Get Google Photos sync progress.
     * GET /api/v1/public/galleries/{slug}/google-photos/syncs/{uuid}/status
     */
    public function syncStatus(Request $request, string $slug, string $uuid): JsonResponse
    {
        $gallery = Gallery::where('slug', $slug)->firstOrFail();

        $errorResponse = $this->verifyGalleryAccess($gallery, $request);
        if ($errorResponse) {
            return $errorResponse;
        }

        $sync = GooglePhotoSync::where('gallery_id', $gallery->id)
            ->where('uuid', $uuid)
            ->firstOrFail();

        $progressService = app(\App\Services\ExportProgressService::class);
        $progress = $progressService->calculate(
            $sync->started_at,
            $sync->completed_at,
            $sync->total_photos,
            $sync->processed_photos,
            $sync->failed_photos,
            $sync->status
        );

        return response()->json([
            'uuid' => $sync->uuid,
            'status' => $sync->status,
            'email' => $sync->email,
            'notify_when_ready' => (bool)$sync->notify_when_ready,
            'total_photos' => $sync->total_photos,
            'processed_photos' => $sync->processed_photos,
            'failed_photos' => $sync->failed_photos,
            'album_url' => $sync->album_url,
            'error' => $sync->error,
            'completed_at' => $sync->completed_at?->toIso8601String(),
            'percentage' => $progress['percentage'],
            'remaining_seconds' => $progress['remaining_seconds'],
            'estimated_finish_time' => $progress['estimated_finish_time'],
        ]);
    }

    /**
     * Update notification subscription for a sync.
     * POST /api/v1/public/galleries/{slug}/google-photos/syncs/{uuid}/notify
     */
    public function updateSyncNotification(Request $request, string $slug, string $uuid): JsonResponse
    {
        $gallery = Gallery::where('slug', $slug)->firstOrFail();

        $errorResponse = $this->verifyGalleryAccess($gallery, $request);
        if ($errorResponse) {
            return $errorResponse;
        }

        $sync = GooglePhotoSync::where('gallery_id', $gallery->id)
            ->where('uuid', $uuid)
            ->firstOrFail();

        $email = $request->input('email');
        $notifyWhenReady = (bool) $request->input('notify_when_ready', false);

        if ($notifyWhenReady && (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL))) {
            return response()->json([
                'code' => 'INVALID_EMAIL',
                'message' => 'A valid email address is required for notifications.',
            ], 422);
        }

        $sync->update([
            'email' => $notifyWhenReady ? $email : null,
            'notify_when_ready' => $notifyWhenReady,
        ]);

        // If it is already completed, and we haven't sent a notification yet, queue it immediately
        if ($sync->status === 'completed' || $sync->status === 'completed_with_errors') {
            if ($notifyWhenReady && is_null($sync->notification_sent_at)) {
                \Illuminate\Support\Facades\DB::transaction(function () use ($sync) {
                    $lockedSync = GooglePhotoSync::where('id', $sync->id)->lockForUpdate()->first();
                    if ($lockedSync && is_null($lockedSync->notification_sent_at)) {
                        $lockedSync->update(['notification_sent_at' => now()]);
                        $emailAddr = $lockedSync->email;
                        \Illuminate\Support\Facades\DB::afterCommit(function () use ($lockedSync, $emailAddr) {
                            \Illuminate\Support\Facades\Mail::to($emailAddr)->queue(
                                new \App\Mail\GooglePhotosSyncCompletedMail($lockedSync)
                            );
                        });
                    }
                });
            }
        }

        return response()->json([
            'status' => 'success',
            'email' => $sync->email,
            'notify_when_ready' => (bool)$sync->notify_when_ready,
        ]);
    }
}
