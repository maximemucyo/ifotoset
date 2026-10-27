<?php

namespace App\Models;

use App\Casts\UuidBinaryCast;
use App\Enums\AccountRole;
use App\Traits\HasBinaryUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An emailed invitation to join a studio account. Only a SHA-256 hash of the token is stored.
 */
class AccountInvitation extends Model
{
    use HasBinaryUuid;

    public const EXPIRES_AFTER_DAYS = 7;

    protected $fillable = [
        'uuid',
        'account_id',
        'email',
        'role',
        'token_hash',
        'invited_by',
        'expires_at',
        'accepted_at',
        'revoked_at',
    ];

    protected $casts = [
        'uuid' => UuidBinaryCast::class,
        'role' => AccountRole::class,
        'expires_at' => 'datetime',
        'accepted_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    public static function findByToken(string $token): ?self
    {
        return static::where('token_hash', self::hashToken($token))->first();
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(User::class, 'account_id');
    }

    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    /**
     * Not yet accepted, revoked or expired.
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->whereNull('accepted_at')
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now());
    }

    public function isPending(): bool
    {
        return !$this->accepted_at && !$this->revoked_at && $this->expires_at->isFuture();
    }
}
