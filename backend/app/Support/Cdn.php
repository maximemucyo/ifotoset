<?php

namespace App\Support;

class Cdn
{
    /**
     * URL scheme used for CDN / media delivery URLs.
     * Production uses https behind Cloudflare; local dev (MinIO) uses http.
     */
    public static function scheme(): string
    {
        return (string) config('filesystems.disks.b2.cdn_protocol', 'https');
    }
}
