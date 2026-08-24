<?php

namespace App\Jobs;

use App\Models\GooglePhotoSync;
use App\Models\GooglePhotoCredential;
use App\Models\Photo;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class UploadGooglePhotosChunkJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 300;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $syncId,
        public array $photoIds
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
            return;
        }

        $this->refreshTokenIfNeeded($credential);

        $photos = Photo::whereIn('id', $this->photoIds)->get();

        $successCount = 0;
        $failedCount = 0;
        $uploadTokens = [];

        foreach ($photos as $photo) {
            $photoPath = $photo->path . '/' . $photo->filename;
            $tempPath = storage_path('app/tmp/uploads/' . uniqid() . '_' . $photo->filename);
            
            File::ensureDirectoryExists(dirname($tempPath));

            try {
                if (!Storage::disk('b2')->exists($photoPath)) {
                    throw new \Exception("File missing in storage bucket: {$photoPath}");
                }

                $readStream = Storage::disk('b2')->readStream($photoPath);
                if (!$readStream) {
                    throw new \Exception("Failed to open read stream from storage.");
                }

                $writeStream = fopen($tempPath, 'w');
                stream_copy_to_stream($readStream, $writeStream);
                fclose($writeStream);
                fclose($readStream);

                if (!File::exists($tempPath) || File::size($tempPath) === 0) {
                    throw new \Exception("Local temporary image is empty or missing.");
                }

                // POST raw image bytes to Google upload API
                $response = Http::withToken($credential->access_token)
                    ->withHeaders([
                        'Content-type' => 'application/octet-stream',
                        'X-Goog-Upload-Content-Type' => $photo->mime_type,
                        'X-Goog-Upload-Protocol' => 'raw',
                    ])
                    ->withBody(fopen($tempPath, 'r'), $photo->mime_type)
                    ->post('https://photoslibrary.googleapis.com/v1/uploads');

                if ($response->failed()) {
                    throw new \Exception("Google Photos upload failed: " . $response->body());
                }

                $uploadToken = $response->body();
                $uploadTokens[] = [
                    'token' => $uploadToken,
                    'photo_id' => $photo->id,
                ];
                $successCount++;

            } catch (Throwable $e) {
                Log::warning('Single photo Google sync upload failed', [
                    'sync_id' => $this->syncId,
                    'photo_id' => $photo->id,
                    'error' => $e->getMessage(),
                ]);
                $failedCount++;
            } finally {
                if (File::exists($tempPath)) {
                    File::delete($tempPath);
                }
            }
        }

        // Call batchCreate if there are successful uploads
        if (!empty($uploadTokens)) {
            try {
                $newMediaItems = array_map(fn($item) => [
                    'description' => 'Synchronized from Ifotoset client gallery',
                    'simpleMediaItem' => [
                        'uploadToken' => $item['token'],
                    ],
                ], $uploadTokens);

                $batchResponse = Http::withToken($credential->access_token)
                    ->post('https://photoslibrary.googleapis.com/v1/mediaItems:batchCreate', [
                        'albumId' => $sync->album_id,
                        'newMediaItems' => $newMediaItems,
                    ]);

                if ($batchResponse->failed()) {
                    throw new \Exception('batchCreate request failed: ' . $batchResponse->body());
                }

                // Walk item results to deduct individual failures
                $results = $batchResponse->json('newMediaItemResults') ?? [];
                foreach ($results as $res) {
                    $status = $res['status'] ?? [];
                    if (isset($status['code']) && $status['code'] !== 0) {
                        $failedCount++;
                        $successCount--;
                    }
                }

            } catch (Throwable $e) {
                Log::error('Google Photos batchCreate failed', [
                    'sync_id' => $this->syncId,
                    'error' => $e->getMessage(),
                ]);
                // Revert success counts to failed
                $failedCount += $successCount;
                $successCount = 0;
            }
        }

        // Atomic row locking sync database update
        DB::transaction(function () use ($sync, $successCount, $failedCount) {
            $lockSync = GooglePhotoSync::where('id', $sync->id)->lockForUpdate()->first();
            if ($lockSync) {
                $lockSync->processed_photos += $successCount;
                $lockSync->failed_photos += $failedCount;

                if ($lockSync->processed_photos + $lockSync->failed_photos >= $lockSync->total_photos) {
                    $lockSync->status = $lockSync->failed_photos > 0 ? 'completed_with_errors' : 'completed';
                    $lockSync->completed_at = now();
                }
                $lockSync->save();
            }
        });
    }

    /**
     * Refresh credential token if close to expiry.
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
                Log::warning('Failed to refresh Google Photos OAuth access token inside chunk', [
                    'sync_id' => $this->syncId,
                    'response' => $response->body(),
                ]);
            }
        }
    }
}
