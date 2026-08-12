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
    ];

    protected $casts = [
        'uuid' => UuidBinaryCast::class,
        'size' => 'integer',
        'width' => 'integer',
        'height' => 'integer',
        'taken_at' => 'datetime',
        'sort_order' => 'integer',
    ];

    public function gallery(): BelongsTo
    {
        return $this->belongsTo(Gallery::class);
    }

    public function disk(): BelongsTo
    {
        return $this->belongsTo(StorageDisk::class, 'disk_id');
    }
}
?>
