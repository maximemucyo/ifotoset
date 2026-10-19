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
    public function generatePresignedUploadUrl(string $objectKey, string $base64Sha256, DateTimeInterface $expiresAt, ?string $contentType = null): string
    {
        // AWS S3 Flysystem driver presigned URL generation
        $client = $this->presignClient();
        $bucket = config('filesystems.disks.b2.bucket', 'ifotoset-media');

        $params = [
            'Bucket' => $bucket,
            'Key' => $objectKey,
            'ChecksumSHA256' => $base64Sha256,
        ];
        // Signed, so the browser must PUT with exactly this Content-Type
        if ($contentType) {
            $params['ContentType'] = $contentType;
        }

        $cmd = $client->getCommand('PutObject', $params);

        $request = $client->createPresignedRequest($cmd, $expiresAt);

        return (string) $request->getUri();
    }

    /**
     * Generates an S3 presigned GET URL for downloading a file with a custom filename.
     */
    public function generatePresignedDownloadUrl(string $objectKey, string $filename, \DateTimeInterface $expiresAt): string
    {
        // Safe ASCII fallback: replace non-ASCII characters with underscores and strip double quotes/backslashes
        $asciiFilename = preg_replace('/[^\x20-\x7E]/', '_', $filename);
        $asciiFilename = str_replace(['"', '\\'], '', $asciiFilename);
        if (trim($asciiFilename) === '') {
            $asciiFilename = 'photo.jpg';
        }

        $utf8Filename = rawurlencode($filename);
        $contentDisposition = "attachment; filename=\"{$asciiFilename}\"; filename*=UTF-8''{$utf8Filename}";

        // If CDN domain is available, return a signed, branded CDN download URL with attachment filename.
        // The edge worker only serves downloads that carry a valid token.
        if (!empty($this->cdnDomain) && config('filesystems.disks.b2.use_cdn_downloads', true)) {
            $signedUrl = app(MediaTokenService::class)->signUrlUntil($objectKey, $expiresAt, $this->cdnDomain);
            return "{$signedUrl}&filename={$utf8Filename}";
        }

        try {
            $disk = Storage::disk('b2');
            if (method_exists($disk, 'getClient')) {
                $client = $this->presignClient();
                if ($client && method_exists($client, 'getCommand')) {
                    $bucket = config('filesystems.disks.b2.bucket', 'ifotoset-media');

                    $cmd = $client->getCommand('GetObject', [
                        'Bucket' => $bucket,
                        'Key' => $objectKey,
                        'ResponseContentDisposition' => $contentDisposition,
                    ]);

                    $request = $client->createPresignedRequest($cmd, $expiresAt);

                    return (string) $request->getUri();
                }
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Presigned download URL generation failed: ' . $e->getMessage());
        }

        return Storage::disk('b2')->url($objectKey);
    }

    /**
     * S3 client used for browser-facing presigned URLs. Uses the public endpoint
     * when one is configured (e.g. local docker storage reachable on localhost).
     */
    protected function presignClient(): \Aws\S3\S3Client
    {
        $client = Storage::disk('b2')->getClient();
        $publicEndpoint = config('filesystems.disks.b2.public_endpoint');

        if (empty($publicEndpoint)) {
            return $client;
        }

        return new \Aws\S3\S3Client([
            'version' => 'latest',
            'region' => config('filesystems.disks.b2.region'),
            'endpoint' => $publicEndpoint,
            'use_path_style_endpoint' => true,
            'credentials' => $client->getCredentials()->wait(),
        ]);
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
     * Deletes multiple exact object keys from storage.
     *
     * @param array<string> $objectKeys
     */
    public function deleteObjects(array $objectKeys): bool
    {
        if (empty($objectKeys)) {
            return true;
        }

        try {
            return Storage::disk('b2')->delete($objectKeys);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Storage deleteObjects failed.', [
                'count' => count($objectKeys),
                'exception' => $e->getMessage(),
            ]);
            throw $e;
        }
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
            \Illuminate\Support\Facades\Log::error('Storage deleteDirectory failed.', [
                'directory_key' => $directoryKey,
                'exception' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Computes deterministic CDN asset URL.
     */
    public function getCdnUrl(string $path, ?string $size = null, ?string $filename = null, ?string $customDomain = null): string
    {
        $domain = rtrim($customDomain ?: $this->cdnDomain, '/');
        $cleanPath = ltrim($path, '/');

        // Check if filename was embedded in path
        if ($filename && str_ends_with($cleanPath, '/' . $filename)) {
            $cleanPath = dirname($cleanPath);
        } elseif (!$filename && !empty(pathinfo($cleanPath, PATHINFO_EXTENSION))) {
            $filename = basename($cleanPath);
            $cleanPath = dirname($cleanPath);
        }

        if ($cleanPath === '.' || $cleanPath === '/') {
            $cleanPath = '';
        }

        $baseDir = $cleanPath ? "{$cleanPath}/" : '';

        if ($size) {
            $baseName = $filename ? pathinfo($filename, PATHINFO_FILENAME) : 'original';
            $file = "{$baseName}_{$size}.webp";
        } else {
            $file = $filename ?? 'original.jpg';
        }

        // Media of PIN/private galleries is only served by the edge worker with a valid token.
        // A directory-scoped token covers the original and every variant of the photo.
        if (str_starts_with($baseDir, 'protected-galleries/')) {
            $tokens = app(MediaTokenService::class);

            return $tokens->signDirectoryUrlUntil("{$baseDir}{$file}", $tokens->windowedExpiry(), $domain);
        }

        return \App\Support\Cdn::scheme() . "://{$domain}/{$baseDir}{$file}";
    }
}
?>
