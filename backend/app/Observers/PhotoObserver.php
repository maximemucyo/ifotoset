<?php

namespace App\Observers;

use App\Models\Photo;
use App\Services\GalleryStatisticsService;

class PhotoObserver
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
     * Handle the Photo "created" event.
     */
    public function created(Photo $photo): void
    {
        $this->recalculate($photo);
    }

    /**
     * Handle the Photo "updated" event.
     */
    public function updated(Photo $photo): void
    {
        // Only trigger recalculation if relevant attributes changed
        if ($photo->isDirty('gallery_id') || $photo->isDirty('size') || $photo->isDirty('deleted_at')) {
            $this->recalculate($photo);

            // If it was moved between galleries, recalculate the old gallery too
            if ($photo->isDirty('gallery_id')) {
                $oldGalleryId = $photo->getOriginal('gallery_id');
                if ($oldGalleryId) {
                    $this->statisticsService->recalculateGallery($oldGalleryId);
                }
            }
        }
    }

    /**
     * Handle the Photo "deleted" event.
     */
    public function deleted(Photo $photo): void
    {
        $this->recalculate($photo);
    }

    /**
     * Handle the Photo "restored" event.
     */
    public function restored(Photo $photo): void
    {
        $this->recalculate($photo);
    }

    /**
     * Handle the Photo "forceDeleted" event.
     */
    public function forceDeleted(Photo $photo): void
    {
        $this->recalculate($photo);
    }

    /**
     * Helper to run calculations for the photo's current gallery and owner.
     */
    protected function recalculate(Photo $photo): void
    {
        if ($photo->gallery_id) {
            $this->statisticsService->recalculateGallery($photo->gallery_id);
            
            $gallery = $photo->gallery;
            if ($gallery) {
                $this->statisticsService->recalculateUserStorage($gallery->user_id);
            }
        }
    }
}
?>
