<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GalleryInvitation extends Model
{
    protected $fillable = [
        'gallery_id',
        'email',
        'token',
        'invited_by',
        'accepted_at',
        'expires_at',
        'revoked_at',
    ];

    protected $casts = [
        'accepted_at' => 'datetime',
        'expires_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    public function gallery(): BelongsTo
    {
        return $this->belongsTo(Gallery::class);
    }

    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }
}
