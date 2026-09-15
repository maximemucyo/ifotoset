<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentWebhook extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'provider',
        'event_id',
        'deposit_id',
        'signature',
        'payload_hash',
        'headers',
        'payload',
        'processing_status',
        'received_at',
        'processed_at',
    ];

    protected $casts = [
        'headers' => 'array',
        'payload' => 'array',
        'received_at' => 'datetime',
        'processed_at' => 'datetime',
    ];
}
?>
