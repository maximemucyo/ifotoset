<?php

namespace App\Actions;

use App\Models\User;
use App\Services\StorageService;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

class UploadAvatarAction
{
    public function __construct(
        protected StorageService $storageService
    ) {}

    /**
     * Request a presigned upload URL for the user's avatar.
     */
    public function requestUpload(User $user, string $filename, string $sha256): array
    {
        $sanitizedFilename = preg_replace('/[^a-zA-Z0-9_\.-]/', '_', $filename);
        $objectKey = "avatars/{$user->uuid}/" . time() . "_{$sanitizedFilename}";
        $expiresAt = now()->addMinutes(15);
        $base64Sha256 = base64_encode(hex2bin($sha256));

        $presignedUrl = $this->storageService->generatePresignedUploadUrl(
            $objectKey,
            $base64Sha256,
            $expiresAt
        );

        return [
            'object_key' => $objectKey,
            'presigned_url' => $presignedUrl,
            'headers' => [
                'x-amz-checksum-sha256' => $base64Sha256,
            ],
            'expires_at' => $expiresAt->toIso8601String(),
        ];
    }

    /**
     * Confirm successful avatar upload and delete old avatar.
     *
     * @throws Exception
     */
    public function confirmUpload(User $user, string $objectKey): string
    {
        // 1. Check if the uploaded file exists in storage
        if (!$this->storageService->exists($objectKey)) {
            throw new Exception("Avatar file was not found in storage bucket.");
        }

        // 2. Download raw upload and compress to max 400×400 WebP
        $rawContent = Storage::disk('b2')->get($objectKey);
        if (!$rawContent) {
            throw new Exception("Could not retrieve uploaded avatar for compression.");
        }

        $manager = new ImageManager(new Driver());
        $image = $manager->read($rawContent);
        $image->orient(); // Auto-rotate from EXIF
        $image->scaleDown(width: 400, height: 400); // Shrink only — never upscale
        $compressed = $image->toWebp(85);

        $compressedKey = preg_replace('/\.[^.]+$/', '', $objectKey) . '_logo.webp';
        Storage::disk('b2')->put($compressedKey, (string) $compressed);

        if (!$this->storageService->exists($compressedKey)) {
            throw new Exception("Failed to store compressed logo.");
        }

        // 3. Delete raw original — only keep compressed version
        try {
            $this->storageService->delete($objectKey);
        } catch (\Throwable $e) {
            Log::warning('Failed to delete raw avatar after compression', [
                'user_uuid' => $user->uuid,
                'raw_key'   => $objectKey,
                'error'     => $e->getMessage(),
            ]);
        }

        $oldAvatarPath = $user->avatar_path;

        // 4. Update user database record with compressed path
        $user->update([
            'avatar_path' => $compressedKey,
        ]);

        // 5. Best-effort delete old compressed avatar
        if ($oldAvatarPath && $oldAvatarPath !== $compressedKey) {
            try {
                $this->storageService->delete($oldAvatarPath);
            } catch (\Throwable $e) {
                Log::warning('Failed to delete old avatar from storage', [
                    'user_uuid'       => $user->uuid,
                    'old_avatar_path' => $oldAvatarPath,
                    'error'           => $e->getMessage(),
                ]);
            }
        }

        $cdnDomain = config('filesystems.disks.b2.cdn_domain', 'cdn.ifotoset.com');
        return "https://{$cdnDomain}/" . ltrim($compressedKey, '/');
    }
}
