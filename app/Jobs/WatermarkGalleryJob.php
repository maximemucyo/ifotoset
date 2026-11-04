<?php

namespace App\Jobs;

use App\Enums\PhotoStatus;
use App\Models\Gallery;
use App\Support\Gallery\Watermark;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Queue a watermark for every photo in a gallery that doesn't have the current design yet:
 * when the gallery's watermark is turned on, or when the studio changes how it looks.
 */
class WatermarkGalleryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public Gallery $gallery) {}

    public function handle(): void
    {
        $gallery = $this->gallery->fresh();
        if (!$gallery?->watermark || !$gallery->user || !Watermark::allowedFor($gallery->user)) {
            return;
        }

        self::pending($gallery, Watermark::for($gallery->user)->version())
            ->chunkById(200, fn ($photos) => $photos->each(fn ($photo) => WatermarkPhotoJob::dispatch($photo)));
    }

    /**
     * Ready photos (not videos) still without previews in this design.
     */
    public static function pending(Gallery $gallery, string $version)
    {
        return $gallery->photos()
            ->where('status', PhotoStatus::Ready->value)
            ->where(fn ($q) => $q->where('media_type', 'photo')->orWhereNull('media_type'))
            ->where(fn ($q) => $q->whereNull('watermark_version')->orWhere('watermark_version', '!=', $version));
    }
}
