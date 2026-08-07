<?php

namespace App\Actions;

use App\Exceptions\BookingOverlapException;
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
     * @throws BookingOverlapException
     */
    public function execute(User $user, array $data): Booking
    {
        return DB::transaction(function () use ($user, $data) {
            // 1. Acquire row lock on existing bookings for this photographer to prevent concurrent inserts/race conditions
            Booking::where('user_id', $user->id)->lockForUpdate()->get();

            // Resolve Client ID from UUID
            $clientId = null;
            if (!empty($data['client_id'])) {
                $client = Client::where('uuid', $data['client_id'])
                    ->where('user_id', $user->id)
                    ->firstOrFail();
                $clientId = $client->id;
            }

            // Resolve Package ID from UUID
            $packageId = null;
            $package = null;
            if (!empty($data['package_id'])) {
                $package = Package::where('uuid', $data['package_id'])
                    ->where('user_id', $user->id)
                    ->firstOrFail();
                $packageId = $package->id;
            }

            $startsAt = new Carbon($data['starts_at']);
            
            // If the package is set, auto-calculate the ends_at duration
            if ($package && empty($data['ends_at'])) {
                $endsAt = (clone $startsAt)->addMinutes($package->duration_minutes);
            } else {
                $endsAt = isset($data['ends_at']) ? new Carbon($data['ends_at']) : (clone $startsAt)->addHour();
            }

            // 2. Validate availability using AvailabilityService if requested (public flow)
            $validateAvailability = filter_var($data['validate_availability'] ?? false, FILTER_VALIDATE_BOOLEAN);
            if ($validateAvailability && $package) {
                $isAvailable = $this->availabilityService->isSlotAvailable($user, $startsAt, $package);
                if (!$isAvailable) {
                    throw new \RuntimeException("The selected time slot is no longer available. Please select another time.");
                }
            }

            // 3. Fallback standard overlap check (if not ignoring overlaps)
            $ignoreOverlap = filter_var($data['ignore_overlap'] ?? false, FILTER_VALIDATE_BOOLEAN);
            if (!$ignoreOverlap) {
                $conflicts = Booking::where('user_id', $user->id)
                    ->whereNull('deleted_at')
                    ->overlapping($startsAt, $endsAt)
                    ->get();

                if ($conflicts->isNotEmpty()) {
                    throw new BookingOverlapException($conflicts);
                }
            }

            return Booking::create([
                'user_id' => $user->id,
                'client_id' => $clientId,
                'package_id' => $packageId,
                'title' => $data['title'],
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'timezone' => $data['timezone'] ?? $user->timezone ?? config('app.timezone', 'UTC'),
                'location' => $data['location'] ?? null,
                'status' => $data['status'] ?? 'pending',
                'price' => $data['price'] ?? ($package ? $package->price : null),
                'currency' => $data['currency'] ?? ($package ? $package->currency : 'RWF'),
                'notes' => $data['notes'] ?? null,
            ]);
        });
    }
}
