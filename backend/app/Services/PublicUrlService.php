<?php

namespace App\Services;

class PublicUrlService
{
    /**
     * Get the public portfolio URL for a photographer.
     */
    public function photographerUrl(string $username): string
    {
        $protocol = config('app.public_protocol', 'https');
        $host = config('app.public_root_host', 'ifotoset.com');
        $port = config('app.public_root_port');
        $portSuffix = ($port && !in_array((int) $port, [80, 443], true)) ? ":{$port}" : '';
        $encodedUsername = rawurlencode(strtolower($username));
        
        return "{$protocol}://{$encodedUsername}.{$host}{$portSuffix}";
    }

    /**
     * Get the public gallery URL.
     */
    public function galleryUrl(string $username, string $slug): string
    {
        $encodedSlug = rawurlencode($slug);
        return $this->photographerUrl($username) . '/' . $encodedSlug;
    }

    /**
     * Get the public gallery export URL.
     */
    public function galleryExportUrl(string $username, string $slug): string
    {
        $encodedSlug = rawurlencode($slug);
        return $this->photographerUrl($username) . '/' . $encodedSlug . '/export';
    }
}
