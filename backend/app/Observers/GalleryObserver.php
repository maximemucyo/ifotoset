<?php

namespace App\Observers;

use App\Models\Gallery;
use App\Services\GalleryStatisticsService;
use App\Services\StorageStatisticsService;

class GalleryObserver
{
    /**
     * Handle events after all transactions are committed.
     *
     * @var bool
     */
    public $afterCommit = true;

    public function __construct(
        protected GalleryStatisticsService $statisticsService
    ) {}

    /**
     * Handle the Gallery "created" event.
     */
    public function created(Gallery $gallery): void
    {
        \App\Models\GalleryStats::firstOrCreate([
            'gallery_id' => $gallery->id,
        ], [
            'photo_count' => 0,
            'video_count' => 0,
            'downloads_count' => 0,
            'favorites_count' => 0,
            'total_bytes' => 0,
            'updated_at' => now(),
        ]);

        StorageStatisticsService::clearCache($gallery->user_id);
    }

    /**
     * Handle the Gallery "updated" event.
     */
    public function updated(Gallery $gallery): void
    {
        StorageStatisticsService::clearCache($gallery->user_id);

        // If owner changed, recalculate for both old and new owners
        if ($gallery->isDirty('user_id')) {
            $this->statisticsService->recalculateUserStorage($gallery->user_id);

            $oldUserId = $gallery->getOriginal('user_id');
            if ($oldUserId) {
                $this->statisticsService->recalculateUserStorage($oldUserId);
                StorageStatisticsService::clearCache($oldUserId);
            }
        }
    }

    /**
     * Handle the Gallery "deleted" event.
     */
    public function deleted(Gallery $gallery): void
    {
        $this->statisticsService->recalculateUserStorage($gallery->user_id);
        StorageStatisticsService::clearCache($gallery->user_id);
    }

    /**
     * Handle the Gallery "restored" event.
     */
    public function restored(Gallery $gallery): void
    {
        $this->statisticsService->recalculateUserStorage($gallery->user_id);
        StorageStatisticsService::clearCache($gallery->user_id);
    }

    /**
     * Handle the Gallery "forceDeleted" event.
     */
    public function forceDeleted(Gallery $gallery): void
    {
        $this->statisticsService->recalculateUserStorage($gallery->user_id);
        StorageStatisticsService::clearCache($gallery->user_id);
    }
}
?>
