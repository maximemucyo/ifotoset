<?php

namespace App\Support;

/**
 * Upload allowlist. The stored Content-Type always comes from this map,
 * never from the browser, so an upload cannot be served as HTML or script.
 */
final class MediaTypes
{
    public const IMAGES = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'webp' => 'image/webp',
        'avif' => 'image/avif',
    ];

    public const VIDEOS = [
        'mp4' => 'video/mp4',
        'mov' => 'video/quicktime',
        'webm' => 'video/webm',
    ];

    public const AVATAR_IMAGES = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'webp' => 'image/webp',
    ];

    public static function extension(string $filename): string
    {
        return strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    }

    /**
     * Canonical Content-Type for a gallery upload, or null when the type is not allowed.
     * The declared browser MIME type must agree with the extension's family.
     */
    public static function forGalleryUpload(string $filename, ?string $declaredMime = null): ?string
    {
        $extension = self::extension($filename);
        $contentType = self::IMAGES[$extension] ?? self::VIDEOS[$extension] ?? null;

        if ($contentType === null) {
            return null;
        }

        $declaredMime = strtolower(trim((string) $declaredMime));
        if ($declaredMime !== '' && $declaredMime !== 'application/octet-stream'
            && strtok($declaredMime, '/') !== strtok($contentType, '/')) {
            return null;
        }

        return $contentType;
    }

    public static function forAvatar(string $filename): ?string
    {
        return self::AVATAR_IMAGES[self::extension($filename)] ?? null;
    }

    public static function isVideoExtension(string $extension): bool
    {
        return isset(self::VIDEOS[strtolower($extension)]);
    }
}
