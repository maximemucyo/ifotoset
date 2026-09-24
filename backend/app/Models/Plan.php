<?php

namespace App\Models;

use App\Casts\UuidBinaryCast;
use App\Traits\HasBinaryUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    use HasBinaryUuid;
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

    public function getVideoLimitSecondsAttribute(): int
    {
        return (int) ($this->attributes['video_limit_seconds'] ?? $this->attributes['video_limit'] ?? 0);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
?>
