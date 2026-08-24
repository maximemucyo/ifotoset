<?php

namespace App\Jobs;

use App\Enums\PhotoStatus;
use App\Models\Gallery;
use App\Models\GalleryDownload;
use App\Models\Photo;
use App\Services\StorageService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;
use ZipArchive;

class GenerateGalleryZipJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 600; // 10 minutes timeout for large galleries

    public int $uniqueFor = 600;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $galleryId,
        public int $downloadId
    ) {}

    /**
     * Unique queue identifier.
     */
    public function uniqueId(): string
    {
        return 'generate_zip_' . $this->galleryId;
    }

    /**
     * Execute the job.
     */
    public function handle(StorageService $storageService): void
    {
        $gallery = Gallery::find($this->galleryId);
        $download = GalleryDownload::find($this->downloadId);

        if (!$gallery || !$download) {
            return;
        }

        // Double check status
        if ($download->status === 'ready' || $download->status === 'ready_with_errors') {
            return;
        }

        $totalPhotos = Photo::where('gallery_id', $gallery->id)
            ->where('status', PhotoStatus::Ready->value)
            ->count();

        if ($totalPhotos === 0) {
            $download->update([
                'status' => 'failed',
                'error' => 'No ready photos in gallery to pack.',
            ]);
            return;
        }

        $download->update([
            'status' => 'processing',
            'total_photos' => $totalPhotos,
            'processed_photos' => 0,
            'failed_photos' => 0,
            'started_at' => now(),
            'completed_at' => null,
            'error' => null,
        ]);

        $photos = Photo::where('gallery_id', $gallery->id)
            ->where('status', PhotoStatus::Ready->value)
            ->orderBy('id')
            ->lazy();

        $tempJobId = uniqid();
        $tempDir = storage_path("app/tmp/zip_extraction/{$gallery->uuid}_{$tempJobId}");
        $tempZipPath = storage_path("app/tmp/zips/{$gallery->uuid}_{$tempJobId}.tmp");

        File::ensureDirectoryExists($tempDir);
        File::ensureDirectoryExists(dirname($tempZipPath));

        $zip = new ZipArchive();
        if ($zip->open($tempZipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            $download->update([
                'status' => 'failed',
                'error' => 'Could not initialize ZipArchive.',
            ]);
            return;
        }

        $addedNames = [];
        $processedCount = 0;
        $failedCount = 0;
        $lastDbUpdateAt = microtime(true);

        try {
            foreach ($photos as $photo) {
                $photoPath = $photo->path . '/' . $photo->filename;
                
                // Sanitize and handle filename collisions inside the archive
                $cleanName = basename($photo->original_filename);
                // Remove directory traversal sequences
                $cleanName = str_replace(['..', '/', '\\'], '', $cleanName);
                
                $fileNameInZip = $cleanName;
                $counter = 1;
                while (in_array($fileNameInZip, $addedNames)) {
                    $pathInfo = pathinfo($cleanName);
                    $fileNameInZip = $pathInfo['filename'] . " ({$counter})." . ($pathInfo['extension'] ?? '');
                    $counter++;
                }
                $addedNames[] = $fileNameInZip;

                $success = false;
                if (Storage::disk('b2')->exists($photoPath)) {
                    $localPhotoPath = $tempDir . '/' . uniqid() . '_' . $photo->filename;
                    
                    $readStream = Storage::disk('b2')->readStream($photoPath);
                    if ($readStream) {
                        $writeStream = fopen($localPhotoPath, 'w');
                        stream_copy_to_stream($readStream, $writeStream);
                        fclose($writeStream);
                        fclose($readStream);
                        
                        if (File::exists($localPhotoPath) && File::size($localPhotoPath) > 0) {
                            if ($zip->addFile($localPhotoPath, $fileNameInZip)) {
                                $success = true;
                            }
                        }
                    }
                }

                if ($success) {
                    $processedCount++;
                } else {
                    $failedCount++;
                }

                // Batch database updates: every 10 photos or every 3 seconds
                $nowTime = microtime(true);
                if ((($processedCount + $failedCount) % 10 === 0) || ($nowTime - $lastDbUpdateAt >= 3.0)) {
                    $download->update([
                        'processed_photos' => $processedCount,
                        'failed_photos' => $failedCount,
                    ]);
                    $lastDbUpdateAt = $nowTime;
                }
            }

            $zip->close();

            // Final count update
            $download->update([
                'processed_photos' => $processedCount,
                'failed_photos' => $failedCount,
            ]);

            // Check if file size is > 0
            if (!File::exists($tempZipPath) || File::size($tempZipPath) === 0) {
                throw new \Exception("Generated ZIP file is empty or missing.");
            }

            // Versioned B2 storage destination
            $snapshotHash = $download->photo_snapshot_hash;
            $b2ZipPath = "galleries/{$gallery->uuid}/downloads/photos-{$snapshotHash}.zip";

            // Upload the ZIP to B2
            Storage::disk('b2')->put($b2ZipPath, fopen($tempZipPath, 'r'));

            // Verify upload exists in B2
            if (!Storage::disk('b2')->exists($b2ZipPath)) {
                throw new \Exception("Failed to verify ZIP presence in storage.");
            }

            $finalStatus = $failedCount > 0 ? 'ready_with_errors' : 'ready';

            // DB Transaction for Atomic state change and Mailable trigger
            \Illuminate\Support\Facades\DB::transaction(function () use ($download, $finalStatus, $b2ZipPath, $storageService) {
                // Lock row
                $lockedDownload = GalleryDownload::where('id', $download->id)->lockForUpdate()->first();
                if (!$lockedDownload) {
                    return;
                }

                $lockedDownload->update([
                    'status' => $finalStatus,
                    'storage_path' => $b2ZipPath,
                    'size' => Storage::disk('b2')->size($b2ZipPath),
                    'completed_at' => now(),
                    'generated_at' => now(),
                ]);

                // Safe check for opt-in mail trigger
                if ($lockedDownload->notify_when_ready && $lockedDownload->email && is_null($lockedDownload->notification_sent_at)) {
                    $lockedDownload->update([
                        'notification_sent_at' => now(),
                    ]);

                    $downloadUrl = $storageService->getCdnUrl(dirname($lockedDownload->storage_path), null, basename($lockedDownload->storage_path));
                    $email = $lockedDownload->email;

                    // Queue email after commit
                    \Illuminate\Support\Facades\DB::afterCommit(function () use ($lockedDownload, $downloadUrl, $email) {
                        \Illuminate\Support\Facades\Mail::to($email)->queue(
                            new \App\Mail\GalleryZipReadyMail($lockedDownload, $downloadUrl)
                        );
                    });
                }
            });

        } catch (Throwable $e) {
            Log::error('ZIP generation failed: ' . $e->getMessage(), [
                'gallery_id' => $gallery->id,
                'download_id' => $download->id,
                'exception' => $e,
            ]);

            $download->update([
                'status' => 'failed',
                'error' => $e->getMessage(),
                'completed_at' => now(),
            ]);
            
            throw $e;
        } finally {
            // Safe cleanup
            if (File::exists($tempZipPath)) {
                File::delete($tempZipPath);
            }
            if (File::exists($tempDir)) {
                File::deleteDirectory($tempDir);
            }
        }
    }
}
