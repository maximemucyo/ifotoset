<?php

namespace App\Models;

use App\Casts\UuidBinaryCast;
use App\Traits\HasBinaryUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GooglePhotoAuthorization extends Model
{
    use HasBinaryUuid;

    protected $fillable = [
        'uuid',
        'gallery_id',
        'state',
        'photo_uuids',
        'consumed_at',
        'expires_at',
    ];

    protected $casts = [
        'uuid' => UuidBinaryCast::class,
        'photo_uuids' => 'array',
        'consumed_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function gallery(): BelongsTo
    {
        return $this->belongsTo(Gallery::class);
    }
}
