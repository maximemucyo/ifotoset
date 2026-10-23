<?php

namespace App\Actions;

use App\Exceptions\BookingOverlapException;
use App\Exceptions\SlotUnavailableException;
use App\Models\Booking;
use App\Models\Client;
use App\Models\Package;
use App\Models\User;
use App\Services\AvailabilityService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class CreateBookingAction
{
    public function __construct(
        protected AvailabilityService $availabilityService
    ) {}

    /**
     * Create a new booking.
     *
     * Times without an explicit offset are read in the photographer's timezone; they are stored in UTC.
     * Options: validate_availability, ignore_overlap, collect_deposit (hold the slot until the
     * deposit is paid), notify (send the "new booking" emails, default true).
     *
     * @throws BookingOverlapException
     * @throws SlotUnavailableException
     */
    public function execute(User $user, array $data): Booking
    {
        return DB::transaction(function () use ($user, $data) {
            // 1. Acquire deterministic lock on the photographer's record to prevent concurrent booking races
            User::where('id', $user->id)->lockForUpdate()->first();
            Booking::where('user_id', $user->id)->lockForUpdate()->get();

            // Resolve Client ID from UUID
            $clientId = null;
            if (!empty($data['client_id'])) {
                $client = Client::where('uuid', $data['client_id'])
                    ->where('user_id', $user->id)
                    ->firstOrFail();
                $clientId = $client->id;
            }

            // Resolve Package ID from UUID (archived or hidden packages can't be booked)
            $packageId = null;
            $package = null;
            if (!empty($data['package_id'])) {
                $package = Package::where('uuid', $data['package_id'])
                    ->where('user_id', $user->id)
                    ->active()
                    ->firstOrFail();
                $packageId = $package->id;
            }

            $timezone = $data['timezone'] ?? $user->timezone ?? config('app.timezone', 'UTC');
            $startsAt = $this->parseTime($data['starts_at'], $timezone);

            // If the package is set, auto-calculate the ends_at duration
            if ($package && empty($data['ends_at'])) {
                $endsAt = $startsAt->copy()->addMinutes($package->duration_minutes);
            } elseif (!empty($data['ends_at'])) {
                $endsAt = $this->parseTime($data['ends_at'], $timezone);
            } else {
                $endsAt = $startsAt->copy()->addMinutes((int) ($data['duration_minutes'] ?? 60));
            }

            // 2. Validate availability using AvailabilityService if requested (public flow)
            $validateAvailability = filter_var($data['validate_availability'] ?? false, FILTER_VALIDATE_BOOLEAN);
            if ($validateAvailability && $package) {
                $isAvailable = $this->availabilityService->isSlotAvailable($user, $startsAt, $package);
                if (!$isAvailable) {
                    throw new SlotUnavailableException();
                }
            }

            // 3. Fallback standard overlap check (if not ignoring overlaps)
            $ignoreOverlap = filter_var($data['ignore_overlap'] ?? false, FILTER_VALIDATE_BOOLEAN);
            if (!$ignoreOverlap) {
                $conflicts = Booking::where('user_id', $user->id)
                    ->blocking()
                    ->overlapping($startsAt, $endsAt)
                    ->get();

                if ($conflicts->isNotEmpty()) {
                    throw new BookingOverlapException($conflicts);
                }
            }

            // 4. Online deposit: hold the slot while the client approves the payment
            $depositAmount = filter_var($data['collect_deposit'] ?? false, FILTER_VALIDATE_BOOLEAN)
                ? $package?->computedDepositAmount()
                : null;

            $booking = Booking::create([
                'user_id' => $user->id,
                'client_id' => $clientId,
                'package_id' => $packageId,
                'title' => $data['title'],
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'timezone' => $timezone,
                'location' => $data['location'] ?? null,
                'status' => $data['status'] ?? 'pending',
                'price' => $data['price'] ?? ($package ? $package->price : null),
                'deposit_amount' => $depositAmount,
                'deposit_status' => $depositAmount ? 'pending' : null,
                'hold_expires_at' => $depositAmount ? now()->addMinutes(Booking::DEPOSIT_HOLD_MINUTES) : null,
                'currency' => $data['currency'] ?? ($package ? $package->currency : 'RWF'),
                'notes' => $data['notes'] ?? null,
            ]);

            if (filter_var($data['notify'] ?? true, FILTER_VALIDATE_BOOLEAN)) {
                event(new \App\Events\BookingCreated($booking));
            }

            return $booking;
        });
    }

    /**
     * Parse a wall-clock time in the given timezone (an explicit offset wins) and return it in UTC.
     */
    protected function parseTime(mixed $value, string $timezone): Carbon
    {
        $time = $value instanceof \DateTimeInterface
            ? Carbon::instance($value)
            : Carbon::parse((string) $value, $timezone);

        return $time->utc();
    }
}
