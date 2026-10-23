<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AvailabilitySetting extends Model
{
    /**
     * Hours used for a weekday the photographer has not configured yet
     * (the same defaults the Availability page creates).
     */
    public const DEFAULT_START = '09:00:00';
    public const DEFAULT_END = '17:00:00';

    protected $fillable = [
        'user_id',
        'day_of_week',
        'start_time',
        'end_time',
        'is_active',
    ];

    protected $casts = [
        'day_of_week' => 'integer',
        'is_active' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
