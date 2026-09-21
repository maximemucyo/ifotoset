<?php

namespace App\Services;

use App\Models\Gallery;
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
        $zoneId = config('services.cloudflare.zone_id') ?? env('CLOUDFLARE_ZONE_ID');
        $apiToken = config('services.cloudflare.api_token') ?? env('CLOUDFLARE_API_TOKEN');

        if ($zoneId && $apiToken) {
            try {
                $urls = [];
                $publicUrlService = app(PublicUrlService::class);
                if ($gallery->user && $gallery->user->username) {
                    $urls[] = $publicUrlService->galleryUrl($gallery->user->username, $gallery->slug);
                    $urls[] = $publicUrlService->galleryUrl($gallery->user->username, $gallery->slug) . '/photos';
                }

                if (!empty($urls)) {
                    Http::withToken($apiToken)
                        ->post("https://api.cloudflare.com/client/v4/zones/{$zoneId}/purge_cache", [
                            'files' => $urls,
                        ]);
                }
            } catch (\Throwable $e) {
                Log::warning("Cloudflare edge cache purge failed for gallery {$gallery->uuid}: " . $e->getMessage());
            }
        }
    }
}
