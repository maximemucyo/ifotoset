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
    ];

    protected $casts = [
        'generated_at' => 'datetime',
        'size' => 'integer',
    ];

    public function gallery(): BelongsTo
    {
        return $this->belongsTo(Gallery::class);
    }
}
