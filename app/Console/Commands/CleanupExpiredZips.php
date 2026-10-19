<?php

namespace App\Console\Commands;

use App\Models\GalleryDownload;
use App\Services\GalleryZipDownloadService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CleanupExpiredZips extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'gallery:cleanup-expired-zips';

    /**
     * The console command description.
     */
    protected $description = 'Clean up expired gallery ZIP downloads from storage and update their status in the database.';

    /**
     * Execute the console command.
     */
    public function handle(GalleryZipDownloadService $zipService): int
    {
        $this->info("Checking for expired ZIP downloads (older than 24 hours)...");

        $threshold = now()->subHours(24);
        $expiredCount = 0;

        GalleryDownload::whereIn('status', ['ready', 'ready_with_errors'])
            ->where('completed_at', '<', $threshold)
            ->chunkById(50, function ($downloads) use ($zipService, &$expiredCount) {
                foreach ($downloads as $download) {
                    try {
                        if ($zipService->expireIfNecessary($download)) {
                            $expiredCount++;
                        }
                    } catch (\Throwable $e) {
                        Log::error("Failed to clean up expired ZIP download ID {$download->id}: " . $e->getMessage());
                    }
                }
            });

        $this->info("Cleanup process complete! Expired and updated {$expiredCount} ZIP downloads.");
        Log::info("CleanupExpiredZips run complete.", [
            'expired_count' => $expiredCount,
        ]);

        return Command::SUCCESS;
    }
}
