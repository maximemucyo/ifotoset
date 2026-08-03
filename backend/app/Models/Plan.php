<?php

namespace App\Models;

use App\Casts\UuidBinaryCast;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    protected $fillable = [
        'uuid',
        'slug',
        'name',
        'monthly_price',
        'annual_price',
        'currency',
        'storage_limit',
        'video_limit',
        'gallery_limit',
        'team_limit',
    ];

    protected $casts = [
        'uuid' => UuidBinaryCast::class,
        'monthly_price' => 'decimal:2',
        'annual_price' => 'decimal:2',
        'storage_limit' => 'integer',
        'video_limit' => 'integer',
        'gallery_limit' => 'integer',
        'team_limit' => 'integer',
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
?>
