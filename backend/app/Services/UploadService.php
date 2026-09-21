<?php

namespace App\Services;

use App\Enums\PhotoStatus;
use App\Enums\UploadStatus;
use App\Exceptions\StorageQuotaExceededException;
use App\Models\Gallery;
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
        string $idempotencyKey
    ): array {
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
                // Release old reservation if it was still in requested status
                DB::table('users')
                    ->where('id', $user->id)
                    ->update([
                        'storage_reserved_bytes' => DB::raw("CASE WHEN storage_reserved_bytes >= {$staleSession->expected_size} THEN storage_reserved_bytes - {$staleSession->expected_size} ELSE 0 END"),
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

        // Generate unique UUID and object path
        $photoUuid = Uuid::uuid7()->toString();
        $basename = pathinfo($filename, PATHINFO_FILENAME);
        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        $sanitizedBasename = preg_replace('/[^a-zA-Z0-9_\.-]/', '_', $basename);
        $sanitizedFilename = $sanitizedBasename . '.' . $extension;

        $objectKey = "galleries/{$gallery->uuid}/photos/{$photoUuid}/{$sanitizedFilename}";
        $expiresAt = now()->addHours(2);

        $session = UploadSession::create([
            'uuid' => $photoUuid,
            'user_id' => $user->id,
            'gallery_id' => $gallery->id,
            'idempotency_key' => $idempotencyKey,
            'object_key' => $objectKey,
            'original_filename' => $filename,
            'expected_size' => $fileSize,
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

        // 2. Database Transaction: Create photo record & update upload session status
        return DB::transaction(function () use ($session, $user) {
            $defaultDisk = StorageDisk::firstOrCreate([
                'driver' => 'b2',
            ], [
                'uuid' => Uuid::uuid7()->toString(),
                'bucket' => config('filesystems.disks.b2.bucket', 'ifotoset-media'),
                'region' => config('filesystems.disks.b2.region', 'us-east-005'),
                'cdn_domain' => config('filesystems.disks.b2.cdn_domain', 'cdn.ifotoset.com'),
            ]);

            $extension = pathinfo($session->object_key, PATHINFO_EXTENSION);
            $mimeType = match(strtolower($extension)) {
                'png' => 'image/png',
                'gif' => 'image/gif',
                'webp' => 'image/webp',
                'heic' => 'image/heic',
                'heif' => 'image/heif',
                'tiff' => 'image/tiff',
                default => 'image/jpeg',
            };

            $photo = Photo::create([
                'uuid' => $session->uuid,
                'gallery_id' => $session->gallery_id,
                'disk_id' => $defaultDisk->id,
                'path' => dirname($session->object_key),
                'filename' => basename($session->object_key),
                'original_filename' => $session->original_filename ?? basename($session->object_key),
                'stored_filename' => basename($session->object_key),
                'mime_type' => $mimeType,
                'size' => $session->expected_size,
                'checksum' => $session->expected_sha256,
                'status' => PhotoStatus::Processing->value,
            ]);

            $session->update([
                'status' => UploadStatus::Completed->value,
            ]);

            // Release reservation (storage_used_bytes is updated via PhotoObserver)
            DB::table('users')
                ->where('id', $user->id)
                ->update([
                    'storage_reserved_bytes' => DB::raw("CASE WHEN storage_reserved_bytes >= {$session->expected_size} THEN storage_reserved_bytes - {$session->expected_size} ELSE 0 END"),
                ]);

            // Seed initial media job tracking record in queued status
            \App\Models\MediaJob::create([
                'photo_id' => $photo->id,
                'job_name' => \App\Jobs\ProcessPhotoJob::class,
                'job_type' => \App\Jobs\ProcessPhotoJob::class,
                'status' => \App\Enums\MediaJobStatus::Queued->value,
                'progress' => 'Queued',
            ]);

            // Dispatch background WebP resize and metadata extraction job
            DB::afterCommit(function () use ($photo) {
                \App\Jobs\ProcessPhotoJob::dispatch($photo);
            });

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
                    ]);
            }

            // Clean up any partial objects in storage asynchronously
            $this->storageService->delete($session->object_key);
        }
    }
}
?>
