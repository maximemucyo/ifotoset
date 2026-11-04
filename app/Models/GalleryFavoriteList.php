<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One client's favorites in a gallery, keyed by the email they gave when they first tapped the heart.
 */
class GalleryFavoriteList extends Model
{
    protected $fillable = [
        'gallery_id',
        'email',
        'submitted_at',
        'seen_at',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'seen_at' => 'datetime',
    ];

    public function gallery(): BelongsTo
    {
        return $this->belongsTo(Gallery::class);
    }

    public function favorites(): HasMany
    {
        return $this->hasMany(GalleryFavorite::class, 'list_id');
    }

    public function photos(): BelongsToMany
    {
        return $this->belongsToMany(Photo::class, 'gallery_favorites', 'list_id', 'photo_id');
    }

    /**
     * Picks changed after the client sent them.
     */
    public function changedSinceSubmitted(): bool
    {
        return $this->submitted_at !== null && $this->updated_at?->gt($this->submitted_at);
    }
}
