<?php

namespace App\Jobs;

use App\Models\GooglePhotoSync;
use App\Models\Photo;
use App\Models\GooglePhotoCredential;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class SyncGooglePhotosJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 600;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $syncId,
        public ?array $photoUuids = null
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $sync = GooglePhotoSync::find($this->syncId);
        if (!$sync) {
            return;
        }

        $credential = $sync->credential;
        if (!$credential) {
            $sync->update([
                'status' => 'failed',
                'error' => 'Google credentials mapping not found.',
            ]);
            return;
        }

        try {
            $this->refreshTokenIfNeeded($credential);

            // Create Album on Google Photos
            $response = Http::withToken($credential->access_token)
                ->post('https://photoslibrary.googleapis.com/v1/albums', [
                    'album' => [
                        'title' => 'Ifotoset - ' . $sync->gallery->title,
                    ],
                ]);

            if ($response->failed()) {
                throw new \Exception('Google Photos Album creation failed: ' . $response->body());
            }

            $albumId = $response->json('id');
            $albumUrl = $response->json('productUrl');

            // Retrieve target photo collection
            $query = Photo::where('gallery_id', $sync->gallery_id)
                ->where('status', \App\Enums\PhotoStatus::Ready->value);

            if (!empty($this->photoUuids)) {
                $query->whereIn('uuid', $this->photoUuids);
            }

            $photos = $query->get();

            if ($photos->isEmpty()) {
                $sync->update([
                    'status' => 'completed',
                    'album_id' => $albumId,
                    'album_url' => $albumUrl,
                    'total_photos' => 0,
                    'completed_at' => now(),
                ]);
                return;
            }

            $sync->update([
                'status' => 'processing',
                'album_id' => $albumId,
                'album_url' => $albumUrl,
                'total_photos' => $photos->count(),
                'started_at' => now(),
            ]);

            // Chunk in groups of 50 to match Google batchCreate size limits
            $chunks = $photos->chunk(50);
            foreach ($chunks as $chunk) {
                $photoIds = $chunk->pluck('id')->toArray();
                UploadGooglePhotosChunkJob::dispatch($sync->id, $photoIds);
            }

        } catch (Throwable $e) {
            Log::error('Google Photos Album initialization job failed', [
                'sync_id' => $this->syncId,
                'exception' => $e->getMessage(),
            ]);

            $sync->update([
                'status' => 'failed',
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Refresh Google Access Token if close to expiry.
     */
    protected function refreshTokenIfNeeded(GooglePhotoCredential $credential): void
    {
        if (!$credential->refresh_token) {
            return;
        }

        $expiresAt = $credential->expires_at;
        if (!$expiresAt || $expiresAt->subMinutes(5)->isPast()) {
            $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
                'client_id' => config('services.google.client_id'),
                'client_secret' => config('services.google.client_secret'),
                'refresh_token' => $credential->refresh_token,
                'grant_type' => 'refresh_token',
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $credential->update([
                    'access_token' => $data['access_token'],
                    'expires_at' => now()->addSeconds($data['expires_in']),
                ]);
            } else {
                Log::warning('Failed to refresh Google Photos OAuth access token', [
                    'sync_id' => $this->syncId,
                    'response' => $response->body(),
                ]);
            }
        }
    }
}
