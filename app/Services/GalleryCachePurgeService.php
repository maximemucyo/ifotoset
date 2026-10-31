<?php

namespace App\Services;

use App\Jobs\PurgeEdgeCacheJob;
use App\Models\Gallery;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GalleryCachePurgeService
{
    /**
     * Invalidate internal application caches and Cloudflare/edge CDN for the given gallery.
     */
    public function purge(Gallery $gallery): void
    {
        // 1. Invalidate internal application cache tags / keys
        try {
            Cache::forget('admin_dashboard_stats');
            Cache::forget("gallery_stats_{$gallery->id}");
            Cache::forget("gallery_public_{$gallery->slug}");
        } catch (\Throwable $e) {
            Log::warning("Failed to clear application cache for gallery {$gallery->uuid}: " . $e->getMessage());
        }

        // 2. Cloudflare / Edge Cache Invalidation (if configured)
        if ($gallery->user) {
            $this->purgePrefixes([$this->photographerPrefix($gallery->user->username)]);
        }
    }

    /**
     * Queue a purge of every cached page on a photographer's site (portfolio and galleries).
     * Queued with a short delay and deduplicated, so a 300-photo upload sends one request.
     */
    public function queuePhotographer(?User $photographer, ?string $username = null): void
    {
        $username ??= $photographer?->username;
        if (self::configured() && $username) {
            PurgeEdgeCacheJob::dispatch($this->photographerPrefix($username))->delay(now()->addSeconds(15));
        }
    }

    /**
     * Queue a purge of the main site (home, features, pricing, legal pages).
     */
    public function queueMainSite(): void
    {
        if (self::configured()) {
            PurgeEdgeCacheJob::dispatch($this->host(config('app.public_root_host')) . '/')->delay(now()->addSeconds(15));
        }
    }

    /**
     * Drop every cached page (used after a deploy changes the HTML); photos and assets stay cached.
     */
    public function purgeAllPages(): bool
    {
        return $this->send(['tags' => [\App\Support\EdgeCache::PAGE_TAG]]);
    }

    /**
     * Purge cached URLs by prefix ("host/path"), which also covers query-string variants.
     */
    public function purgePrefixes(array $prefixes): bool
    {
        return $this->send(['prefixes' => array_values($prefixes)]);
    }

    public static function configured(): bool
    {
        return config('services.cloudflare.zone_id') && config('services.cloudflare.api_token');
    }

    private function send(array $payload): bool
    {
        if (!self::configured()) {
            return false;
        }

        try {
            $response = Http::withToken(config('services.cloudflare.api_token'))
                ->timeout(10)
                ->post('https://api.cloudflare.com/client/v4/zones/' . config('services.cloudflare.zone_id') . '/purge_cache', $payload);

            if (!$response->successful()) {
                Log::warning('Cloudflare cache purge was rejected: ' . $response->body());
            }

            return $response->successful();
        } catch (\Throwable $e) {
            Log::warning('Cloudflare cache purge failed: ' . $e->getMessage());

            return false;
        }
    }

    private function photographerPrefix(string $username): string
    {
        return $this->host(strtolower($username) . '.' . config('app.public_root_host')) . '/';
    }

    private function host(string $host): string
    {
        return strtolower(rtrim($host, '/'));
    }
}
