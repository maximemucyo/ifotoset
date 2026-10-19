<?php

namespace App\Models;

use App\Casts\UuidBinaryCast;
use App\Traits\HasBinaryUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AvailabilityException extends Model
{
    use HasBinaryUuid;

    protected $fillable = [
        'uuid',
        'user_id',
        'date',
        'start_time',
        'end_time',
        'is_closed',
    ];

    protected $casts = [
        'uuid' => UuidBinaryCast::class,
        'date' => 'date',
        'is_closed' => 'boolean',
    ];

    protected static function booted()
    {
        static::saving(function (AvailabilityException $exception) {
            if ($exception->is_closed) {
                $exception->start_time = null;
                $exception->end_time = null;
            } else {
                if (empty($exception->start_time) || empty($exception->end_time)) {
                    throw new \InvalidArgumentException("Active availability exceptions require both start and end times.");
                }
                if ($exception->start_time >= $exception->end_time) {
                    throw new \InvalidArgumentException("Exception start time must be before end time.");
                }
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
