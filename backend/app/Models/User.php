<?php

namespace App\Models;

use App\Casts\UuidBinaryCast;
use App\Traits\HasBinaryUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Contracts\Auth\MustVerifyEmail;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes, HasBinaryUuid;

    protected $fillable = [
        'uuid',
        'plan_id',
        'name',
        'email',
        'password',
        'email_verified_at',
        'storage_used_bytes',
        'storage_reserved_bytes',
        'storage_warning_75_active',
        'storage_warning_100_active',
        'storage_warning_generation',
        'role',
        'is_active',
        'username',
        'phone',
        'location',
        'website',
        'bio',
        'avatar_path',
        'notification_preferences',
        'timezone',
        'slot_interval_minutes',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'uuid' => UuidBinaryCast::class,
        'email_verified_at' => 'datetime',
        'is_active' => 'boolean',
        'notification_preferences' => 'array',
        'storage_used_bytes' => 'integer',
        'storage_reserved_bytes' => 'integer',
        'storage_warning_75_active' => 'boolean',
        'storage_warning_100_active' => 'boolean',
        'storage_warning_generation' => 'integer',
    ];

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isFree(): bool
    {
        return ! $this->plan_id || ! $this->plan || $this->plan->slug === 'free';
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function galleries(): HasMany
    {
        return $this->hasMany(Gallery::class);
    }

    public function clients(): HasMany
    {
        return $this->hasMany(Client::class);
    }

    public function packages(): HasMany
    {
        return $this->hasMany(Package::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function clearAvailabilityCache(): void
    {
        $key = "availability-version:photographer_id:{$this->id}";
        if (\Illuminate\Support\Facades\Cache::has($key)) {
            \Illuminate\Support\Facades\Cache::increment($key);
        } else {
            \Illuminate\Support\Facades\Cache::put($key, 2, now()->addDays(30));
        }
    }

    /**
     * Send the password reset notification.
     *
     * @param  string  $token
     * @return void
     */
    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new \App\Notifications\QueuedResetPassword($token));
    }

    /**
     * Send the email verification notification.
     *
     * @return void
     */
    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new \App\Notifications\QueuedVerifyEmail());
    }

    public function getAvatarUrlAttribute(): ?string
    {
        if (!$this->avatar_path) {
            return null;
        }
        $cdnDomain = config('filesystems.disks.b2.cdn_domain', 'cdn.ifotoset.com');
        return "https://{$cdnDomain}/" . ltrim($this->avatar_path, '/');
    }

    public function getPublicUrlAttribute(): string
    {
        return app(\App\Services\PublicUrlService::class)->photographerUrl($this->username);
    }
}
?>
