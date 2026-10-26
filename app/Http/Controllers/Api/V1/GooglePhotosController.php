<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\GooglePhotoAuthorization;
use App\Models\GooglePhotoSync;
use App\Models\GooglePhotoCredential;
use App\Models\Photo;
use App\Queries\GalleryQuery;
use App\Traits\VerifiesGalleryAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class GooglePhotosController extends Controller
{
    use VerifiesGalleryAccess;

    public function __construct(
        protected GalleryQuery $galleryQuery
    ) {}

    /**
     * OAuth redirect target registered with Google. Defaults to this app's
     * own callback route so no separate frontend is needed.
     */
    public static function redirectUri(): string
    {
        return config('services.google.redirect_uri') ?: route('google-photos.callback');
    }

    /**
     * Generate Google Photos authorization url.
     * POST /api/v1/public/galleries/{username}/{slug}/google-photos/authorize
     */
    public function authorizePhotos(Request $request, string $username, string $slug): JsonResponse
    {
        $gallery = $this->galleryQuery->findForUser($username, $slug);

        $errorResponse = $this->verifyGalleryAccess($gallery, $request);
        if ($errorResponse) {
            return $errorResponse;
        }

        if (!$gallery->googlePhotosExportEnabled()) {
            return response()->json([
                'code' => 'GOOGLE_PHOTOS_DISABLED',
                'message' => 'Google Photos integration is disabled for this gallery.',
            ], 403);
        }

        if ($pinError = $this->verifyDownloadPin($gallery, $request)) {
            return $pinError;
        }

        $photoCount = Photo::where('gallery_id', $gallery->id)
            ->where('is_hidden', false)
            ->where('status', \App\Enums\PhotoStatus::Ready->value)
            ->count();

        if ($photoCount === 0) {
            return response()->json([
                'status' => 'empty',
                'message' => 'This gallery has no ready photos to sync.',
            ], 400);
        }

        // Validate selected photos if provided
        $photoUuids = $request->input('photo_uuids') ?: $request->input('favorite_uuids');
        if ($photoUuids !== null && !is_array($photoUuids)) {
            return response()->json([
                'code' => 'INVALID_SELECTION',
                'message' => 'The photo selection is invalid.',
            ], 422);
        }
        if ($photoUuids) {
            $validPhotos = Photo::where('gallery_id', $gallery->id)
                ->where('is_hidden', false)
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
        $redirectUri = self::redirectUri();

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
     * Google redirects the visitor here after consent. Swap the code for
     * tokens, start the background sync, and send the visitor back to the
     * gallery export page to follow progress.
     * GET /google-photos/callback
     */
    public function handleCallback(Request $request): RedirectResponse
    {
        $state = (string) $request->query('state');
        $auth = $state !== '' ? GooglePhotoAuthorization::where('state', $state)->first() : null;

        if (!$auth || !$auth->gallery) {
            return redirect()->route('home');
        }

        $gallery = $auth->gallery()->with('user')->first();
        $exportUrl = fn (array $query) => route('public.gallery.export', [
            'username' => strtolower($gallery->user->username),
            'slug' => $gallery->slug,
            'type' => 'google-photos',
            ...$query,
        ]);

        if ($request->filled('error') || !$request->filled('code')) {
            return redirect()->to($exportUrl(['error' => 'denied']));
        }

        if ($auth->expires_at->isPast()) {
            return redirect()->to($exportUrl(['error' => 'expired']));
        }

        // Single-use enforcement, atomic so a replayed callback cannot start a second sync
        $claimed = GooglePhotoAuthorization::where('id', $auth->id)
            ->whereNull('consumed_at')
            ->update(['consumed_at' => now()]);

        if (!$claimed) {
            return redirect()->to($exportUrl(['error' => 'used']));
        }

        $tokenResponse = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'client_id' => config('services.google.client_id'),
            'client_secret' => config('services.google.client_secret'),
            'redirect_uri' => self::redirectUri(),
            'code' => $request->query('code'),
            'grant_type' => 'authorization_code',
        ]);

        if ($tokenResponse->failed() || empty($tokenResponse->json('access_token'))) {
            return redirect()->to($exportUrl(['error' => 'token']));
        }

        $tokens = $tokenResponse->json();

        $sync = GooglePhotoSync::create([
            'gallery_id' => $gallery->id,
            'status' => 'pending',
            'email' => $auth->email,
            'notify_when_ready' => (bool) $auth->notify_when_ready,
        ]);

        GooglePhotoCredential::create([
            'sync_id' => $sync->id,
            'access_token' => $tokens['access_token'],
            'refresh_token' => $tokens['refresh_token'] ?? null,
            'expires_at' => isset($tokens['expires_in']) ? now()->addSeconds($tokens['expires_in']) : null,
        ]);

        // Dispatch background parent job passing ONLY the sync primary id
        \App\Jobs\SyncGooglePhotosJob::dispatch($sync->id, $auth->photo_uuids);

        return redirect()->to($exportUrl(['sync' => $sync->uuid]));
    }

    /**
     * Get Google Photos sync progress.
     * GET /api/v1/public/galleries/{username}/{slug}/google-photos/syncs/{uuid}/status
     */
    public function syncStatus(Request $request, string $username, string $slug, string $uuid): JsonResponse
    {
        $gallery = $this->galleryQuery->findForUser($username, $slug);

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
     * POST /api/v1/public/galleries/{username}/{slug}/google-photos/syncs/{uuid}/notify
     */
    public function updateSyncNotification(Request $request, string $username, string $slug, string $uuid): JsonResponse
    {
        $gallery = $this->galleryQuery->findForUser($username, $slug);

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
