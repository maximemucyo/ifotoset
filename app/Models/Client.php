<?php

namespace App\Models;

use App\Casts\UuidBinaryCast;
use App\Traits\HasBinaryUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Client extends Model
{
    use SoftDeletes, HasBinaryUuid;

    protected $fillable = [
        'uuid',
        'user_id',
        'name',
        'email',
        'phone',
        'company_name',
        'location',
        'instagram',
        'notes',
        'tags',
        'last_contacted_at',
    ];

    protected $casts = [
        'uuid' => UuidBinaryCast::class,
        'tags' => 'array',
        'last_contacted_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }
}
