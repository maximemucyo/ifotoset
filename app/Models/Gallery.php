<?php

namespace App\Models;

use App\Casts\UuidBinaryCast;
use App\Support\ClientExports;
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

    /**
     * How photos are ordered in the gallery, keyed by the stored `photo_order` value.
     */
    public const PHOTO_ORDERS = [
        'taken' => 'Date taken',
        'filename' => 'File name',
        'uploaded' => 'Upload order',
    ];

    /**
     * Who can open a shared gallery, keyed by the stored `visibility` value. One vocabulary for every screen.
     */
    public const VISIBILITIES = [
        'public' => ['label' => 'Public', 'icon' => 'globe', 'description' => 'Anyone with the link.'],
        'password' => ['label' => 'Password', 'icon' => 'key', 'description' => 'Anyone with the link and the password.'],
        'private' => ['label' => 'Private', 'icon' => 'lock', 'description' => 'Only people you invite by email.'],
    ];

    /**
     * Client gallery layouts, keyed by the stored `template` value. Each starts with its own photo grid.
     */
    public const TEMPLATES = [
        'full' => ['name' => 'Full bleed', 'description' => 'The cover fills the screen, with the title on it.', 'grid' => 'masonry'],
        'framed' => ['name' => 'Framed', 'description' => 'The cover sits inside a margin, with the title below.', 'grid' => 'rows'],
        'split' => ['name' => 'Split', 'description' => 'The cover on one half, the title on the other.', 'grid' => 'masonry'],
        'none' => ['name' => 'No cover', 'description' => 'Just the title. Photos start right away.', 'grid' => 'square'],
    ];

    /**
     * How photos are laid out in the client gallery, keyed by the stored `grid_style` value.
     */
    public const GRID_STYLES = [
        'masonry' => 'Columns',
        'rows' => 'Rows',
        'square' => 'Squares',
    ];

    /**
     * Light or dark client gallery, keyed by the stored `color_mode` value, with the theme it starts in.
     */
    public const COLOR_MODES = [
        'light' => ['label' => 'Light', 'theme' => 'light'],
        'dark' => ['label' => 'Dark', 'theme' => 'dark'],
        'auto' => ['label' => 'Same as the phone', 'theme' => 'system'],
    ];

    protected $fillable = [
        'uuid',
        'user_id',
        'cover_photo_id',
        'has_explicit_cover',
        'cover_focal_x',
        'cover_focal_y',
        'title',
        'slug',
        'client_name',
        'client_id',
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
        'favorites_enabled',
        'favorites_limit',
        'photo_order',
        'download_pin',
        'password_hash',
        'password_hint',
        'invite_token',
        'expires_at',
        'published_at',
        'version',
        'featured_order',
        'portfolio_category',
        'template',
        'grid_style',
        'color_mode',
        'use_branding',
        'watermark',
    ];

    protected $attributes = [
        'photo_order' => 'taken',
        'template' => 'full',
        'grid_style' => 'masonry',
        'color_mode' => 'light',
        'favorites_enabled' => true,
        'use_branding' => false,
        'watermark' => false,
    ];

    protected static function booted(): void
    {
        // A focal point belongs to one photo: a new cover starts centred unless the same save places it
        static::updating(function (Gallery $gallery) {
            if ($gallery->isDirty('cover_photo_id') && !$gallery->isDirty(['cover_focal_x', 'cover_focal_y'])) {
                $gallery->cover_focal_x = null;
                $gallery->cover_focal_y = null;
            }
        });

        // Cached public pages (portfolio and galleries) show gallery details: clear them on change
        static::updated(fn (Gallery $gallery) => app(\App\Services\GalleryCachePurgeService::class)->queuePhotographer($gallery->user));
        static::deleted(fn (Gallery $gallery) => app(\App\Services\GalleryCachePurgeService::class)->queuePhotographer($gallery->user));

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
        'published_at' => 'datetime',
        'taken_down_at' => 'datetime',
        'restored_at' => 'datetime',
        'version' => 'integer',
        'featured_order' => 'integer',
        'has_explicit_cover' => 'boolean',
        'cover_focal_x' => 'float',
        'cover_focal_y' => 'float',
        'show_on_profile' => 'boolean',
        'allow_photo_downloads' => 'boolean',
        'allow_gallery_downloads' => 'boolean',
        'allow_google_photos' => 'boolean',
        'favorites_enabled' => 'boolean',
        'use_branding' => 'boolean',
        'watermark' => 'boolean',
        'favorites_limit' => 'integer',
    ];

    /**
     * Timezone a photographer's "Expires on" date is read in: the gallery closes at the end of that day there.
     */
    public static function expiryTimezone(?User $user): string
    {
        return $user?->timezone ?: config('app.schedule_timezone', 'UTC');
    }

    /**
     * End of the given Y-m-d day in the owner's timezone, as stored in `expires_at`; null when no date is given.
     */
    public static function expiryFromDate(?string $date, ?User $user): ?\Carbon\CarbonImmutable
    {
        if (blank($date)) {
            return null;
        }

        return \Carbon\CarbonImmutable::createFromFormat('Y-m-d', $date, self::expiryTimezone($user))
            ->endOfDay()
            ->utc();
    }

    /**
     * Drafts are being prepared: clients can't open them until the photographer shares them.
     * Only an explicit null counts, so a gallery created without the column set (the database
     * fills it in) is never mistaken for a draft before it is reloaded.
     */
    public function isDraft(): bool
    {
        return array_key_exists('published_at', $this->attributes) && $this->attributes['published_at'] === null;
    }

    public function scopePublished(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->whereNotNull('published_at');
    }

    /**
     * Where a gallery is in its life, as photographers think of it, keyed by what `stage()` returns.
     */
    public const STAGES = [
        'draft' => 'Draft',
        'shared' => 'Shared',
        'viewed' => 'Viewed',
        'downloaded' => 'Downloaded',
        'expired' => 'Expired',
    ];

    /**
     * Filters of the Galleries page: published galleries count as shared until they expire.
     */
    public function scopeLifecycle(\Illuminate\Database\Eloquent\Builder $query, string $filter): \Illuminate\Database\Eloquent\Builder
    {
        return match ($filter) {
            'drafts' => $query->whereNull('published_at'),
            'shared' => $query->whereNotNull('published_at')->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now())),
            'expiring' => $query->whereNotNull('published_at')->whereBetween('expires_at', [now(), now()->addDays(7)]),
            'expired' => $query->where('expires_at', '<=', now()),
            default => $query,
        };
    }

    /**
     * Adds `last_viewed_at`: when a client last opened the gallery. Used by `stage()` and `activitySummary()`.
     */
    public function scopeWithLastViewedAt(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        if ($query->getQuery()->columns === null) {
            $query->select('galleries.*');
        }

        return $query->selectSub(
            \Illuminate\Support\Facades\DB::table('activity_logs')
                ->selectRaw('MAX(created_at)')
                ->whereColumn('activity_logs.gallery_id', 'galleries.id')
                ->where('event', 'gallery_viewed'),
            'last_viewed_at'
        );
    }

    public function lastViewedAt(): ?\Illuminate\Support\Carbon
    {
        $value = $this->attributes['last_viewed_at'] ?? null;

        return $value ? \Illuminate\Support\Carbon::parse($value) : null;
    }

    /**
     * A key of STAGES. Viewed and Downloaded need `withLastViewedAt()` and the stats relation.
     */
    public function stage(): string
    {
        return match (true) {
            $this->expires_at !== null && $this->expires_at->isPast() => 'expired',
            $this->isDraft() => 'draft',
            ($this->stats?->downloads_count ?? 0) > 0 => 'downloaded',
            $this->lastViewedAt() !== null => 'viewed',
            default => 'shared',
        };
    }

    /**
     * What clients did, in a few words: "Viewed 2 hours ago, 48 favorites, 12 downloads".
     */
    public function activitySummary(): string
    {
        $favorites = $this->stats?->favorites_count ?? 0;
        $downloads = $this->stats?->downloads_count ?? 0;
        $parts = array_filter([
            ($viewed = $this->lastViewedAt()) ? 'Viewed ' . $viewed->diffForHumans() : null,
            $favorites > 0 ? number_format($favorites) . ' ' . \Illuminate\Support\Str::plural('favorite', $favorites) : null,
            $downloads > 0 ? number_format($downloads) . ' ' . \Illuminate\Support\Str::plural('download', $downloads) : null,
        ]);

        return match (true) {
            $parts !== [] => ucfirst(implode(', ', $parts)),
            $this->isDraft() => 'Not shared yet',
            default => 'Not opened yet',
        };
    }

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
            ->whereNotNull('published_at')
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
     * Anyone holding the link can open this gallery without a PIN or invitation.
     * Wider than search indexing: unlisted client galleries qualify too.
     */
    public function hasPublicLink(): bool
    {
        return !$this->trashed()
            && !$this->isDraft()
            && $this->visibility === 'public'
            && empty($this->password_hash)
            && ($this->expires_at === null || $this->expires_at->isFuture())
            && $this->moderation_status !== 'taken_down';
    }

    /**
     * Determine whether this gallery is eligible for public search indexing.
     * Decoupled from user session authorization.
     */
    public function isPubliclyIndexable(): bool
    {
        if ($this->trashed() || $this->isDraft() || $this->visibility !== 'public') {
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
            ->whereNotNull('published_at')
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
     * "Save to Google Photos" needs both the gallery setting and the platform switch (Admin → Settings → Client exports).
     */
    public function googlePhotosExportEnabled(): bool
    {
        return $this->allow_google_photos && ClientExports::googlePhotosEnabledFor($this->user) && !$this->clientDownloadsPaused();
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
        return $this->allow_gallery_downloads && ClientExports::zipEnabled() && !$this->clientDownloadsPaused();
    }

    /**
     * Downloads stay behind a separate PIN the client enters on top of gallery access.
     */
    public function requiresDownloadPin(): bool
    {
        return !empty($this->download_pin);
    }

    /**
     * Columns for the studio's download setting: clients can always download single photos and the whole
     * gallery, and the photographer's only choice is whether that asks for a PIN.
     */
    public static function downloadAttributes(?string $pin): array
    {
        return [
            'allow_photo_downloads' => true,
            'allow_gallery_downloads' => true,
            'download_pin' => $pin,
        ];
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

    /**
     * CSS object-position that keeps the cover's subject in frame at any crop.
     */
    /**
     * What each Settings section is set to, as shown beside it in the gallery workspace.
     *
     * @return array{details: string, privacy: string, expiry: string, downloads: string, favorites: string}
     */
    public function settingsSummary(): array
    {
        return [
            'details' => ($this->client?->name ?: $this->client_name) ?: 'No client',
            'privacy' => (self::VISIBILITIES[$this->visibility] ?? self::VISIBILITIES['private'])['label'],
            'expiry' => $this->expires_at?->copy()->setTimezone(self::expiryTimezone($this->user))->format('M j, Y') ?? 'Never',
            'downloads' => $this->requiresDownloadPin() ? 'PIN ' . $this->download_pin : 'No PIN',
            'watermark' => $this->watermark ? 'On' : 'Off',
            'favorites' => match (true) {
                !$this->favorites_enabled => 'Off',
                (bool) $this->favorites_limit => 'Up to ' . $this->favorites_limit,
                default => 'On',
            },
        ];
    }

    /**
     * The client gallery's look, with unknown stored values read as the defaults.
     *
     * @return array{template: string, grid: string, mode: string}
     */
    public function design(): array
    {
        return [
            'template' => isset(self::TEMPLATES[$this->template]) ? $this->template : 'full',
            'grid' => isset(self::GRID_STYLES[$this->grid_style]) ? $this->grid_style : 'masonry',
            'mode' => isset(self::COLOR_MODES[$this->color_mode]) ? $this->color_mode : 'light',
        ];
    }

    /**
     * The studio's portfolio look (logo, colour, title font) when this gallery uses it, otherwise null.
     */
    public function brand(): ?\App\Support\Portfolio\PortfolioSettings
    {
        return $this->use_branding && $this->user ? $this->user->portfolio() : null;
    }

    public function coverObjectPosition(?float $x = null, ?float $y = null): string
    {
        $x ??= $this->cover_focal_x ?? 0.5;
        $y ??= $this->cover_focal_y ?? 0.5;

        return sprintf('%s%% %s%%', round($x * 100, 1), round($y * 100, 1));
    }

    public function getCoverUrlAttribute(): ?string
    {
        return $this->getCoverUrl('md');
    }

    /**
     * Parts of the gallery such as "Ceremony" and "Reception", in the photographer's order.
     */
    public function sets(): HasMany
    {
        return $this->hasMany(GallerySet::class)->orderBy('sort_order')->orderBy('id');
    }

    public function photos(): HasMany
    {
        return $this->hasMany(Photo::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * Each client's favorites, one list per email.
     */
    public function favoriteLists(): HasMany
    {
        return $this->hasMany(GalleryFavoriteList::class);
    }

    /**
     * Quick share links to chosen photos, newest first.
     */
    public function shareLinks(): HasMany
    {
        return $this->hasMany(GalleryShareLink::class)->latest('id');
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(GalleryInvitation::class);
    }
}
?>
