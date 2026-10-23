<?php

namespace App\Actions\Studio;

use App\Models\User;
use App\Services\StorageService;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\ImageInterface;

/**
 * Portfolio branding images (logo, cover, social share image): direct browser upload to B2,
 * then resized server-side. Works like UploadAvatarAction.
 */
class UploadPortfolioImageAction
{
    /** kind => users column */
    public const KINDS = [
        'logo' => 'logo_path',
        'cover' => 'cover_path',
        'share' => 'share_image_path',
    ];

    public function __construct(
        protected StorageService $storageService
    ) {}

    /**
     * Request a presigned upload URL for one of the portfolio images.
     */
    public function requestUpload(User $user, string $kind, string $filename, string $sha256): array
    {
        $this->assertKind($kind);

        $contentType = \App\Support\MediaTypes::forAvatar($filename);
        if ($contentType === null) {
            throw ValidationException::withMessages([
                'filename' => 'Images must be JPEG, PNG or WebP.',
            ]);
        }

        $sanitizedFilename = preg_replace('/[^a-zA-Z0-9_\.-]/', '_', $filename);
        $objectKey = "portfolio/{$user->uuid}/{$kind}/" . time() . "_{$sanitizedFilename}";
        $expiresAt = now()->addMinutes(15);
        $base64Sha256 = base64_encode(hex2bin($sha256));

        $presignedUrl = $this->storageService->generatePresignedUploadUrl(
            $objectKey,
            $base64Sha256,
            $expiresAt,
            $contentType
        );

        return [
            'object_key' => $objectKey,
            'presigned_url' => $presignedUrl,
            'headers' => [
                'x-amz-checksum-sha256' => $base64Sha256,
                'Content-Type' => $contentType,
            ],
            'expires_at' => $expiresAt->toIso8601String(),
        ];
    }

    /**
     * Resize the uploaded original, store it on the user and remove the previous image.
     *
     * @throws Exception
     */
    public function confirmUpload(User $user, string $kind, string $objectKey): string
    {
        $this->assertKind($kind);

        // Only keys issued to this user for this kind are accepted
        if (!str_starts_with($objectKey, "portfolio/{$user->uuid}/{$kind}/") || str_contains($objectKey, '..')) {
            throw new Exception('Unknown upload.');
        }

        if (!$this->storageService->exists($objectKey)) {
            throw new Exception('The uploaded image was not found in storage.');
        }

        $rawContent = Storage::disk('b2')->get($objectKey);
        if (!$rawContent) {
            throw new Exception('Could not read the uploaded image.');
        }

        $manager = new ImageManager(new Driver());
        $image = $manager->read($rawContent);
        $image->orient(); // Auto-rotate from EXIF

        [$encoded, $extension] = $this->process($kind, $image);

        $processedKey = preg_replace('/\.[^.]+$/', '', $objectKey) . "_{$kind}.{$extension}";
        Storage::disk('b2')->put($processedKey, $encoded);

        if (!$this->storageService->exists($processedKey)) {
            throw new Exception('Failed to store the processed image.');
        }

        $this->deleteQuietly($user, $objectKey);

        $column = self::KINDS[$kind];
        $oldPath = $user->{$column};
        $user->update([$column => $processedKey]);

        if ($oldPath && $oldPath !== $processedKey) {
            $this->deleteQuietly($user, $oldPath);
        }

        return $user->{$this->urlAttribute($kind)};
    }

    /**
     * Remove a portfolio image (the portfolio falls back to its default).
     */
    public function remove(User $user, string $kind): void
    {
        $this->assertKind($kind);

        $column = self::KINDS[$kind];
        $oldPath = $user->{$column};
        $user->update([$column => null]);

        if ($oldPath) {
            $this->deleteQuietly($user, $oldPath);
        }
    }

    /**
     * @return array{0: string, 1: string} encoded bytes and file extension
     */
    protected function process(string $kind, ImageInterface $image): array
    {
        return match ($kind) {
            // Keeps transparency; wide wordmarks fit inside 800 × 400
            'logo' => [(string) $image->scaleDown(width: 800, height: 400)->toWebp(90), 'webp'],
            'cover' => [(string) $image->scaleDown(width: 2400, height: 2400)->toWebp(82), 'webp'],
            // Link previews: the 1.91:1 size Facebook, WhatsApp and X expect, as JPEG for older crawlers
            'share' => [(string) $image->cover(1200, 630)->toJpeg(85), 'jpg'],
        };
    }

    protected function urlAttribute(string $kind): string
    {
        return match ($kind) {
            'logo' => 'logo_url',
            'cover' => 'cover_url',
            'share' => 'share_image_url',
        };
    }

    protected function assertKind(string $kind): void
    {
        if (!isset(self::KINDS[$kind])) {
            throw ValidationException::withMessages(['kind' => 'Unknown image type.']);
        }
    }

    protected function deleteQuietly(User $user, string $path): void
    {
        try {
            $this->storageService->delete($path);
        } catch (\Throwable $e) {
            Log::warning('Failed to delete portfolio image from storage', [
                'user_uuid' => $user->uuid,
                'path' => $path,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
