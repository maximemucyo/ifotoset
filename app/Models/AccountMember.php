<?php

namespace App\Models;

use App\Enums\AccountRole;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Links a member's login to the studio account (the owner's user row) they work in.
 */
class AccountMember extends Model
{
    protected $fillable = [
        'account_id',
        'user_id',
        'role',
        'invited_by',
        'suspended_at',
    ];

    protected $casts = [
        'role' => AccountRole::class,
        'suspended_at' => 'datetime',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(User::class, 'account_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('suspended_at');
    }

    public function isSuspended(): bool
    {
        return $this->suspended_at !== null;
    }
}
