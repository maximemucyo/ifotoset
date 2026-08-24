<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GalleryDownload extends Model
{
    protected $fillable = [
        'gallery_id',
        'status',
        'generated_at',
        'storage_path',
        'size',
        'photo_snapshot_hash',
        'total_photos',
        'processed_photos',
        'failed_photos',
        'email',
        'notify_when_ready',
        'error',
        'started_at',
        'completed_at',
        'notification_sent_at',
    ];

    protected $casts = [
        'generated_at' => 'datetime',
        'size' => 'integer',
        'total_photos' => 'integer',
        'processed_photos' => 'integer',
        'failed_photos' => 'integer',
        'notify_when_ready' => 'boolean',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'notification_sent_at' => 'datetime',
    ];

    public function gallery(): BelongsTo
    {
        return $this->belongsTo(Gallery::class);
    }
}
