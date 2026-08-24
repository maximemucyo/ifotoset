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
        if ($download->status === 'ready') {
            return;
        }

        $download->update(['status' => 'processing']);

        $photos = Photo::where('gallery_id', $gallery->id)
            ->where('status', PhotoStatus::Ready->value)
            ->orderBy('id')
            ->lazy();

        if ($photos->isEmpty()) {
            $download->update([
                'status' => 'failed',
                'error' => 'No ready photos in gallery to pack.',
            ]);
            return;
        }

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

                if (Storage::disk('b2')->exists($photoPath)) {
                    $localPhotoPath = $tempDir . '/' . uniqid() . '_' . $photo->filename;
                    
                    $readStream = Storage::disk('b2')->readStream($photoPath);
                    if ($readStream) {
                        $writeStream = fopen($localPhotoPath, 'w');
                        stream_copy_to_stream($readStream, $writeStream);
                        fclose($writeStream);
                        fclose($readStream);
                        
                        $zip->addFile($localPhotoPath, $fileNameInZip);
                    }
                }
            }

            $zip->close();

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

            // Update database mapping atomically
            $download->update([
                'status' => 'ready',
                'storage_path' => $b2ZipPath,
                'size' => Storage::disk('b2')->size($b2ZipPath),
                'generated_at' => now(),
            ]);

        } catch (Throwable $e) {
            Log::error('ZIP generation failed: ' . $e->getMessage(), [
                'gallery_id' => $gallery->id,
                'download_id' => $download->id,
                'exception' => $e,
            ]);

            $download->update([
                'status' => 'failed',
                'error' => $e->getMessage(),
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
