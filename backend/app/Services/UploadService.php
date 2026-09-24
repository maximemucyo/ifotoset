<?php

namespace App\Services;

use App\Enums\MediaJobStatus;
use App\Enums\PhotoStatus;
use App\Enums\UploadStatus;
use App\Exceptions\StorageQuotaExceededException;
use App\Models\Gallery;
use App\Models\MediaJob;
use App\Models\Photo;
use App\Models\StorageDisk;
use App\Models\UploadSession;
use App\Models\User;
use Exception;
use Illuminate\Support\Facades\DB;
use Ramsey\Uuid\Uuid;

class UploadService
{
    public function __construct(
        protected StorageService $storageService
    ) {}

    /**
     * Creates or retrieves an existing presigned upload session for direct browser upload.
     */
    public function createUploadSession(
        User $user,
        Gallery $gallery,
        string $filename,
        int $fileSize,
        string $mimeType,
        string $sha256,
        string $idempotencyKey,
        ?int $declaredDurationSeconds = null
    ): array {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $isVideo = str_starts_with(strtolower($mimeType), 'video/') || in_array($extension, ['mp4', 'mov', 'webm']);

        // Video plan & quota enforcement
        $videoReservation = 0;
        if ($isVideo) {
            if (!$user->hasVideoSupport()) {
                throw new \App\Exceptions\VideoNotSupportedOnPlanException();
            }

            $maxAllowedBytes = (int) ($user->plan?->max_video_size_bytes ?: 524288000); // 500MB default
            if ($maxAllowedBytes > 0 && $fileSize > $maxAllowedBytes) {
                throw new \App\Exceptions\VideoFileSizeExceededException($fileSize, $maxAllowedBytes);
            }

            $maxSingleDuration = (int) ($user->plan?->max_single_video_duration_seconds ?: 900); // 15 mins default
            if ($declaredDurationSeconds !== null && $declaredDurationSeconds > 0 && $declaredDurationSeconds <= $maxSingleDuration) {
                $videoReservation = (int) $declaredDurationSeconds;
            } else {
                $videoReservation = $maxSingleDuration;
            }

            $availableSeconds = $user->getAvailableVideoSeconds();
            if ($availableSeconds < $videoReservation) {
                throw new \App\Exceptions\VideoQuotaExceededException(
                    requiredSeconds: $videoReservation,
                    availableSeconds: $availableSeconds,
                    limitSeconds: (int) ($user->plan?->video_limit_seconds ?? $user->plan?->video_limit ?? 0),
                    usedSeconds: (int) $user->video_seconds_used,
                    reservedSeconds: (int) $user->video_seconds_reserved
                );
            }
        }

        // Idempotency check: Return existing active session without allocating a second reservation
        $existingSession = UploadSession::where('user_id', $user->id)
            ->where('idempotency_key', $idempotencyKey)
            ->where('status', UploadStatus::Requested->value)
            ->where('expires_at', '>', now())
            ->first();

        if ($existingSession) {
            $base64Sha256 = base64_encode(hex2bin($existingSession->expected_sha256));
            $presignedUrl = $this->storageService->generatePresignedUploadUrl(
                $existingSession->object_key,
                $base64Sha256,
                $existingSession->expires_at
            );

            return [
                'upload_session_id' => $existingSession->uuid,
                'session_id' => $existingSession->uuid,
                'object_key' => $existingSession->object_key,
                'presigned_url' => $presignedUrl,
                'upload_url' => $presignedUrl,
                'headers' => [
                    'x-amz-checksum-sha256' => $base64Sha256,
                ],
                'expires_at' => $existingSession->expires_at->toIso8601String(),
            ];
        }

        // Clean up any stale/expired session with the same idempotency key
        $staleSession = UploadSession::where('user_id', $user->id)
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        if ($staleSession) {
            if ($staleSession->status === UploadStatus::Requested->value) {
                // Release old storage and video reservations
                DB::table('users')
                    ->where('id', $user->id)
                    ->update([
                        'storage_reserved_bytes' => DB::raw("CASE WHEN storage_reserved_bytes >= {$staleSession->expected_size} THEN storage_reserved_bytes - {$staleSession->expected_size} ELSE 0 END"),
                        'video_seconds_reserved' => DB::raw("CASE WHEN video_seconds_reserved >= {$staleSession->reserved_duration_seconds} THEN video_seconds_reserved - {$staleSession->reserved_duration_seconds} ELSE 0 END"),
                    ]);
            }
            $staleSession->delete();
        }

        // Atomic storage quota reservation
        $limit = $user->plan?->storage_limit;
        if ($limit !== null && $limit > 0) {
            $affected = DB::table('users')
                ->where('id', $user->id)
                ->whereRaw('(storage_used_bytes + storage_reserved_bytes + ?) <= ?', [$fileSize, $limit])
                ->increment('storage_reserved_bytes', $fileSize);

            if ($affected === 0) {
                $fresh = DB::table('users')->where('id', $user->id)->first(['storage_used_bytes', 'storage_reserved_bytes']);
                $used = (int) ($fresh->storage_used_bytes ?? 0);
                $reserved = (int) ($fresh->storage_reserved_bytes ?? 0);
                $available = max(0, $limit - ($used + $reserved));

                throw new StorageQuotaExceededException(
                    requiredBytes: $fileSize,
                    availableBytes: $available,
                    limitBytes: $limit,
                    usedBytes: $used,
                    reservedBytes: $reserved
                );
            }
        } else {
            // Unlimited plan: record reservation tracking without quota ceiling
            DB::table('users')
                ->where('id', $user->id)
                ->increment('storage_reserved_bytes', $fileSize);
        }

        // Atomic video seconds reservation
        if ($isVideo && $videoReservation > 0) {
            DB::table('users')
                ->where('id', $user->id)
                ->increment('video_seconds_reserved', $videoReservation);
        }

        // Generate unique UUID and object path
        $photoUuid = Uuid::uuid7()->toString();
        $basename = pathinfo($filename, PATHINFO_FILENAME);
        $sanitizedBasename = preg_replace('/[^a-zA-Z0-9_\.-]/', '_', $basename);
        $sanitizedFilename = $sanitizedBasename . '.' . $extension;

        $isProtected = ($gallery->visibility !== 'public' || !empty($gallery->password_hash));
        $prefix = $isProtected ? "protected-galleries/{$gallery->uuid}/" : "galleries/{$gallery->uuid}/";

        if ($isVideo) {
            $objectKey = "{$prefix}videos/{$photoUuid}/original.{$extension}";
        } else {
            $objectKey = "{$prefix}photos/{$photoUuid}/{$sanitizedFilename}";
        }

        $expiresAt = now()->addHours(2);

        $session = UploadSession::create([
            'uuid' => $photoUuid,
            'user_id' => $user->id,
            'gallery_id' => $gallery->id,
            'idempotency_key' => $idempotencyKey,
            'object_key' => $objectKey,
            'original_filename' => $filename,
            'expected_size' => $fileSize,
            'reserved_duration_seconds' => $videoReservation,
            'expected_sha256' => $sha256,
            'status' => UploadStatus::Requested->value,
            'expires_at' => $expiresAt,
        ]);

        $base64Sha256 = base64_encode(hex2bin($sha256));

        $presignedUrl = $this->storageService->generatePresignedUploadUrl(
            $objectKey,
            $base64Sha256,
            $expiresAt
        );

        return [
            'upload_session_id' => $session->uuid,
            'session_id' => $session->uuid,
            'object_key' => $session->object_key,
            'presigned_url' => $presignedUrl,
            'upload_url' => $presignedUrl,
            'headers' => [
                'x-amz-checksum-sha256' => $base64Sha256,
            ],
            'expires_at' => $expiresAt->toIso8601String(),
        ];
    }

    /**
     * Verifies object presence via zero-bandwidth HeadObject check and confirms upload.
     */
    public function confirmUpload(User $user, string $uploadSessionUuid): Photo
    {
        $session = UploadSession::where('uuid', $uploadSessionUuid)
            ->where('user_id', $user->id)
            ->firstOrFail();

        if ($session->status === UploadStatus::Completed->value) {
            return Photo::where('checksum', $session->expected_sha256)->firstOrFail();
        }

        // 1. Lightweight 0-byte HeadObject call to verify file presence and size in storage
        $exists = $this->storageService->exists($session->object_key);
        if (!$exists) {
            throw new Exception("File object was not found in storage bucket.");
        }

        $actualSize = $this->storageService->size($session->object_key);
        if ($actualSize !== $session->expected_size) {
            throw new Exception("File size mismatch. Expected {$session->expected_size} bytes, got {$actualSize} bytes.");
        }

        // 2. Database Transaction: Create photo/video record & update upload session status
        return DB::transaction(function () use ($session, $user) {
            $defaultDisk = StorageDisk::firstOrCreate([
                'driver' => 'b2',
            ], [
                'uuid' => Uuid::uuid7()->toString(),
                'bucket' => config('filesystems.disks.b2.bucket', 'ifotoset-media'),
                'region' => config('filesystems.disks.b2.region', 'us-east-005'),
                'cdn_domain' => config('filesystems.disks.b2.cdn_domain', 'cdn.ifotoset.com'),
            ]);

            $extension = strtolower(pathinfo($session->object_key, PATHINFO_EXTENSION));
            $isVideo = in_array($extension, ['mp4', 'mov', 'webm']) || ($session->reserved_duration_seconds > 0);

            $mimeType = match($extension) {
                'mp4' => 'video/mp4',
                'mov' => 'video/quicktime',
                'webm' => 'video/webm',
                'png' => 'image/png',
                'gif' => 'image/gif',
                'webp' => 'image/webp',
                'heic' => 'image/heic',
                'heif' => 'image/heif',
                'tiff' => 'image/tiff',
                default => $isVideo ? 'video/mp4' : 'image/jpeg',
            };

            $photo = Photo::create([
                'uuid' => $session->uuid,
                'gallery_id' => $session->gallery_id,
                'disk_id' => $defaultDisk->id,
                'media_type' => $isVideo ? 'video' : 'photo',
                'path' => dirname($session->object_key),
                'filename' => basename($session->object_key),
                'original_filename' => $session->original_filename ?? basename($session->object_key),
                'stored_filename' => basename($session->object_key),
                'original_path' => $session->object_key,
                'delivery_path' => null,
                'mime_type' => $mimeType,
                'size' => $session->expected_size,
                'checksum' => $session->expected_sha256,
                'status' => PhotoStatus::Processing->value,
            ]);

            $session->update([
                'status' => UploadStatus::Completed->value,
            ]);

            // Release storage reservation (storage_used_bytes is updated via PhotoObserver)
            DB::table('users')
                ->where('id', $user->id)
                ->update([
                    'storage_reserved_bytes' => DB::raw("CASE WHEN storage_reserved_bytes >= {$session->expected_size} THEN storage_reserved_bytes - {$session->expected_size} ELSE 0 END"),
                ]);

            if ($isVideo) {
                // Seed initial media job tracking record in queued status
                MediaJob::create([
                    'photo_id' => $photo->id,
                    'job_name' => \App\Jobs\ProcessVideoJob::class,
                    'job_type' => \App\Jobs\ProcessVideoJob::class,
                    'status' => MediaJobStatus::Queued->value,
                    'progress' => 'Queued',
                ]);

                // Dispatch background video remux/transcode, poster extraction, and metadata job
                $reservedSeconds = (int) $session->reserved_duration_seconds;
                DB::afterCommit(function () use ($photo, $reservedSeconds) {
                    \App\Jobs\ProcessVideoJob::dispatch($photo, $reservedSeconds);
                });
            } else {
                // Seed initial media job tracking record in queued status
                MediaJob::create([
                    'photo_id' => $photo->id,
                    'job_name' => \App\Jobs\ProcessPhotoJob::class,
                    'job_type' => \App\Jobs\ProcessPhotoJob::class,
                    'status' => MediaJobStatus::Queued->value,
                    'progress' => 'Queued',
                ]);

                // Dispatch background WebP resize and metadata extraction job
                DB::afterCommit(function () use ($photo) {
                    \App\Jobs\ProcessPhotoJob::dispatch($photo);
                });
            }

            return $photo;
        });
    }

    /**
     * Aborts an upload session upon client cancellation or network error.
     */
    public function abortUpload(User $user, string $uploadSessionUuid, ?string $reason = null): void
    {
        $session = UploadSession::where('uuid', $uploadSessionUuid)
            ->where('user_id', $user->id)
            ->first();

        if ($session && $session->status !== UploadStatus::Completed->value) {
            $wasRequested = ($session->status === UploadStatus::Requested->value);

            $session->update([
                'status' => UploadStatus::Expired->value,
            ]);

            if ($wasRequested) {
                DB::table('users')
                    ->where('id', $user->id)
                    ->update([
                        'storage_reserved_bytes' => DB::raw("CASE WHEN storage_reserved_bytes >= {$session->expected_size} THEN storage_reserved_bytes - {$session->expected_size} ELSE 0 END"),
                        'video_seconds_reserved' => DB::raw("CASE WHEN video_seconds_reserved >= {$session->reserved_duration_seconds} THEN video_seconds_reserved - {$session->reserved_duration_seconds} ELSE 0 END"),
                    ]);
            }

            // Clean up any partial objects in storage asynchronously
            $this->storageService->delete($session->object_key);
        }
    }
}
?>
