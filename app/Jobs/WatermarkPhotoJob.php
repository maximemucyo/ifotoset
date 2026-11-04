<?php

namespace App\Jobs;

use App\Models\Photo;
use App\Services\PhotoWatermarkService;
use App\Support\Gallery\Watermark;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Make one photo's watermarked previews with its studio's current design.
 */
class WatermarkPhotoJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public Photo $photo)
    {
        // Its own worker (ecosystem.config.cjs), so a big batch never holds up uploads on "photos"
        $this->queue = 'watermarks';
    }

    public function backoff(): array
    {
        return [30, 120, 300];
    }

    public function uniqueId(): string
    {
        return (string) $this->photo->uuid;
    }

    public function uniqueFor(): int
    {
        return 600;
    }

    public function handle(PhotoWatermarkService $service): void
    {
        $photo = $this->photo->fresh();
        $gallery = $photo?->gallery;
        if (!$gallery?->watermark || !$gallery->user || !Watermark::allowedFor($gallery->user)) {
            return;
        }

        // The design may have changed since this was queued; always draw the current one
        $watermark = Watermark::for($gallery->user);
        if ($service->needsWatermark($photo, $watermark)) {
            $service->apply($photo, $watermark);
        }
    }
}
