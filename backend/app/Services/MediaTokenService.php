<?php

namespace App\Services;

use App\Models\Gallery;
use App\Models\Photo;

class MediaTokenService
{
    protected string $cdnDomain;
    protected string $cdnSecret;

    public function __construct()
    {
        $this->cdnDomain = config('filesystems.disks.b2.cdn_domain', 'cdn.ifotoset.com');
        $this->cdnSecret = (string) (config('filesystems.disks.b2.cdn_secret') ?: config('app.key'));
    }

    /**
     * Generate delivery streaming URL for a photo/video.
     * If the gallery is protected (PIN or private), generate a short-lived HMAC signed URL.
     */
    public function getDeliveryUrl(Photo $photo, ?Gallery $gallery = null, int $ttlHours = 2): string
    {
        $gallery = $gallery ?: $photo->gallery;
        $targetPath = $photo->delivery_path ?: $photo->original_path ?: ($photo->path . '/' . ($photo->filename ?? $photo->stored_filename));
        $cleanPath = ltrim($targetPath, '/');

        // Check if gallery is protected
        $isProtected = $gallery && ($gallery->visibility !== 'public' || !empty($gallery->password_hash));

        $domain = $photo->diskCdnDomain() ?: $this->cdnDomain;

        if ($isProtected || str_starts_with($cleanPath, 'protected-galleries/')) {
            return $this->signUrl($cleanPath, $ttlHours, $domain);
        }

        return \App\Support\Cdn::scheme() . "://{$domain}/{$cleanPath}";
    }

    /**
     * Generate the URL players should stream: the HLS master playlist when one exists,
     * otherwise the MP4 delivery URL.
     */
    public function getStreamUrl(Photo $photo, ?Gallery $gallery = null, int $ttlHours = 4): string
    {
        if (!$photo->hls_path || !config('filesystems.disks.b2.hls_streaming', true)) {
            return $this->getDeliveryUrl($photo, $gallery, $ttlHours);
        }

        $gallery = $gallery ?: $photo->gallery;
        $cleanPath = ltrim($photo->hls_path, '/');
        $isProtected = $gallery && ($gallery->visibility !== 'public' || !empty($gallery->password_hash));

        $domain = $photo->diskCdnDomain() ?: $this->cdnDomain;

        if ($isProtected || str_starts_with($cleanPath, 'protected-galleries/')) {
            return $this->signDirectoryUrl($cleanPath, $ttlHours, $domain);
        }

        return \App\Support\Cdn::scheme() . "://{$domain}/{$cleanPath}";
    }

    /**
     * Sign a file URL with a token scoped to its whole directory and embedded in the path:
     *   {dir}/_t/{exp}/{sig}/{file}
     * Relative references inside HLS playlists (variant playlists, segments) inherit the token,
     * which query-string tokens would lose. The edge worker verifies and strips it.
     */
    public function signDirectoryUrl(string $path, int $ttlHours = 4, ?string $domain = null): string
    {
        $domain = $domain ?: $this->cdnDomain;
        $cleanPath = ltrim($path, '/');
        $dir = dirname($cleanPath);
        $file = basename($cleanPath);
        $exp = now()->addHours($ttlHours)->timestamp;
        $sig = hash_hmac('sha256', "{$dir}/:{$exp}", $this->cdnSecret);

        return \App\Support\Cdn::scheme() . "://{$domain}/{$dir}/_t/{$exp}/{$sig}/{$file}";
    }

    /**
     * Generate short-lived HMAC signed URL.
     */
    public function signUrl(string $path, int $ttlHours = 2, ?string $domain = null): string
    {
        $domain = $domain ?: $this->cdnDomain;
        $cleanPath = ltrim($path, '/');
        $exp = now()->addHours($ttlHours)->timestamp;
        $sig = hash_hmac('sha256', "{$cleanPath}:{$exp}", $this->cdnSecret);

        return \App\Support\Cdn::scheme() . "://{$domain}/{$cleanPath}?exp={$exp}&sig={$sig}";
    }

    /**
     * Validate an incoming token at the application level (mirrors edge worker logic).
     */
    public function validateToken(string $path, int $exp, string $sig): bool
    {
        if ($exp < now()->timestamp) {
            return false;
        }

        $cleanPath = ltrim($path, '/');
        $expected = hash_hmac('sha256', "{$cleanPath}:{$exp}", $this->cdnSecret);

        return hash_equals($expected, $sig);
    }
}
