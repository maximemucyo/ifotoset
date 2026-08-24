<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GooglePhotoCredential extends Model
{
    protected $fillable = [
        'sync_id',
        'access_token',
        'refresh_token',
        'expires_at',
    ];

    protected $casts = [
        'access_token' => 'encrypted',
        'refresh_token' => 'encrypted',
        'expires_at' => 'datetime',
    ];

    public function sync(): BelongsTo
    {
        return $this->belongsTo(GooglePhotoSync::class, 'sync_id');
    }
}
