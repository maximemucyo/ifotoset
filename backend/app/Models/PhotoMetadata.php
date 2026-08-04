<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PhotoMetadata extends Model
{
    protected $table = 'photo_metadata';
    
    protected $primaryKey = 'photo_id';
    
    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'photo_id',
        'camera',
        'lens',
        'iso',
        'shutter_speed',
        'aperture',
        'focal_length',
        'flash',
        'orientation',
        'gps_latitude',
        'gps_longitude',
    ];

    public function photo(): BelongsTo
    {
        return $this->belongsTo(Photo::class);
    }
}
