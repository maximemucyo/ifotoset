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

        if ($isProtected || str_starts_with($cleanPath, 'protected-galleries/')) {
            return $this->signUrl($cleanPath, $ttlHours);
        }

        return "https://{$this->cdnDomain}/{$cleanPath}";
    }

    /**
     * Generate short-lived HMAC signed URL.
     */
    public function signUrl(string $path, int $ttlHours = 2): string
    {
        $cleanPath = ltrim($path, '/');
        $exp = now()->addHours($ttlHours)->timestamp;
        $sig = hash_hmac('sha256', "{$cleanPath}:{$exp}", $this->cdnSecret);

        return "https://{$this->cdnDomain}/{$cleanPath}?exp={$exp}&sig={$sig}";
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
