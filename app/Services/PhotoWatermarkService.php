<?php

namespace App\Services;

use App\Enums\PhotoStatus;
use App\Models\Photo;
use App\Support\Gallery\Watermark;
use App\Support\Gallery\WatermarkRenderer;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;
use Throwable;

/**
 * Makes a photo's watermarked previews ("{name}_{size}_w{version}.webp") beside its clean ones.
 * Clients are shown these when the gallery's watermark is on; downloads stay the clean originals.
 */
class PhotoWatermarkService
{
    public function __construct(protected WatermarkRenderer $renderer) {}

    public static function suffix(string $version): string
    {
        return '_w' . $version;
    }

    public function needsWatermark(Photo $photo, Watermark $watermark): bool
    {
        return !$photo->isVideo()
            && $photo->status === PhotoStatus::Ready->value
            && $photo->watermark_version !== $watermark->version();
    }

    /**
     * Draw the watermark on the photo's largest preview, save every size, then drop the previous design's files.
     */
    public function apply(Photo $photo, Watermark $watermark): void
    {
        $disk = Storage::disk('b2');
        $baseName = pathinfo($photo->filename, PATHINFO_FILENAME);
        $version = $watermark->version();

        $manager = new ImageManager(new Driver());
        $image = $manager->read($disk->get("{$photo->path}/{$baseName}_xl.webp"));
        $logo = $watermark->type() === 'logo'
            ? $manager->read($disk->get($watermark->owner()->logo_path))->core()->native()
            : null;
        $this->renderer->apply($image->core()->native(), $watermark, $logo);

        $variants = config('images.variants');
        arsort($variants);
        foreach ($variants as $size => $width) {
            if ($image->width() > $width) {
                $image->scale(width: $width);
            }
            $disk->put("{$photo->path}/{$baseName}_{$size}" . self::suffix($version) . '.webp', (string) $image->toWebp(config('images.quality', 82)));
        }

        $previous = $photo->watermark_version;
        $photo->update(['watermark_version' => $version]);

        if ($previous && $previous !== $version) {
            $this->deleteVersion($photo, $previous);
        }
    }

    /**
     * Keys of the watermarked previews made with one design.
     *
     * @return string[]
     */
    public static function keys(Photo $photo, string $version, ?string $filename = null): array
    {
        $baseName = pathinfo($filename ?? $photo->filename, PATHINFO_FILENAME);

        return array_map(
            fn (string $size) => "{$photo->path}/{$baseName}_{$size}" . self::suffix($version) . '.webp',
            array_keys(config('images.variants')),
        );
    }

    protected function deleteVersion(Photo $photo, string $version): void
    {
        try {
            Storage::disk('b2')->delete(self::keys($photo, $version));
        } catch (Throwable $e) {
            Log::warning("Could not delete old watermarked previews of photo {$photo->uuid}: {$e->getMessage()}");
        }
    }
}
