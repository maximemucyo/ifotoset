<?php

namespace App\Console\Commands;

use App\Models\Gallery;
use App\Models\Photo;
use App\Services\GalleryStatisticsService;
use App\Services\TrashService;
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
    protected $description = 'Permanently purge soft-deleted galleries and photos from database and storage that have exceeded the retention window.';

    /**
     * Execute the console command.
     */
    public function handle(TrashService $trashService, GalleryStatisticsService $statisticsService): int
    {
        $retentionDays = $this->option('days') !== null
            ? (int) $this->option('days')
            : (int) config('filesystems.trash_retention_days', 7);

        // 168-hour exact retention cutoff
        $threshold = Carbon::now()->subHours($retentionDays * 24);

        $this->info("Purging soft-deleted items older than {$retentionDays} days (threshold: {$threshold->toDateTimeString()})...");

        $purgedGalleriesCount = 0;
        $purgedPhotosCount = 0;
        $affectedUserIds = [];

        // 1. Purge expired galleries first in chunks (cascades to their photos and storage objects)
        Gallery::onlyTrashed()
            ->where('deleted_at', '<=', $threshold)
            ->chunkById(50, function ($galleries) use ($trashService, &$purgedGalleriesCount, &$affectedUserIds) {
                foreach ($galleries as $gallery) {
                    try {
                        $affectedUserIds[$gallery->user_id] = true;
                        $trashService->purgeGallery($gallery->id, recalculateStats: false);
                        $purgedGalleriesCount++;
                    } catch (\Throwable $e) {
                        Log::error("PurgeExpiredTrash command failed for gallery {$gallery->id}: " . $e->getMessage());
                    }
                }
            });

        // 2. Purge remaining individually expired photos whose parent gallery is active
        Photo::onlyTrashed()
            ->where('deleted_at', '<=', $threshold)
            ->whereHas('gallery', function ($query) {
                $query->whereNull('deleted_at');
            })
            ->with('gallery')
            ->chunkById(100, function ($photos) use ($trashService, &$purgedPhotosCount, &$affectedUserIds) {
                foreach ($photos as $photo) {
                    try {
                        if ($photo->gallery?->user_id) {
                            $affectedUserIds[$photo->gallery->user_id] = true;
                        }
                        $trashService->purgePhoto($photo->id, recalculateStats: false);
                        $purgedPhotosCount++;
                    } catch (\Throwable $e) {
                        Log::error("PurgeExpiredTrash command failed for photo {$photo->id}: " . $e->getMessage());
                    }
                }
            });

        // 3. Recalculate storage quotas once per affected user
        foreach (array_keys($affectedUserIds) as $userId) {
            try {
                $statisticsService->recalculateUserStorage((int) $userId);
            } catch (\Throwable $e) {
                Log::warning("Failed to recalculate storage for user {$userId} during purge: " . $e->getMessage());
            }
        }

        $this->info("Purge process complete! Permanently deleted {$purgedGalleriesCount} galleries and {$purgedPhotosCount} photos.");
        Log::info("PurgeExpiredTrash run complete.", [
            'galleries_purged' => $purgedGalleriesCount,
            'photos_purged' => $purgedPhotosCount,
            'retention_days' => $retentionDays,
            'affected_users_count' => count($affectedUserIds),
        ]);

        return Command::SUCCESS;
    }
}
