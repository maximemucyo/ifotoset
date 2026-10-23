<?php

namespace App\Models;

use App\Casts\UuidBinaryCast;
use App\Traits\HasBinaryUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;

class Plan extends Model
{
    use HasBinaryUuid;

    /**
     * Cache key for the plans shown on the public landing page.
     */
    public const LANDING_CACHE_KEY = 'landing_plans';

    protected $fillable = [
        'uuid',
        'slug',
        'name',
        'monthly_price',
        'annual_price',
        'currency',
        'storage_limit',
        'video_limit',
        'video_limit_seconds',
        'max_video_size_bytes',
        'max_single_video_duration_seconds',
        'gallery_limit',
        'team_limit',
    ];

    protected $casts = [
        'uuid' => UuidBinaryCast::class,
        'monthly_price' => 'decimal:2',
        'annual_price' => 'decimal:2',
        'storage_limit' => 'integer',
        'video_limit' => 'integer',
        'video_limit_seconds' => 'integer',
        'max_video_size_bytes' => 'integer',
        'max_single_video_duration_seconds' => 'integer',
        'gallery_limit' => 'integer',
        'team_limit' => 'integer',
    ];

    protected static function booted(): void
    {
        // Keep the landing page pricing in sync with admin edits.
        static::saved(fn () => Cache::forget(self::LANDING_CACHE_KEY));
        static::deleted(fn () => Cache::forget(self::LANDING_CACHE_KEY));
    }

    public function getVideoLimitSecondsAttribute(): int
    {
        return (int) ($this->attributes['video_limit_seconds'] ?? $this->attributes['video_limit'] ?? 0);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function features(): BelongsToMany
    {
        return $this->belongsToMany(Feature::class, 'plan_features');
    }

    /**
     * Feature codes on this plan, cached briefly (the plan/feature matrix rarely changes).
     *
     * @return array<int, string>
     */
    public function featureCodes(): array
    {
        return Cache::remember("plan_features:{$this->id}", now()->addMinutes(10), fn () => $this->features()->pluck('code')->all());
    }
}
?>
