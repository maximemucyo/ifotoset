<?php

namespace App\Jobs;

use App\Services\TrashService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class PurgePhotoJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 5;

    /**
     * The unique lock duration in seconds (30 minutes).
     */
    public int $uniqueFor = 1800;

    /**
     * Calculate the number of seconds to wait before retrying the job.
     */
    public function backoff(): array
    {
        return [60, 300, 900, 1800];
    }

    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $photoId
    ) {}

    /**
     * Get the unique ID for the lock.
     */
    public function uniqueId(): string
    {
        return 'purge_photo_' . $this->photoId;
    }

    /**
     * Execute the job.
     */
    public function handle(TrashService $trashService): void
    {
        $trashService->purgePhoto($this->photoId);
    }
}
