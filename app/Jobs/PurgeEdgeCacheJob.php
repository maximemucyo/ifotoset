<?php

namespace App\Jobs;

use App\Services\GalleryCachePurgeService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Clears one URL prefix from Cloudflare's cache. Unique until it runs, so the many
 * model events of an upload or a settings save collapse into a single API call.
 */
class PurgeEdgeCacheJob implements ShouldQueue, ShouldBeUniqueUntilProcessing
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;

    public function __construct(
        public string $prefix
    ) {}

    public function backoff(): array
    {
        return [30, 120];
    }

    public function uniqueId(): string
    {
        return 'purge_edge_cache_' . $this->prefix;
    }

    public function handle(GalleryCachePurgeService $purger): void
    {
        if (GalleryCachePurgeService::configured() && !$purger->purgePrefixes([$this->prefix])) {
            $this->release(60);
        }
    }
}
