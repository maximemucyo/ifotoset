<?php

namespace App\Jobs;

use App\Enums\PhotoStatus;
use App\Models\Photo;
use App\Models\PhotoMetadata;
use App\Support\ExifReader;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;
use kornrunner\Blurhash\Blurhash;
use Throwable;

class ProcessPhotoJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The unique ID of the job.
     */
    public function uniqueId(): string
    {
        return $this->photo->uuid;
    }

    /**
     * The number of seconds after which the job's unique lock will be released.
     */
    public function uniqueFor(): int
    {
        return 600; // 10 minutes lock
    }

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * Calculate the number of seconds to wait before retrying the job.
     */
    public function backoff(): array
    {
        return [30, 120, 300];
    }

    /**
     * Create a new job instance.
     */
    /**
     * Create a new job instance.
     */
    public function __construct(
        protected Photo $photo
    ) {
        $this->queue = 'photos';
    }

    /**
     * Get the Photo model instance.
     */
    public function getPhoto(): Photo
    {
        return $this->photo;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        ini_set('memory_limit', '512M');

        $photo = $this->photo;

        // Find the current media job tracking record
        $mediaJob = \App\Models\MediaJob::where('photo_id', $photo->id)->orderBy('id', 'desc')->first();

        // Helper to update progress
        $updateProgress = function (string $progress) use ($mediaJob) {
            if ($mediaJob) {
                $mediaJob->update(['progress' => $progress]);
            }
        };

        // Ensure status is processing
        $photo->update(['status' => PhotoStatus::Processing->value]);

        $photoUuid = $photo->uuid;
        $tempDir = storage_path("app/tmp/photos/{$photoUuid}");
        
        // Ensure local temp folder exists
        File::ensureDirectoryExists($tempDir);

        $originalExtension = pathinfo($photo->filename, PATHINFO_EXTENSION);
        $tempFilePath = "{$tempDir}/original.{$originalExtension}";

        $uploadedVariants = [];

        try {
            // 1. Download original from storage
            $updateProgress('Downloading Original');
            $originalPath = $photo->path . '/' . $photo->filename;
            $originalContent = Storage::disk('b2')->get($originalPath);
            
            if (!$originalContent) {
                throw new \Exception("Could not retrieve original image from storage.");
            }

            File::put($tempFilePath, $originalContent);

            // Ensure temporary file is fully flushed to disk before validation
            clearstatcache(true, $tempFilePath);
            
            // Verify MIME type using content-inspection (fall back to current DB value if fail)
            $verifiedMimeType = File::mimeType($tempFilePath) ?? $photo->mime_type;

            // 2. Extract Metadata
            $updateProgress('Extracting Metadata');
            $exifData = ExifReader::read($tempFilePath);

            // 3. Initialize Intervention Image Manager with GD Driver
            $manager = new ImageManager(new Driver());
            $image = $manager->read($tempFilePath);

            // 4. Auto-rotate image using EXIF orientation before resizing
            $image->orient();

            // Capture final rotated dimensions
            $width = $image->width();
            $height = $image->height();

            // 5. Generate responsive variants (XL -> XS) sequentially
            $updateProgress('Generating & Uploading WebP');
            $variants = config('images.variants', [
                'xs' => 200,
                'sm' => 480,
                'md' => 960,
                'lg' => 1600,
                'xl' => 2560,
            ]);
            // Sort variants descending by size to allow sequential downscaling
            arsort($variants);
            $quality = config('images.quality', 82);
            $baseName = pathinfo($photo->filename, PATHINFO_FILENAME);

            foreach ($variants as $sizeName => $targetWidth) {
                $updateProgress('Generating WebP (' . strtoupper($sizeName) . ')');

                // If original/current image is wider than the target width, scale it down in-place
                if ($image->width() > $targetWidth) {
                    $image->scale(width: $targetWidth);
                }

                // Encode to WebP
                $encodedWebp = $image->toWebp($quality);

                // Upload variant to storage
                $variantPath = $photo->path . '/' . $baseName . '_' . $sizeName . '.webp';
                Storage::disk('b2')->put($variantPath, (string) $encodedWebp);

                // Verify upload succeeded
                if (!Storage::disk('b2')->exists($variantPath)) {
                    throw new \Exception("Failed to verify uploaded variant: {$sizeName}");
                }

                $uploadedVariants[] = $variantPath;

                unset($encodedWebp);
                gc_collect_cycles();
            }

            // 6. Compute BlurHash from the final XS variant
            $updateProgress('Generating BlurHash');
            $blurhash = $this->calculateBlurhash($image);

            unset($image);
            gc_collect_cycles();

            // 7. A watermarked gallery never lists this photo clean: its watermark is made before it's ready
            $this->watermarkIfNeeded($photo);

            // 8. Update database records in transaction
            $updateProgress('Updating Statistics');
            \Illuminate\Support\Facades\DB::transaction(function () use ($photo, $width, $height, $blurhash, $exifData, $verifiedMimeType) {
                $photo->update([
                    'width' => $width,
                    'height' => $height,
                    'blurhash' => $blurhash,
                    'mime_type' => $verifiedMimeType,
                    'taken_at' => $exifData['taken_at'] ?? $photo->taken_at,
                    'status' => PhotoStatus::Ready->value,
                ]);

                // A gallery created without a date takes it from the first photo that has one
                if ($photo->taken_at) {
                    \App\Models\Gallery::whereKey($photo->gallery_id)->whereNull('event_date')
                        ->update(['event_date' => $photo->taken_at->toDateString()]);
                }

                PhotoMetadata::updateOrCreate([
                    'photo_id' => $photo->id,
                ], [
                    'camera' => $exifData['camera'],
                    'lens' => $exifData['lens'],
                    'iso' => $exifData['iso'],
                    'shutter_speed' => $exifData['shutter_speed'],
                    'aperture' => $exifData['aperture'],
                    'focal_length' => $exifData['focal_length'],
                    'flash' => $exifData['flash'],
                    'orientation' => $exifData['orientation'],
                ]);
            });

        } catch (Throwable $e) {
            Log::error("Failed to process photo [{$photoUuid}]: " . $e->getMessage());

            // Delete any partially uploaded variants to clean up storage
            foreach ($uploadedVariants as $path) {
                try {
                    Storage::disk('b2')->delete($path);
                } catch (Throwable $cleanupError) {
                    Log::warning("Failed to clean up variant {$path} on storage: " . $cleanupError->getMessage());
                }
            }

            // Rethrow so the queue handles retries/backoffs
            throw $e;
        } finally {
            // Always clean up local temporary files
            try {
                File::deleteDirectory($tempDir);
            } catch (Throwable $cleanupError) {
                Log::warning("Failed to delete temp folder {$tempDir}: " . $cleanupError->getMessage());
            }
        }
    }

    private function watermarkIfNeeded(Photo $photo): void
    {
        $gallery = $photo->gallery;
        if (!$gallery?->watermark || !$gallery->user || $photo->isVideo() || !\App\Support\Gallery\Watermark::allowedFor($gallery->user)) {
            return;
        }

        try {
            app(\App\Services\PhotoWatermarkService::class)->apply($photo, \App\Support\Gallery\Watermark::for($gallery->user));
        } catch (Throwable $e) {
            // The photo is still good; its watermark is tried again on its own
            Log::warning("Watermark failed for photo [{$photo->uuid}], retrying separately: " . $e->getMessage());
            \App\Jobs\WatermarkPhotoJob::dispatch($photo)->delay(now()->addMinute());
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(Throwable $exception): void
    {
        Log::error("ProcessPhotoJob failed after all retries for Photo [{$this->photo->uuid}]: " . $exception->getMessage());
        
        $this->photo->update([
            'status' => PhotoStatus::Failed->value,
        ]);
    }

    /**
     * Compute BlurHash of the image.
     */
    private function calculateBlurhash(\Intervention\Image\Image $image): ?string
    {
        try {
            $resized = clone $image;
            $resized->resize(32, 32);

            $gdImage = $resized->core()->native();
            if (!$gdImage) {
                return null;
            }

            $pixels = [];
            for ($y = 0; $y < 32; $y++) {
                $row = [];
                for ($x = 0; $x < 32; $x++) {
                    $colorIdx = imagecolorat($gdImage, $x, $y);
                    $color = imagecolorsforindex($gdImage, $colorIdx);
                    $row[] = [
                        $color['red'],
                        $color['green'],
                        $color['blue']
                    ];
                }
                $pixels[] = $row;
            }

            return Blurhash::encode($pixels, 4, 3);
        } catch (\Exception $e) {
            Log::error("Failed to generate BlurHash: " . $e->getMessage());
            return null;
        }
    }
}
