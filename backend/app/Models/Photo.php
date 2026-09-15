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
        'path',
        'filename',
        'original_filename',
        'stored_filename',
        'mime_type',
        'size',
        'width',
        'height',
        'checksum',
        'blurhash',
        'taken_at',
        'sort_order',
        'status',
        'is_hidden',
    ];

    protected $casts = [
        'uuid' => UuidBinaryCast::class,
        'size' => 'integer',
        'width' => 'integer',
        'height' => 'integer',
        'taken_at' => 'datetime',
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

    /**
     * Compute deterministic CDN URL for this photo with optional size variant (xs, sm, md, lg, xl).
     * If photo is not ready yet, it seamlessly falls back to the original uploaded file.
     */
    public function getUrl(?string $size = null): string
    {
        return app(\App\Services\StorageService::class)->getCdnUrl(
            $this->path,
            ($size && $this->status === \App\Enums\PhotoStatus::Ready->value) ? $size : null,
            $this->filename ?? $this->stored_filename,
            $this->disk?->cdn_domain
        );
    }

    public function getThumbnailUrlAttribute(): string
    {
        return $this->getUrl('sm');
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
