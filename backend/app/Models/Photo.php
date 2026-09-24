<?php

namespace App\Models;

use App\Casts\UuidBinaryCast;
use App\Traits\HasBinaryUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Photo extends Model
{
    use SoftDeletes, HasBinaryUuid;

    protected static function booted()
    {
        static::created(function ($photo) {
            $gallery = $photo->gallery;
            if ($gallery) {
                app(\App\Services\GalleryCoverService::class)->setAutoCover($gallery);
            }
        });

        static::deleted(function ($photo) {
            // Retrieve relation even if it's soft-deleted
            $gallery = $photo->gallery;
            if ($gallery) {
                app(\App\Services\GalleryCoverService::class)->handlePhotoDeletion($gallery, $photo);
            }
        });
    }

    protected $fillable = [
        'uuid',
        'gallery_id',
        'album_id',
        'disk_id',
        'media_type',
        'path',
        'filename',
        'original_filename',
        'stored_filename',
        'original_path',
        'delivery_path',
        'poster_path',
        'video_metadata',
        'mime_type',
        'size',
        'width',
        'height',
        'duration_seconds',
        'checksum',
        'blurhash',
        'taken_at',
        'sort_order',
        'status',
        'processing_error',
        'processed_at',
        'is_hidden',
    ];

    protected $casts = [
        'uuid' => UuidBinaryCast::class,
        'size' => 'integer',
        'width' => 'integer',
        'height' => 'integer',
        'duration_seconds' => 'integer',
        'video_metadata' => 'array',
        'taken_at' => 'datetime',
        'processed_at' => 'datetime',
        'sort_order' => 'integer',
        'is_hidden' => 'boolean',
    ];

    public function gallery(): BelongsTo
    {
        return $this->belongsTo(Gallery::class);
    }

    public function disk(): BelongsTo
    {
        return $this->belongsTo(StorageDisk::class, 'disk_id');
    }

    public function isVideo(): bool
    {
        return $this->media_type === 'video';
    }

    public function isPhoto(): bool
    {
        return $this->media_type === 'photo' || empty($this->media_type);
    }

    public function getDurationFormattedAttribute(): ?string
    {
        if ($this->duration_seconds === null) {
            return null;
        }

        $seconds = (int) $this->duration_seconds;
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $remSeconds = $seconds % 60;

        if ($hours > 0) {
            return sprintf('%02d:%02d:%02d', $hours, $minutes, $remSeconds);
        }

        return sprintf('%02d:%02d', $minutes, $remSeconds);
    }

    /**
     * Compute deterministic CDN URL for this photo with optional size variant (xs, sm, md, lg, xl).
     * If media is a video:
     *   - size specified => returns WebP poster thumbnail (or fallback)
     *   - size null => returns delivery streaming URL
     */
    public function getUrl(?string $size = null): string
    {
        if ($this->isVideo()) {
            if ($size !== null) {
                return $this->getPosterUrl($size) ?? '';
            }
            return $this->getDeliveryUrl() ?? '';
        }

        return app(\App\Services\StorageService::class)->getCdnUrl(
            $this->path,
            ($size && $this->status === \App\Enums\PhotoStatus::Ready->value) ? $size : null,
            $this->filename ?? $this->stored_filename,
            $this->disk?->cdn_domain
        );
    }

    public function getPosterUrl(?string $size = null): ?string
    {
        if (!$this->poster_path) {
            return null;
        }

        return app(\App\Services\StorageService::class)->getCdnUrl(
            dirname($this->poster_path),
            $size,
            basename($this->poster_path),
            $this->disk?->cdn_domain
        );
    }

    public function getDeliveryUrl(): ?string
    {
        $targetPath = $this->delivery_path ?: $this->original_path ?: ($this->path . '/' . ($this->filename ?? $this->stored_filename));
        if (!$targetPath) {
            return null;
        }

        return app(\App\Services\StorageService::class)->getCdnUrl(
            dirname($targetPath),
            null,
            basename($targetPath),
            $this->disk?->cdn_domain
        );
    }

    public function getDeliveryDownloadUrl(): string
    {
        $targetPath = $this->delivery_path ?: $this->original_path ?: ($this->path . '/' . ($this->filename ?? $this->stored_filename));
        $filename = $this->original_filename ?: basename($targetPath);

        // Ensure filename ends in .mp4 for video delivery
        if ($this->isVideo() && !str_ends_with(strtolower($filename), '.mp4')) {
            $filename = pathinfo($filename, PATHINFO_FILENAME) . '.mp4';
        }

        return app(\App\Services\StorageService::class)->generatePresignedDownloadUrl(
            $targetPath,
            $filename,
            now()->addHours(2)
        );
    }

    public function getOriginalDownloadUrl(): string
    {
        $targetPath = $this->original_path ?: ($this->path . '/' . ($this->filename ?? $this->stored_filename));
        $filename = $this->original_filename ?: basename($targetPath);

        return app(\App\Services\StorageService::class)->generatePresignedDownloadUrl(
            $targetPath,
            $filename,
            now()->addHours(2)
        );
    }

    public function getThumbnailUrl(?string $size = 'sm'): string
    {
        if ($this->isVideo()) {
            return $this->getPosterUrl($size) ?? '';
        }
        return $this->getUrl($size);
    }

    public function getThumbnailUrlAttribute(): string
    {
        return $this->getThumbnailUrl('sm');
    }

    public function getMediumUrlAttribute(): string
    {
        return $this->getUrl('md');
    }

    public function getLargeUrlAttribute(): string
    {
        return $this->getUrl('lg');
    }

    public function getFullUrlAttribute(): string
    {
        return $this->getUrl('xl');
    }

    public function getOriginalUrlAttribute(): string
    {
        return $this->getUrl();
    }
}
?>
