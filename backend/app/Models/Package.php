<?php

namespace App\Models;

use App\Casts\UuidBinaryCast;
use App\Traits\HasBinaryUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Package extends Model
{
    use SoftDeletes, HasBinaryUuid;

    protected $fillable = [
        'uuid',
        'user_id',
        'name',
        'description',
        'price',
        'currency',
        'duration_minutes',
        'deliverables',
        'sort_order',
        'is_active',
        'deposit_type',
        'deposit_amount',
    ];

    protected $casts = [
        'uuid' => UuidBinaryCast::class,
        'price' => 'decimal:2',
        'deposit_amount' => 'decimal:2',
        'duration_minutes' => 'integer',
        'deliverables' => 'array',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    /**
     * Compute the actual deposit amount in the package's currency.
     * Returns null if no deposit is required.
     */
    public function computedDepositAmount(): ?float
    {
        return match ($this->deposit_type) {
            'fixed' => $this->deposit_amount ? (float) $this->deposit_amount : null,
            'percentage' => $this->deposit_amount
                ? round((float) $this->price * ((float) $this->deposit_amount / 100), 2)
                : null,
            default => null,
        };
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
