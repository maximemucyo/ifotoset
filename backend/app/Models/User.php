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

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes, HasBinaryUuid;

    protected $fillable = [
        'uuid',
        'plan_id',
        'name',
        'email',
        'password',
        'storage_used_bytes',
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
    ];

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
}
?>
