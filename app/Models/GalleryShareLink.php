<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

/**
 * A quick share link: a few chosen photos from a gallery, opened with the link alone.
 */
class GalleryShareLink extends Model
{
    public const MAX_PHOTOS = 500;

    protected $fillable = [
        'gallery_id',
        'token',
        'created_by',
    ];

    protected $casts = [
        'views_count' => 'integer',
        'last_viewed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(fn (GalleryShareLink $link) => $link->token ??= Str::random(24));
    }

    public function gallery(): BelongsTo
    {
        return $this->belongsTo(Gallery::class);
    }

    public function photos(): BelongsToMany
    {
        return $this->belongsToMany(Photo::class, 'gallery_share_link_photos', 'share_link_id', 'photo_id');
    }

    public function url(): string
    {
        return route('share-link.show', $this->token);
    }

    /**
     * The link stops with the gallery: trashed, taken down or expired. A draft still opens, since the photographer
     * chose these photos to send.
     */
    public function isOpen(): bool
    {
        $gallery = $this->gallery;

        return $gallery !== null
            && !$gallery->trashed()
            && !$gallery->isTakenDown()
            && !($gallery->expires_at?->isPast() ?? false)
            && (bool) $gallery->user?->is_active;
    }
}
