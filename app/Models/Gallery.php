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

    /**
     * Portfolio pages on {username}.{root}/ that a gallery slug must never take.
     */
    public const RESERVED_SLUGS = ['book', 'about', 'contact'];

    protected $fillable = [
        'uuid',
        'user_id',
        'cover_photo_id',
        'title',
        'slug',
        'client_name',
        'event_date',
        'visibility',
        'moderation_status',
        'taken_down_at',
        'taken_down_by',
        'takedown_reason',
        'restored_at',
        'restored_by',
        'show_on_profile',
        'allow_photo_downloads',
        'allow_gallery_downloads',
        'allow_google_photos',
        'download_pin',
        'password_hash',
        'password_hint',
        'invite_token',
        'expires_at',
        'version',
        'featured_order',
        'portfolio_category',
    ];

    protected static function booted(): void
    {
        // Privacy decides the storage prefix (and so whether the CDN demands a token): move media when it flips.
        static::updated(function (Gallery $gallery) {
            if (!$gallery->wasChanged(['visibility', 'password_hash'])) {
                return;
            }

            $wasProtected = $gallery->getOriginal('visibility') !== 'public' || !empty($gallery->getOriginal('password_hash'));
            if ($wasProtected !== $gallery->hasProtectedMedia()) {
                \App\Jobs\RelocateGalleryMediaJob::dispatch($gallery->id)->afterCommit();
            }
        });
    }

    protected $casts = [
        'uuid' => UuidBinaryCast::class,
        'event_date' => 'date',
        'expires_at' => 'datetime',
        'taken_down_at' => 'datetime',
        'restored_at' => 'datetime',
        'version' => 'integer',
        'featured_order' => 'integer',
        'show_on_profile' => 'boolean',
        'allow_photo_downloads' => 'boolean',
        'allow_gallery_downloads' => 'boolean',
        'allow_google_photos' => 'boolean',
    ];

    public function isTakenDown(): bool
    {
        return $this->moderation_status === 'taken_down';
    }

    public function takenDownBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'taken_down_by');
    }

    public function restoredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'restored_by');
    }

    /**
     * Scope to galleries shown on the photographer's public profile.
     * Only unexpired public galleries with show_on_profile = true and not taken down qualify.
     */
    public function scopeShownOnProfile(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('visibility', 'public')
            ->where('moderation_status', '!=', 'taken_down')
            ->where('show_on_profile', true)
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            });
    }

    /**
     * Scope to galleries explicitly featured on the photographer's public profile.
     */
    public function scopeFeatured(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->whereNotNull('featured_order')->orderBy('featured_order', 'asc');
    }

    /**
     * Order the photographer chose in the portfolio builder; galleries never sorted follow, newest first.
     */
    public function scopePortfolioOrder(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->orderByRaw('featured_order IS NULL')
            ->orderBy('featured_order')
            ->orderByDesc('created_at');
    }

    /**
     * Determine whether this gallery is eligible for public search indexing.
     * Decoupled from user session authorization.
     */
    public function isPubliclyIndexable(): bool
    {
        if ($this->trashed() || $this->visibility !== 'public') {
            return false;
        }

        if (!empty($this->password_hash)) {
            return false;
        }

        if ($this->expires_at !== null && $this->expires_at->isPast()) {
            return false;
        }

        if ($this->moderation_status === 'taken_down') {
            return false;
        }

        // Unlisted client galleries (not shown on the profile) stay out of search
        if (!$this->show_on_profile) {
            return false;
        }

        if (!$this->user || !$this->user->isPubliclyIndexable()) {
            return false;
        }

        return true;
    }

    /**
     * Scope query to publicly indexable galleries.
     */
    public function scopePubliclyIndexable(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('visibility', 'public')
            ->where('moderation_status', '!=', 'taken_down')
            ->where(function ($q) {
                $q->whereNull('password_hash')->orWhere('password_hash', '');
            })
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->where('show_on_profile', true)
            ->whereHas('user', fn ($q) => $q->publiclyIndexable());
    }

    /**
     * "Save to Google Photos" needs both the gallery setting and the platform switch (GOOGLE_PHOTOS_EXPORT).
     */
    public function googlePhotosExportEnabled(): bool
    {
        return $this->allow_google_photos && config('services.google.photos_export') && !$this->clientDownloadsPaused();
    }

    /**
     * Client downloads pause while the owner's Free-plan account is over quota (config/billing.php).
     */
    public function clientDownloadsPaused(): bool
    {
        return (bool) $this->user?->overQuotaStage()?->pausesClientDownloads();
    }

    public function photoDownloadsAllowed(): bool
    {
        return $this->allow_photo_downloads && !$this->clientDownloadsPaused();
    }

    public function galleryDownloadsAllowed(): bool
    {
        return $this->allow_gallery_downloads && !$this->clientDownloadsPaused();
    }

    /**
     * Downloads stay behind a separate PIN the client enters on top of gallery access.
     */
    public function requiresDownloadPin(): bool
    {
        return !empty($this->download_pin);
    }

    /**
     * PIN-protected and private galleries keep their media under the token-only `protected-galleries/` prefix.
     */
    public function hasProtectedMedia(): bool
    {
        return $this->visibility !== 'public' || !empty($this->password_hash);
    }

    public function mediaPrefix(?bool $protected = null): string
    {
        $protected ??= $this->hasProtectedMedia();

        return ($protected ? 'protected-galleries/' : 'galleries/') . $this->uuid;
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

    public function getPublicPhotoCountAttribute(): int
    {
        $hiddenCount = $this->photos()->where('is_hidden', true)->count();
        if ($hiddenCount > 0) {
            return max(0, $this->photo_count - $hiddenCount);
        }
        return $this->photo_count;
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
        if ($this->coverPhoto && !$this->coverPhoto->is_hidden) {
            return $this->coverPhoto->getUrl($size);
        }
        return null;
    }

    /**
     * Responsive srcset for the cover photo (null when there is no usable cover).
     */
    public function getCoverSrcset(array $sizes = ['md', 'lg', 'xl']): ?string
    {
        if ($this->coverPhoto && !$this->coverPhoto->is_hidden) {
            return $this->coverPhoto->getSrcset($sizes);
        }
        return null;
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
