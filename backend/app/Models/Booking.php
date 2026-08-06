<?php

namespace App\Models;

use App\Casts\UuidBinaryCast;
use App\Enums\BookingStatus;
use App\Traits\HasBinaryUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Booking extends Model
{
    use SoftDeletes, HasBinaryUuid;

    protected $fillable = [
        'uuid',
        'user_id',
        'client_id',
        'package_id',
        'title',
        'starts_at',
        'ends_at',
        'timezone',
        'location',
        'status',
        'price',
        'currency',
        'notes',
    ];

    protected $casts = [
        'uuid' => UuidBinaryCast::class,
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'status' => BookingStatus::class,
        'price' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }
}
