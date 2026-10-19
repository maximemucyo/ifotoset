<?php

namespace App\Models;

use App\Casts\UuidBinaryCast;
use App\Traits\HasBinaryUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use HasBinaryUuid;
    protected $fillable = [
        'uuid',
        'user_id',
        'plan_id',
        'booking_id',
        'purpose',
        'billing_cycle',
        'amount',
        'currency',
        'phone_number',
        'provider',
        'idempotency_key',
        'pawapay_deposit_id',
        'provider_transaction_id',
        'status',
        'provider_status',
        'error_message',
        'failure_reason',
        'paid_at',
        'metadata',
    ];

    protected $casts = [
        'uuid' => UuidBinaryCast::class,
        'amount' => 'decimal:2',
        'paid_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    /**
     * Scope to payments safe to expose publicly (booking deposits only).
     */
    public function scopePubliclyAccessible(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('purpose', 'booking_deposit');
    }
}
