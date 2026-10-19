<?php

namespace App\Models;

use App\Casts\UuidBinaryCast;
use App\Traits\HasBinaryUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class GooglePhotoSync extends Model
{
    use HasBinaryUuid;

    protected $fillable = [
        'uuid',
        'gallery_id',
        'status',
        'total_photos',
        'processed_photos',
        'failed_photos',
        'album_id',
        'album_url',
        'error',
        'started_at',
        'completed_at',
        'email',
        'notify_when_ready',
        'notification_sent_at',
    ];

    protected $casts = [
        'uuid' => UuidBinaryCast::class,
        'total_photos' => 'integer',
        'processed_photos' => 'integer',
        'failed_photos' => 'integer',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'notify_when_ready' => 'boolean',
        'notification_sent_at' => 'datetime',
    ];

    public function gallery(): BelongsTo
    {
        return $this->belongsTo(Gallery::class);
    }

    public function credential(): HasOne
    {
        return $this->hasOne(GooglePhotoCredential::class, 'sync_id');
    }
}
