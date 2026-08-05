<?php

namespace App\Console\Commands;

use App\Models\Gallery;
use App\Models\Photo;
use App\Jobs\PurgeGalleryJob;
use App\Jobs\PurgePhotoJob;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class PurgeExpiredTrash extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'ifotoset:purge-expired-trash {--days= : Override trash retention threshold in days}';

    /**
     * The console command description.
     */
    protected $description = 'Permanently purge soft-deleted galleries and photos from database and B2 storage that have exceeded the retention window.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $retentionDays = $this->option('days') !== null
            ? (int) $this->option('days')
            : (int) config('filesystems.trash_retention_days', 30);

        $threshold = Carbon::now()->subDays($retentionDays);

        $this->info("Purging soft-deleted items older than {$retentionDays} days (threshold: {$threshold->toDateTimeString()})...");

        // 1. Purge Galleries chunked
        $purgedGalleriesCount = 0;
        Gallery::onlyTrashed()
            ->where('deleted_at', '<', $threshold)
            ->chunkById(50, function ($galleries) use (&$purgedGalleriesCount) {
                foreach ($galleries as $gallery) {
                    try {
                        PurgeGalleryJob::dispatch($gallery->id);
                        $purgedGalleriesCount++;
                    } catch (\Throwable $e) {
                        Log::error("PurgeExpiredTrash command failed to dispatch job for gallery {$gallery->id}: " . $e->getMessage());
                    }
                }
            });

        // 2. Purge Photos chunked
        $purgedPhotosCount = 0;
        Photo::onlyTrashed()
            ->where('deleted_at', '<', $threshold)
            ->chunkById(100, function ($photos) use (&$purgedPhotosCount) {
                foreach ($photos as $photo) {
                    try {
                        PurgePhotoJob::dispatch($photo->id);
                        $purgedPhotosCount++;
                    } catch (\Throwable $e) {
                        Log::error("PurgeExpiredTrash command failed to dispatch job for photo {$photo->id}: " . $e->getMessage());
                    }
                }
            });

        $this->info("Purge process complete! Dispatched background purge jobs for {$purgedGalleriesCount} galleries and {$purgedPhotosCount} photos.");
        Log::info("PurgeExpiredTrash run complete.", [
            'galleries_dispatched' => $purgedGalleriesCount,
            'photos_dispatched' => $purgedPhotosCount,
            'retention_days' => $retentionDays,
        ]);

        return Command::SUCCESS;
    }
}
