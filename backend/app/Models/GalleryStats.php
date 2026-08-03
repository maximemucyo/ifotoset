<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GalleryStats extends Model
{
    protected $primaryKey = 'gallery_id';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'gallery_id',
        'photo_count',
        'video_count',
        'downloads_count',
        'favorites_count',
        'total_bytes',
        'updated_at',
    ];

    protected $casts = [
        'gallery_id' => 'integer',
        'photo_count' => 'integer',
        'video_count' => 'integer',
        'downloads_count' => 'integer',
        'favorites_count' => 'integer',
        'total_bytes' => 'integer',
        'updated_at' => 'datetime',
    ];

    public function gallery(): BelongsTo
    {
        return $this->belongsTo(Gallery::class);
    }
}
?>
