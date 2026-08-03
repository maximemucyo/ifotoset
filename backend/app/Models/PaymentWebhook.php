<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentWebhook extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'provider',
        'event_id',
        'headers',
        'payload',
        'received_at',
    ];

    protected $casts = [
        'headers' => 'array',
        'payload' => 'array',
        'received_at' => 'datetime',
    ];
}
?>
