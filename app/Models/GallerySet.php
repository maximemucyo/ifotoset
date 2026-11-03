<?php

namespace App\Models;

use App\Casts\UuidBinaryCast;
use App\Traits\HasBinaryUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A part of a gallery, such as "Ceremony" or "Reception". Photos without a set still show under "All".
 */
class GallerySet extends Model
{
    use HasBinaryUuid;

    protected $fillable = [
        'uuid',
        'gallery_id',
        'name',
        'sort_order',
    ];

    protected $casts = [
        'uuid' => UuidBinaryCast::class,
        'sort_order' => 'integer',
    ];

    public function gallery(): BelongsTo
    {
        return $this->belongsTo(Gallery::class);
    }

    public function photos(): HasMany
    {
        return $this->hasMany(Photo::class, 'set_id');
    }
}
