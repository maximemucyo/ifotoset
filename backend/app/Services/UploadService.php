<?php

namespace App\Services;

use App\Enums\PhotoStatus;
use App\Enums\UploadStatus;
use App\Models\Gallery;
use App\Models\Photo;
use App\Models\StorageDisk;
use App\Models\UploadSession;
use App\Models\User;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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
        // Validate user storage quota limit
        if ($user->storage_used_bytes + $fileSize > $user->plan->storage_limit) {
            throw new Exception("Storage limit exceeded for current plan tier.");
        }

        // Idempotency check: Return existing session if already requested
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
                'object_key' => $existingSession->object_key,
                'presigned_url' => $presignedUrl,
                'headers' => [
                    'x-amz-checksum-sha256' => $base64Sha256,
                ],
                'expires_at' => $existingSession->expires_at->toIso8601String(),
            ];
        }

        // Delete any existing inactive/expired session with the same idempotency key to prevent unique constraint violation
        UploadSession::where('user_id', $user->id)
            ->where('idempotency_key', $idempotencyKey)
            ->delete();

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
            'object_key' => $session->object_key,
            'presigned_url' => $presignedUrl,
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
            $session->update([
                'status' => UploadStatus::Expired->value,
            ]);

            // Clean up any partial objects in storage asynchronously
            $this->storageService->delete($session->object_key);
        }
    }
}
?>
