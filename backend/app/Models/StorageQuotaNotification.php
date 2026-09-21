<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StorageQuotaNotification extends Model
{
    protected $table = 'storage_quota_notifications';

    protected $fillable = [
        'user_id',
        'threshold',
        'generation',
        'usage_bytes',
        'limit_bytes',
        'status',
        'sent_at',
    ];

    protected $casts = [
        'threshold' => 'integer',
        'generation' => 'integer',
        'usage_bytes' => 'integer',
        'limit_bytes' => 'integer',
        'sent_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
