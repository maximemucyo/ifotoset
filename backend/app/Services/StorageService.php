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
     * Computes deterministic CDN asset URL.
     */
    public function getCdnUrl(string $path, ?string $size = null, ?string $filename = null): string
    {
        $domain = rtrim($this->cdnDomain, '/');
        $cleanPath = ltrim($path, '/');

        if ($size) {
            return "https://{$domain}/{$cleanPath}/{$size}.webp";
        }

        $file = $filename ?? 'original.jpg';
        return "https://{$domain}/{$cleanPath}/{$file}";
    }
}
?>
