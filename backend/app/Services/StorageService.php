<?php

namespace App\Services;

use DateTimeInterface;
use Illuminate\Support\Facades\Storage;

class StorageService
{
    protected string $cdnDomain;

    public function __construct()
    {
        $this->cdnDomain = config('filesystems.disks.b2.cdn_domain', 'cdn.ifotoset.com');
    }

    /**
     * Generates an S3 presigned upload URL with SHA-256 checksum header enforcement.
     */
    public function generatePresignedUploadUrl(string $objectKey, string $base64Sha256, DateTimeInterface $expiresAt): string
    {
        // AWS S3 / Backblaze B2 Flysystem driver presigned URL generation
        $client = Storage::disk('b2')->getClient();
        $bucket = config('filesystems.disks.b2.bucket', 'ifotoset-media');

        $cmd = $client->getCommand('PutObject', [
            'Bucket' => $bucket,
            'Key' => $objectKey,
            'ChecksumSHA256' => $base64Sha256,
        ]);

        $request = $client->createPresignedRequest($cmd, $expiresAt);

        return (string) $request->getUri();
    }

    /**
     * Executes lightweight HeadObject check to verify file presence in storage.
     */
    public function exists(string $objectKey): bool
    {
        return Storage::disk('b2')->exists($objectKey);
    }

    /**
     * Retrieves file size from S3 object metadata.
     */
    public function size(string $objectKey): int
    {
        return Storage::disk('b2')->size($objectKey);
    }

    /**
     * Deletes file from storage.
     */
    public function delete(string $objectKey): bool
    {
        return Storage::disk('b2')->delete($objectKey);
    }

    /**
     * Deletes folder/prefix recursively from storage.
     * Returns true if the directory is successfully deleted or already absent.
     * Throws an exception on structural connection or API failure.
     */
    public function deleteDirectory(string $directoryKey): bool
    {
        try {
            return Storage::disk('b2')->deleteDirectory($directoryKey);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Backblaze B2 deleteDirectory failed.', [
                'directory_key' => $directoryKey,
                'exception' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Computes deterministic CDN asset URL.
     */
    public function getCdnUrl(string $path, ?string $size = null, ?string $filename = null): string
    {
        $domain = rtrim($this->cdnDomain, '/');
        $cleanPath = ltrim($path, '/');

        if ($size) {
            $baseName = $filename ? pathinfo($filename, PATHINFO_FILENAME) : 'original';
            return "https://{$domain}/{$cleanPath}/{$baseName}_{$size}.webp";
        }

        $file = $filename ?? 'original.jpg';
        return "https://{$domain}/{$cleanPath}/{$file}";
    }
}
?>
