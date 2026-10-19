<?php

namespace App\Services;

class PublicUrlService
{
    /**
     * Get the canonical public portfolio URL for a photographer model or username.
     */
    public function photographer(mixed $photographer): string
    {
        $username = $photographer instanceof \App\Models\User 
            ? $photographer->username 
            : (is_object($photographer) ? ($photographer->username ?? '') : (string) $photographer);

        return $this->photographerUrl($username);
    }

    /**
     * Get the canonical public gallery URL for a gallery model or username + slug.
     */
    public function gallery(mixed $gallery, ?string $slug = null): string
    {
        if ($gallery instanceof \App\Models\Gallery) {
            $username = $gallery->user?->username ?? '';
            $slug = $gallery->slug;
        } else {
            $username = (string) $gallery;
        }

        return $this->galleryUrl($username, (string) $slug);
    }

    /**
     * Get the canonical public homepage URL with optional UTM parameters.
     */
    public function home(array $utm = []): string
    {
        return $this->homeUrl($utm);
    }

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

    /**
     * Get the public platform homepage URL with optional UTM parameters.
     */
    public function homeUrl(array $utm = []): string
    {
        $protocol = config('app.public_protocol', 'https');
        $host = config('app.public_root_host', 'ifotoset.com');
        $port = config('app.public_root_port');
        $portSuffix = ($port && !in_array((int) $port, [80, 443], true)) ? ":{$port}" : '';
        $url = "{$protocol}://{$host}{$portSuffix}/";

        if (!empty($utm)) {
            $url .= '?' . http_build_query($utm);
        }

        return $url;
    }
}
