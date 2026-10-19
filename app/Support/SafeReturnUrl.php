<?php

namespace App\Support;

class SafeReturnUrl
{
    /**
     * Validate and sanitize a return URL to ensure it is strictly a local studio route.
     *
     * @param string|null $url
     * @param string $fallback
     * @return string
     */
    public static function sanitize(?string $url, string $fallback = '/studio/galleries'): string
    {
        if (empty($url) || !is_string($url)) {
            return $fallback;
        }

        $trimmed = trim($url);

        // Disallow backslashes and control characters
        if (str_contains($trimmed, '\\') || preg_match('/[\x00-\x1F\x7F]/', $trimmed)) {
            return $fallback;
        }

        // Disallow scheme indicators (e.g. http://, javascript:, data:)
        if (str_contains($trimmed, '://') || preg_match('/^[a-zA-Z][a-zA-Z0-9+.-]*:/', $trimmed)) {
            return $fallback;
        }

        // Disallow protocol-relative URLs (e.g. //evil.com)
        if (str_starts_with($trimmed, '//')) {
            return $fallback;
        }

        // Must begin with /studio/
        if (!str_starts_with($trimmed, '/studio/') && $trimmed !== '/studio') {
            return $fallback;
        }

        // Parse path component
        $parsed = parse_url($trimmed);
        if (!$parsed || !isset($parsed['path'])) {
            return $fallback;
        }

        $path = $parsed['path'];
        if (!str_starts_with($path, '/studio/') && $path !== '/studio') {
            return $fallback;
        }

        return $trimmed;
    }
}
