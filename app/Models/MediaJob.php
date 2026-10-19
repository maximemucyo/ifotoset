<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MediaJob extends Model
{
    protected $fillable = [
        'photo_id',
        'job_name',
        'job_uuid',
        'job_type',
        'queue',
        'status',
        'attempts',
        'max_attempts',
        'progress',
        'started_at',
        'completed_at',
        'failed_at',
        'duration_ms',
        'error_message',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'failed_at' => 'datetime',
        'attempts' => 'integer',
        'max_attempts' => 'integer',
        'duration_ms' => 'integer',
    ];

    public function photo(): BelongsTo
    {
        return $this->belongsTo(Photo::class);
    }
}
