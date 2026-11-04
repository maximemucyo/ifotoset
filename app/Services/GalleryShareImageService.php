<?php

namespace App\Services;

use App\Enums\PhotoStatus;
use App\Models\Gallery;
use App\Models\Photo;
use App\Queries\GalleryPhotoQuery;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

/**
 * Link-preview image (WhatsApp, Facebook, iMessage...) for galleries with a public link.
 *
 * Served from the gallery's own host because the CDN refuses requests without an ifotoset
 * referrer, which is exactly how link-preview crawlers fetch images.
 */
class GalleryShareImageService
{
    public const WIDTH = 1200;
    public const HEIGHT = 630;

    public function __construct(protected GalleryPhotoQuery $photoQuery) {}

    /**
     * The photo shown in previews: the chosen cover, else the first photo clients see.
     */
    public function photo(Gallery $gallery): ?Photo
    {
        $cover = $gallery->coverPhoto;
        if ($cover && !$cover->is_hidden && $cover->status === PhotoStatus::Ready->value && $cover->getThumbnailUrl('lg')) {
            return $cover;
        }

        return $this->photoQuery->applyOrder($gallery->photos(), $gallery)
            ->where('is_hidden', false)
            ->where('status', PhotoStatus::Ready->value)
            ->where(fn ($q) => $q->where('media_type', '!=', 'video')->orWhereNull('media_type')->orWhereNotNull('poster_path'))
            ->first();
    }

    /**
     * Public URL of the preview image, versioned so crawlers refetch when the cover changes.
     */
    public function url(Gallery $gallery): ?string
    {
        if (!$gallery->hasPublicLink() || !($photo = $this->photo($gallery))) {
            return null;
        }

        return app(PublicUrlService::class)->gallery($gallery) . '/share.jpg?v=' . $this->version($photo, $gallery);
    }

    /**
     * Local path of the rendered JPEG, rendering it on first request.
     */
    public function path(Gallery $gallery): ?string
    {
        if (!$gallery->hasPublicLink() || !($photo = $this->photo($gallery))) {
            return null;
        }

        $dir = storage_path("app/share-images/{$gallery->id}");
        $path = "{$dir}/{$this->version($photo, $gallery)}.jpg";
        if (File::exists($path)) {
            return $path;
        }

        Photo::showWatermarkFor($gallery);
        $key = ltrim((string) parse_url($photo->getThumbnailUrl('lg'), PHP_URL_PATH), '/');
        $source = Storage::disk('b2')->get(rawurldecode($key));
        if (!$source) {
            return null;
        }

        $image = (new ImageManager(new Driver()))->read($source);
        if ($image->height() / $image->width() > self::HEIGHT / self::WIDTH) {
            // Taller than the card: faces sit in the upper part of most portraits, so crop above centre
            $image->scale(width: self::WIDTH);
            $image->crop(self::WIDTH, self::HEIGHT, 0, (int) round(($image->height() - self::HEIGHT) * 0.25));
        } else {
            $image->cover(self::WIDTH, self::HEIGHT);
        }
        $jpeg = $image->toJpeg(82);

        // Drop renders of earlier covers before writing the current one
        File::ensureDirectoryExists($dir);
        File::delete(File::glob("{$dir}/*.jpg"));
        File::put($path, (string) $jpeg);

        return $path;
    }

    private function version(Photo $photo, Gallery $gallery): string
    {
        // A new watermark updates the photo; turning it off only changes the gallery
        return $photo->id . '-' . $photo->updated_at?->timestamp . ($gallery->watermark ? '-w' : '');
    }
}
