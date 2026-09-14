<?php

namespace App\Models;

use App\Casts\UuidBinaryCast;
use App\Traits\HasBinaryUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Gallery extends Model
{
    use SoftDeletes, HasBinaryUuid;


    protected $fillable = [
        'uuid',
        'user_id',
        'cover_photo_id',
        'title',
        'slug',
        'client_name',
        'event_date',
        'visibility',
        'allow_photo_downloads',
        'allow_gallery_downloads',
        'allow_google_photos',
        'password_hash',
        'password_hint',
        'invite_token',
        'expires_at',
        'version',
        'featured_order',
    ];

    protected $casts = [
        'uuid' => UuidBinaryCast::class,
        'event_date' => 'date',
        'expires_at' => 'datetime',
        'version' => 'integer',
        'featured_order' => 'integer',
        'allow_photo_downloads' => 'boolean',
        'allow_gallery_downloads' => 'boolean',
        'allow_google_photos' => 'boolean',
    ];

    /**
     * Scope to galleries explicitly featured on the photographer's public profile.
     */
    public function scopeFeatured(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->whereNotNull('featured_order')->orderBy('featured_order', 'asc');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getPublicUrlAttribute(): string
    {
        return app(\App\Services\PublicUrlService::class)->galleryUrl($this->user->username, $this->slug);
    }

    public function stats(): HasOne
    {
        return $this->hasOne(GalleryStats::class, 'gallery_id');
    }

    public function getPhotoCountAttribute(): int
    {
        if (isset($this->attributes['photos_count'])) {
            return (int) $this->attributes['photos_count'];
        }
        return (int) ($this->stats?->photo_count ?? 0);
    }

    public function getPhotosCountAttribute(): int
    {
        return $this->photo_count;
    }

    public function coverPhoto(): BelongsTo
    {
        return $this->belongsTo(Photo::class, 'cover_photo_id');
    }

    public function getCoverUrl(?string $size = 'md'): ?string
    {
        return $this->coverPhoto?->getUrl($size);
    }

    public function getCoverUrlAttribute(): ?string
    {
        return $this->getCoverUrl('md');
    }

    public function photos(): HasMany
    {
        return $this->hasMany(Photo::class);
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(GalleryInvitation::class);
    }
}
?>
