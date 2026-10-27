<?php

namespace App\Models;

use App\Casts\UuidBinaryCast;
use App\Enums\OverQuotaStage;
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

    // role, plan_id and is_active are deliberately not fillable: set them
    // explicitly (forceFill / attribute assignment) in the admin and billing actions.
    protected $fillable = [
        'uuid',
        'name',
        'email',
        'password',
        'email_verified_at',
        'storage_used_bytes',
        'storage_reserved_bytes',
        'video_seconds_used',
        'video_seconds_reserved',
        'storage_warning_75_active',
        'storage_warning_100_active',
        'storage_warning_generation',
        'username',
        'profile_public',
        'phone',
        'location',
        'specialties',
        'website',
        'bio',
        'avatar_path',
        'portfolio_settings',
        'logo_path',
        'cover_path',
        'share_image_path',
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
        'purged_at' => 'datetime',
        'profile_public' => 'boolean',
        'notification_preferences' => 'array',
        'portfolio_settings' => 'array',
        'specialties' => 'array',
        'storage_used_bytes' => 'integer',
        'storage_reserved_bytes' => 'integer',
        'video_seconds_used' => 'integer',
        'video_seconds_reserved' => 'integer',
        'storage_warning_75_active' => 'boolean',
        'storage_warning_100_active' => 'boolean',
        'storage_warning_generation' => 'integer',
        'over_quota_since' => 'datetime',
        'phone_prompt_dismissed_at' => 'datetime',
    ];

    /**
     * Display name of the directory city matched from the location, e.g. "Rubavu".
     */
    public function cityName(): ?string
    {
        return \App\Support\Directory::cityName($this->city);
    }

    /**
     * Labels of the chosen specialties, in the order the directory lists them.
     *
     * @return array<int, string>
     */
    public function specialtyLabels(): array
    {
        $chosen = $this->specialties ?? [];

        return array_values(array_filter(
            config('directory.specialties'),
            fn (string $slug) => in_array($slug, $chosen, true),
            ARRAY_FILTER_USE_KEY,
        ));
    }

    protected static function booted(): void
    {
        // The directory city is derived from the typed location, never entered directly
        static::saving(function (User $user) {
            if ($user->isDirty('location')) {
                $user->city = \App\Support\Directory::matchCity($user->location);
            }
        });
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isFree(): bool
    {
        return ! $this->plan_id || ! $this->plan || $this->plan->slug === 'free';
    }

    /**
     * True when the account is on the Free plan and stores more than its limit.
     */
    public function isOverFreeQuota(): bool
    {
        $limit = $this->plan?->storage_limit;

        return $this->plan?->slug === 'free'
            && $limit !== null && $limit > 0
            && (int) $this->storage_used_bytes > $limit;
    }

    /**
     * Over-quota timeline stage, or null once the account has upgraded or freed up space.
     */
    public function overQuotaStage(): ?OverQuotaStage
    {
        if (!$this->over_quota_since || !$this->isOverFreeQuota()) {
            return null;
        }

        return OverQuotaStage::since($this->over_quota_since);
    }

    public function hasVideoSupport(): bool
    {
        return ($this->plan?->video_limit_seconds ?? $this->plan?->video_limit ?? 0) > 0;
    }

    public function getAvailableVideoSeconds(): int
    {
        $limit = (int) ($this->plan?->video_limit_seconds ?? $this->plan?->video_limit ?? 0);
        $totalCommittedAndReserved = (int) ($this->video_seconds_used + $this->video_seconds_reserved);
        return max(0, $limit - $totalCommittedAndReserved);
    }

    public function getVideoUsageFormatted(): string
    {
        $usedMinutes = round($this->video_seconds_used / 60);
        $limitSeconds = (int) ($this->plan?->video_limit_seconds ?? $this->plan?->video_limit ?? 0);
        $limitMinutes = round($limitSeconds / 60);

        if ($limitMinutes >= 60) {
            $usedHours = round($this->video_seconds_used / 3600, 1);
            $limitHours = round($limitSeconds / 3600);
            return "{$usedHours}h / {$limitHours}h";
        }

        return "{$usedMinutes}m / {$limitMinutes}m";
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /**
     * Whether the user's plan includes a feature (see FeatureSeeder for codes).
     */
    public function hasFeature(string $code): bool
    {
        return $this->plan !== null && in_array($code, $this->plan->featureCodes(), true);
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

    public function adminNotes(): HasMany
    {
        return $this->hasMany(AdminNote::class, 'user_id');
    }

    /**
     * People working in this user's studio (as its owner).
     */
    public function teamMembers(): HasMany
    {
        return $this->hasMany(AccountMember::class, 'account_id');
    }

    public function teamInvitations(): HasMany
    {
        return $this->hasMany(AccountInvitation::class, 'account_id');
    }

    /**
     * Studios this user works in as a member.
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(AccountMember::class, 'user_id');
    }

    /**
     * Whether this user has a studio of their own. Members who joined through
     * an invite have no username, and so no public studio, until they create one.
     */
    public function hasOwnStudio(): bool
    {
        return filled($this->username);
    }

    /**
     * Whether to ask this studio owner for a phone number. Closing the prompt hides it for a week.
     */
    public function shouldPromptForPhone(): bool
    {
        return $this->hasOwnStudio()
            && blank($this->phone)
            && (!$this->phone_prompt_dismissed_at || $this->phone_prompt_dismissed_at->lt(now()->subDays(7)));
    }

    /**
     * Whether this studio's plan includes team accounts.
     */
    public function hasTeams(): bool
    {
        return $this->hasFeature('teams');
    }

    /**
     * Team seats on the plan, not counting the owner.
     */
    public function seatLimit(): int
    {
        return $this->hasTeams() ? max(0, (int) $this->plan->team_limit) : 0;
    }

    /**
     * Seats taken: active members plus pending, unexpired invitations.
     */
    public function seatsUsed(): int
    {
        return $this->teamMembers()->active()->count() + $this->teamInvitations()->pending()->count();
    }

    /**
     * Team studios this user can open now: active membership, owner still active and on a plan with teams.
     *
     * @return \Illuminate\Support\Collection<int, AccountMember>
     */
    public function activeMemberships(): \Illuminate\Support\Collection
    {
        return $this->memberships()->active()
            ->whereHas('account', fn ($q) => $q->where('is_active', true))
            ->with('account.plan')
            ->orderBy('created_at')
            ->get()
            ->filter(fn (AccountMember $membership) => $membership->account->hasTeams())
            ->values();
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
        return $this->cdnUrl($this->avatar_path);
    }

    public function getLogoUrlAttribute(): ?string
    {
        return $this->cdnUrl($this->logo_path);
    }

    public function getCoverUrlAttribute(): ?string
    {
        return $this->cdnUrl($this->cover_path);
    }

    public function getShareImageUrlAttribute(): ?string
    {
        return $this->cdnUrl($this->share_image_path);
    }

    /**
     * Portfolio design with defaults filled in (memoized; call forgetPortfolio() after changing it).
     */
    public function portfolio(): \App\Support\Portfolio\PortfolioSettings
    {
        return $this->portfolioSettingsCache ??= \App\Support\Portfolio\PortfolioSettings::for($this);
    }

    public function forgetPortfolio(): void
    {
        $this->portfolioSettingsCache = null;
    }

    private ?\App\Support\Portfolio\PortfolioSettings $portfolioSettingsCache = null;

    private function cdnUrl(?string $path): ?string
    {
        if (!$path) {
            return null;
        }
        $cdnDomain = config('filesystems.disks.b2.cdn_domain', 'cdn.ifotoset.com');
        return \App\Support\Cdn::scheme() . "://{$cdnDomain}/" . ltrim($path, '/');
    }

    public function isPubliclyIndexable(): bool
    {
        $reserved = config('reserved_usernames', []);
        return !$this->trashed()
            && $this->is_active
            && $this->profile_public !== false
            && !empty($this->username)
            && !in_array(strtolower($this->username), $reserved, true)
            && $this->role !== 'admin'
            && $this->role !== 'superadmin';
    }

    public function scopePubliclyIndexable(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        $reserved = config('reserved_usernames', []);
        return $query->where('is_active', true)
            ->where('profile_public', true)
            ->whereNotNull('username')
            ->where('username', '!=', '')
            ->whereNotIn('username', $reserved)
            ->whereNotIn('role', ['admin', 'superadmin']);
    }

    public function getPublicUrlAttribute(): string
    {
        return app(\App\Services\PublicUrlService::class)->photographer($this);
    }
}
?>
