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
        $rootDomain = config('app.public_root_domain', 'ifotoset.com');
        $encodedUsername = rawurlencode(strtolower($username));
        
        return "{$protocol}://{$encodedUsername}.{$rootDomain}";
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
