<?php

namespace App\Models;

use App\Casts\UuidBinaryCast;
use App\Enums\BookingStatus;
use App\Traits\HasBinaryUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Database\Eloquent\SoftDeletes;

class Booking extends Model
{
    use SoftDeletes, HasBinaryUuid;

    protected $fillable = [
        'uuid',
        'user_id',
        'client_id',
        'package_id',
        'title',
        'starts_at',
        'ends_at',
        'timezone',
        'location',
        'status',
        'price',
        'deposit_amount',
        'deposit_status',
        'deposit_paid_at',
        'hold_expires_at',
        'currency',
        'notes',
    ];

    /**
     * How long a slot is held while the client approves the Mobile Money deposit.
     */
    public const DEPOSIT_HOLD_MINUTES = 15;

    protected $casts = [
        'uuid' => UuidBinaryCast::class,
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'status' => BookingStatus::class,
        'price' => 'decimal:2',
        'deposit_amount' => 'decimal:2',
        'deposit_paid_at' => 'datetime',
        'hold_expires_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Scope to bookings overlapping a given range.
     */
    public function scopeOverlapping(Builder $query, $startsAt, $endsAt): Builder
    {
        return $query->where('starts_at', '<', $endsAt)
                     ->where('ends_at', '>', $startsAt);
    }

    /**
     * Statuses that occupied/block photographer availability.
     */
    public static function blockingStatuses(): array
    {
        return [
            BookingStatus::Confirmed->value,
            BookingStatus::Pending->value, // Include pending bookings to prevent overlapping requests
        ];
    }

    /**
     * Scope to bookings that occupy the photographer's time. An unpaid deposit
     * hold stops blocking the slot once it expires.
     */
    public function scopeBlocking(Builder $query): Builder
    {
        return $query->whereIn('status', self::blockingStatuses())
            ->where(fn (Builder $q) => $q->whereNull('hold_expires_at')
                ->orWhere('hold_expires_at', '>', now())
                ->orWhere('deposit_status', 'paid'));
    }

    /**
     * Hide checkouts the client abandoned before paying the deposit; the photographer never saw them.
     */
    public function scopeVisibleInStudio(Builder $query): Builder
    {
        return $query->where(fn (Builder $q) => $q->whereNull('deposit_status')
            ->orWhere('deposit_status', '!=', 'expired')
            ->orWhere('status', '!=', BookingStatus::Cancelled->value));
    }

    public function displayTimezone(): string
    {
        return $this->timezone ?: ($this->user?->timezone ?: config('app.timezone', 'UTC'));
    }

    /**
     * Start time in the booking's own timezone (stored in UTC).
     */
    public function localStartsAt(): Carbon
    {
        return $this->starts_at->copy()->setTimezone($this->displayTimezone());
    }

    public function localEndsAt(): ?Carbon
    {
        return $this->ends_at?->copy()->setTimezone($this->displayTimezone());
    }

    /**
     * e.g. "Saturday, November 8, 2026 at 2:00 PM (Africa/Kigali)".
     */
    public function whenLabel(): string
    {
        return $this->localStartsAt()->format('l, F j, Y \a\t g:i A') . ' (' . $this->displayTimezone() . ')';
    }

    public function isDepositPaid(): bool
    {
        return $this->deposit_status === 'paid';
    }

    /**
     * True while the slot is held for a deposit that has not been paid yet.
     */
    public function isAwaitingDeposit(): bool
    {
        return in_array($this->deposit_status, ['pending', 'failed'], true)
            && $this->hold_expires_at?->isFuture();
    }

    public function balanceDue(): ?float
    {
        if ($this->price === null) {
            return null;
        }

        $paid = $this->isDepositPaid() ? (float) $this->deposit_amount : 0.0;

        return max(0.0, (float) $this->price - $paid);
    }
}
