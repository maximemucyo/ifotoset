<?php

namespace App\Console\Commands;

use App\Services\GalleryCachePurgeService;
use Illuminate\Console\Command;

class PurgeEdgeCache extends Command
{
    protected $signature = 'edge-cache:purge';

    protected $description = 'Clear every page Cloudflare has cached, leaving photos and assets (run after a deploy)';

    public function handle(GalleryCachePurgeService $purger): int
    {
        if (!GalleryCachePurgeService::configured()) {
            $this->line('Cloudflare purging is not configured; cached pages expire on their own.');

            return self::SUCCESS;
        }

        if (!$purger->purgeAllPages()) {
            $this->warn('Cloudflare rejected the purge; see the log.');

            return self::FAILURE;
        }

        $this->info('Cloudflare cache cleared.');

        return self::SUCCESS;
    }
}
