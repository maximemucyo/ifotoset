<?php

namespace App\Models;

use App\Casts\UuidBinaryCast;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    protected $fillable = [
        'uuid',
        'user_id',
        'plan_id',
        'amount',
        'currency',
        'phone_number',
        'provider',
        'idempotency_key',
        'pawapay_deposit_id',
        'status',
        'error_message',
    ];

    protected $casts = [
        'uuid' => UuidBinaryCast::class,
        'amount' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }
}
?>
