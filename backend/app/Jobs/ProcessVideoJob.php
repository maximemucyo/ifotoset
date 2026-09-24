<?php

namespace App\Jobs;

use App\Enums\MediaJobStatus;
use App\Enums\PhotoStatus;
use App\Exceptions\VideoQuotaExceededException;
use App\Models\MediaJob;
use App\Models\Photo;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;
use kornrunner\Blurhash\Blurhash;
use Throwable;

class ProcessVideoJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 600;

    public function uniqueId(): string
    {
        return $this->photo->uuid;
    }

    public function uniqueFor(): int
    {
        return 1200;
    }

    public function __construct(
        protected Photo $photo,
        protected int $reservedDurationSeconds = 0
    ) {
        $this->queue = config('media.queue', 'media');
    }

    public function getPhoto(): Photo
    {
        return $this->photo;
    }

    public function handle(): void
    {
        $photo = $this->photo->fresh();
        if (!$photo) {
            return;
        }

        if ($photo->status === PhotoStatus::Ready->value) {
            Log::info("ProcessVideoJob skipped: photo {$this->photo->id} is already processed and ready.");
            return;
        }

        // Ensure status is marked processing
        $photo->update(['status' => PhotoStatus::Processing->value]);

        $gallery = $photo->gallery;
        $user = $gallery?->user;
        if (!$gallery || !$user) {
            Log::error("ProcessVideoJob failed: gallery or user not found for photo {$photo->id}.");
            return;
        }

        $mediaJob = MediaJob::where('photo_id', $photo->id)->orderBy('id', 'desc')->first();
        $updateProgress = function (string $progress) use ($mediaJob) {
            if ($mediaJob) {
                $mediaJob->update(['progress' => $progress]);
            }
        };

        $tempDir = storage_path("app/tmp/videos/{$photo->uuid}");
        File::ensureDirectoryExists($tempDir);

        // Temp disk space safety guard: require at least 3x the video size
        $freeDiskSpace = disk_free_space($tempDir);
        $requiredWorkingSpace = max(100 * 1024 * 1024, $photo->size * 3);
        if ($freeDiskSpace !== false && $freeDiskSpace < $requiredWorkingSpace) {
            Log::warning("ProcessVideoJob: Insufficient temp disk space for photo {$photo->id}. Free: {$freeDiskSpace}, Required: {$requiredWorkingSpace}");
            $this->release(60);
            return;
        }

        $extension = pathinfo($photo->filename, PATHINFO_EXTENSION) ?: 'mp4';
        $tempInputPath = "{$tempDir}/original.{$extension}";
        $tempDeliveryPath = "{$tempDir}/delivery.mp4";
        $tempPosterPath = "{$tempDir}/poster.jpg";

        try {
            // 1. Download original from B2 storage
            $updateProgress('Downloading Original');
            $originalKey = $photo->original_path ?: ($photo->path . '/' . $photo->filename);
            $stream = Storage::disk('b2')->readStream($originalKey);
            if (!$stream) {
                throw new \Exception("Could not read original video stream from storage: {$originalKey}");
            }

            $localFileHandle = fopen($tempInputPath, 'wb');
            stream_copy_to_stream($stream, $localFileHandle);
            fclose($localFileHandle);
            if (is_resource($stream)) {
                fclose($stream);
            }

            clearstatcache(true, $tempInputPath);
            if (!File::exists($tempInputPath) || File::size($tempInputPath) === 0) {
                throw new \Exception("Downloaded original video file is empty.");
            }

            // 2. FFprobe inspection
            $updateProgress('Analyzing Video Metadata');
            $probeData = $this->probeVideo($tempInputPath);
            $actualDuration = (float) ($probeData['duration'] ?? 0);
            if ($actualDuration <= 0) {
                throw new \Exception("Invalid or unreadable video duration from ffprobe.");
            }

            // 3. Transactional Quota Commit & Dynamic Expansion
            $updateProgress('Committing Quota');
            $this->commitVideoQuota($user, $actualDuration, $this->reservedDurationSeconds, $photo, $originalKey);

            // 4. Poster Frame Extraction & BlurHash
            $updateProgress('Extracting Poster');
            $posterInfo = $this->extractPosterAndBlurhash($tempInputPath, $actualDuration, $tempDir, $photo);

            // 5. Smart Remux vs Transcode (delivery.mp4)
            $updateProgress('Preparing Web Delivery');
            $this->prepareDeliveryVideo($tempInputPath, $tempDeliveryPath, $probeData);

            // 6. Upload delivery.mp4 and poster variants to B2
            $updateProgress('Uploading Delivery Files');
            $deliveryKey = dirname($originalKey) . '/delivery.mp4';
            Storage::disk('b2')->putFileAs(
                dirname($originalKey),
                new \Illuminate\Http\File($tempDeliveryPath),
                'delivery.mp4'
            );

            // Upload poster variants
            $posterKey = dirname($originalKey) . '/poster.webp';
            if (!empty($posterInfo['poster_file']) && File::exists($posterInfo['poster_file'])) {
                Storage::disk('b2')->putFileAs(
                    dirname($originalKey),
                    new \Illuminate\Http\File($posterInfo['poster_file']),
                    'poster.webp'
                );

                foreach (['sm', 'md', 'lg'] as $sizeVariant) {
                    $variantFile = "{$tempDir}/poster_{$sizeVariant}.webp";
                    if (File::exists($variantFile)) {
                        Storage::disk('b2')->putFileAs(
                            dirname($originalKey),
                            new \Illuminate\Http\File($variantFile),
                            "poster_{$sizeVariant}.webp"
                        );
                    }
                }
            }

            // 7. Update database records in transaction
            $updateProgress('Finalizing');
            DB::transaction(function () use ($photo, $actualDuration, $probeData, $deliveryKey, $posterKey, $posterInfo) {
                $photo->update([
                    'media_type' => 'video',
                    'duration_seconds' => (int) round($actualDuration),
                    'width' => $probeData['display_width'] ?? $probeData['width'],
                    'height' => $probeData['display_height'] ?? $probeData['height'],
                    'original_path' => $photo->original_path ?: ($photo->path . '/' . $photo->filename),
                    'delivery_path' => $deliveryKey,
                    'poster_path' => $posterKey,
                    'blurhash' => $posterInfo['blurhash'] ?? null,
                    'video_metadata' => $probeData['bounded_metadata'] ?? null,
                    'status' => PhotoStatus::Ready->value,
                    'processing_error' => null,
                    'processed_at' => now(),
                ]);
            });

            // Recalculate gallery stats (updates video_count)
            app(\App\Services\GalleryStatisticsService::class)->recalculateGallery($photo->gallery_id);

            if ($mediaJob) {
                $mediaJob->update([
                    'status' => MediaJobStatus::Completed->value,
                    'progress' => 'Ready',
                    'completed_at' => now(),
                ]);
            }

        } catch (VideoQuotaExceededException $e) {
            Log::warning("ProcessVideoJob rejected: Video quota exceeded for photo {$photo->id}.");
            $photo->update([
                'status' => 'failed',
                'processing_error' => 'VIDEO_QUOTA_EXCEEDED',
            ]);
            if ($mediaJob) {
                $mediaJob->update([
                    'status' => MediaJobStatus::Failed->value,
                    'error' => 'VIDEO_QUOTA_EXCEEDED',
                ]);
            }
        } catch (Throwable $e) {
            Log::error("ProcessVideoJob error for photo {$photo->id}: " . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            $photo->update([
                'status' => 'failed',
                'processing_error' => mb_substr($e->getMessage(), 0, 250),
            ]);

            if ($mediaJob) {
                $mediaJob->update([
                    'status' => MediaJobStatus::Failed->value,
                    'error' => $e->getMessage(),
                ]);
            }

            // Release reserved seconds on fatal failure
            if ($this->reservedDurationSeconds > 0) {
                DB::table('users')
                    ->where('id', $user->id)
                    ->update([
                        'video_seconds_reserved' => DB::raw("CASE WHEN video_seconds_reserved >= {$this->reservedDurationSeconds} THEN video_seconds_reserved - {$this->reservedDurationSeconds} ELSE 0 END"),
                    ]);
            }

            throw $e;
        } finally {
            // Guarantee local temp directory cleanup
            try {
                if (File::isDirectory($tempDir)) {
                    File::deleteDirectory($tempDir);
                }
            } catch (Throwable $t) {
                Log::warning("Failed to clean up temp video dir: {$tempDir}");
            }
        }
    }

    /**
     * Executes ffprobe to extract video stream and container metadata.
     */
    protected function probeVideo(string $filePath): array
    {
        $escaped = escapeshellarg($filePath);
        $command = "ffprobe -v quiet -print_format json -show_format -show_streams {$escaped}";
        $output = shell_exec($command);

        if (!$output) {
            throw new \Exception("ffprobe returned empty output.");
        }

        $data = json_decode($output, true);
        if (!$data || !isset($data['format'])) {
            throw new \Exception("ffprobe failed to parse JSON stream metadata.");
        }

        $format = $data['format'];
        $streams = $data['streams'] ?? [];

        $videoStream = null;
        $audioStream = null;
        foreach ($streams as $stream) {
            if ($stream['codec_type'] === 'video' && !$videoStream) {
                $videoStream = $stream;
            } elseif ($stream['codec_type'] === 'audio' && !$audioStream) {
                $audioStream = $stream;
            }
        }

        if (!$videoStream) {
            throw new \Exception("No video stream found in uploaded media file.");
        }

        $duration = (float) ($videoStream['duration'] ?? $format['duration'] ?? 0);
        $width = (int) ($videoStream['width'] ?? 0);
        $height = (int) ($videoStream['height'] ?? 0);
        $videoCodec = strtolower($videoStream['codec_name'] ?? 'unknown');
        $audioCodec = strtolower($audioStream['codec_name'] ?? 'none');
        $pixelFormat = strtolower($videoStream['pix_fmt'] ?? '');
        $bitrate = (int) ($format['bit_rate'] ?? $videoStream['bit_rate'] ?? 0);

        // Frame rate calculation
        $fps = 0.0;
        if (!empty($videoStream['avg_frame_rate']) && str_contains($videoStream['avg_frame_rate'], '/')) {
            [$num, $den] = explode('/', $videoStream['avg_frame_rate']);
            if ((float) $den > 0) {
                $fps = round((float) $num / (float) $den, 2);
            }
        }

        // Check rotation in side data or tags
        $rotation = 0;
        if (isset($videoStream['tags']['rotate'])) {
            $rotation = (int) $videoStream['tags']['rotate'];
        } elseif (!empty($videoStream['side_data_list'])) {
            foreach ($videoStream['side_data_list'] as $side) {
                if (isset($side['rotation'])) {
                    $rotation = (int) $side['rotation'];
                }
            }
        }

        // Swap display dimensions if rotated 90 or 270 degrees
        $displayWidth = $width;
        $displayHeight = $height;
        if (abs($rotation) === 90 || abs($rotation) === 270) {
            $displayWidth = $height;
            $displayHeight = $width;
        }

        return [
            'duration' => $duration,
            'width' => $width,
            'height' => $height,
            'display_width' => $displayWidth,
            'display_height' => $displayHeight,
            'rotation' => $rotation,
            'video_codec' => $videoCodec,
            'audio_codec' => $audioCodec,
            'pixel_format' => $pixelFormat,
            'format_name' => strtolower($format['format_name'] ?? ''),
            'fps' => $fps,
            'bitrate' => $bitrate,
            'bounded_metadata' => [
                'container' => 'mp4',
                'video_codec' => $videoCodec,
                'audio_codec' => $audioCodec,
                'width' => $displayWidth,
                'height' => $displayHeight,
                'fps' => $fps,
                'bitrate' => $bitrate,
                'audio_channels' => (int) ($audioStream['channels'] ?? 0),
                'pixel_format' => $pixelFormat,
                'rotation' => $rotation,
            ],
        ];
    }

    /**
     * Atomically validates and commits video quota, expanding reservation if needed.
     */
    protected function commitVideoQuota(User $user, float $actualDuration, int $reservedDuration, Photo $photo, string $originalKey): void
    {
        $actualSeconds = (int) round($actualDuration);

        DB::transaction(function () use ($user, $actualSeconds, $reservedDuration, $originalKey) {
            // Lock user row
            $lockedUser = User::lockForUpdate()->find($user->id);
            $plan = $lockedUser->plan;
            $limit = (int) ($plan?->video_limit_seconds ?? $plan?->video_limit ?? 0);

            $currentUsed = (int) $lockedUser->video_seconds_used;
            $currentReserved = (int) $lockedUser->video_seconds_reserved;

            // Base reserved without this job's reservation
            $otherReserved = max(0, $currentReserved - $reservedDuration);

            if ($actualSeconds <= $reservedDuration) {
                // Actual fits within existing reservation: commit actual and release excess reservation
                $lockedUser->video_seconds_used = $currentUsed + $actualSeconds;
                $lockedUser->video_seconds_reserved = $otherReserved;
                $lockedUser->save();
            } else {
                // Actual exceeds initial reservation: attempt dynamic expansion
                $excess = $actualSeconds - $reservedDuration;
                $availableRemaining = max(0, $limit - ($currentUsed + $otherReserved + $reservedDuration));

                if ($excess <= $availableRemaining) {
                    // Fits remaining quota: expand and commit
                    $lockedUser->video_seconds_used = $currentUsed + $actualSeconds;
                    $lockedUser->video_seconds_reserved = $otherReserved;
                    $lockedUser->save();
                } else {
                    // Exceeds quota: release held reservation and reject
                    $lockedUser->video_seconds_reserved = $otherReserved;
                    $lockedUser->save();

                    // Delete original file from B2
                    try {
                        Storage::disk('b2')->delete($originalKey);
                    } catch (\Throwable $t) {
                        Log::warning("Failed to delete rejected video from storage: {$originalKey}");
                    }

                    throw new VideoQuotaExceededException(
                        requiredSeconds: $actualSeconds,
                        availableSeconds: $availableRemaining + $reservedDuration,
                        limitSeconds: $limit,
                        usedSeconds: $currentUsed,
                        reservedSeconds: $otherReserved
                    );
                }
            }
        });
    }

    /**
     * Extracts poster image and computes responsive variants and BlurHash.
     */
    protected function extractPosterAndBlurhash(string $videoPath, float $duration, string $tempDir, Photo $photo): array
    {
        $posterJpg = "{$tempDir}/poster.jpg";
        $posterWebp = "{$tempDir}/poster.webp";

        // Heuristic timestamp: between 1.0s and 5.0s, at roughly 10%
        $targetTime = max(0.5, min(5.0, $duration * 0.10));
        $escapedVideo = escapeshellarg($videoPath);
        $escapedPoster = escapeshellarg($posterJpg);

        $threads = (int) config('media.ffmpeg_threads', env('MEDIA_FFMPEG_THREADS', 2));
        $cmd = "ffmpeg -y -ss {$targetTime} -i {$escapedVideo} -frames:v 1 -q:v 2 -threads {$threads} {$escapedPoster} 2>&1";
        shell_exec($cmd);

        if (!File::exists($posterJpg) || File::size($posterJpg) === 0) {
            // Fallback: try frame at 0.1s
            $cmdFallback = "ffmpeg -y -ss 0.1 -i {$escapedVideo} -frames:v 1 -q:v 2 -threads {$threads} {$escapedPoster} 2>&1";
            shell_exec($cmdFallback);
        }

        if (!File::exists($posterJpg) || File::size($posterJpg) === 0) {
            Log::warning("ProcessVideoJob: Could not extract poster frame for photo {$photo->id}. Using placeholder.");
            return [
                'poster_file' => null,
                'blurhash' => null,
            ];
        }

        // Convert poster to WebP variants
        $manager = new ImageManager(new Driver());
        $image = $manager->read($posterJpg);
        $image->toWebp(85)->save($posterWebp);

        // Generate responsive poster variants
        $variants = [
            'sm' => 480,
            'md' => 960,
            'lg' => 1600,
        ];

        foreach ($variants as $size => $w) {
            $variantFile = "{$tempDir}/poster_{$size}.webp";
            $clone = clone $image;
            if ($clone->width() > $w) {
                $clone->scale(width: $w);
            }
            $clone->toWebp(82)->save($variantFile);
        }

        // Calculate BlurHash from the SM variant
        $blurhash = null;
        try {
            $smImage = $manager->read("{$tempDir}/poster_sm.webp");
            $smImage->scale(width: 32);
            $width = $smImage->width();
            $height = $smImage->height();

            $pixels = [];
            for ($y = 0; $y < $height; $y++) {
                $row = [];
                for ($x = 0; $x < $width; $x++) {
                    $color = $smImage->pickColor($x, $y);
                    $row[] = [$color->red()->value(), $color->green()->value(), $color->blue()->value()];
                }
                $pixels[] = $row;
            }

            $blurhash = Blurhash::encode($pixels, 4, 3);
        } catch (\Throwable $t) {
            Log::warning("ProcessVideoJob: Blurhash generation failed for photo {$photo->id}: " . $t->getMessage());
        }

        return [
            'poster_file' => $posterWebp,
            'blurhash' => $blurhash,
        ];
    }

    /**
     * Prepares web-optimized delivery.mp4 using faststart remux (copy) or bounded transcode.
     */
    protected function prepareDeliveryVideo(string $inputPath, string $outputPath, array $probeData): void
    {
        $escapedInput = escapeshellarg($inputPath);
        $escapedOutput = escapeshellarg($outputPath);
        $threads = (int) config('media.ffmpeg_threads', env('MEDIA_FFMPEG_THREADS', 2));

        $videoCodec = $probeData['video_codec'] ?? '';
        $audioCodec = $probeData['audio_codec'] ?? '';
        $pixelFormat = $probeData['pixel_format'] ?? '';
        $rotation = (int) ($probeData['rotation'] ?? 0);

        // Case A & B: Web-ready H.264 + AAC + yuv420p without rotation -> remux copy with faststart!
        $canStreamCopy = ($videoCodec === 'h264')
            && in_array($audioCodec, ['aac', 'none', ''])
            && in_array($pixelFormat, ['yuv420p', 'yuvj420p'])
            && ($rotation === 0);

        if ($canStreamCopy) {
            $cmd = "ffmpeg -y -i {$escapedInput} -c copy -movflags +faststart -threads {$threads} {$escapedOutput} 2>&1";
            $res = shell_exec($cmd);

            if (File::exists($outputPath) && File::size($outputPath) > 0) {
                return;
            }
            Log::warning("Remux stream copy failed. Falling back to transcode. Output: {$res}");
        }

        // Case C: Incompatible codecs or rotation needed -> transcode with rotation normalization
        $filter = "";
        if ($rotation === 90) {
            $filter = "-vf \"transpose=1\"";
        } elseif ($rotation === 180) {
            $filter = "-vf \"transpose=2,transpose=2\"";
        } elseif ($rotation === 270) {
            $filter = "-vf \"transpose=2\"";
        }

        $cmd = "ffmpeg -y -i {$escapedInput} {$filter} -c:v libx264 -preset fast -crf 23 -pix_fmt yuv420p -c:a aac -b:a 160k -movflags +faststart -threads {$threads} {$escapedOutput} 2>&1";
        shell_exec($cmd);

        if (!File::exists($outputPath) || File::size($outputPath) === 0) {
            throw new \Exception("Failed to generate delivery.mp4 via FFmpeg transcode.");
        }
    }
}
