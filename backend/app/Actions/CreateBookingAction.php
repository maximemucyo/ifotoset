<?php

namespace App\Actions;

use App\Exceptions\BookingOverlapException;
use App\Models\Booking;
use App\Models\Client;
use App\Models\Package;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class CreateBookingAction
{
    /**
     * Create a new booking.
     *
     * @throws BookingOverlapException
     */
    public function execute(User $user, array $data): Booking
    {
        return DB::transaction(function () use ($user, $data) {
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
            if (!empty($data['package_id'])) {
                $package = Package::where('uuid', $data['package_id'])
                    ->where('user_id', $user->id)
                    ->firstOrFail();
                $packageId = $package->id;
            }

            $startsAt = new Carbon($data['starts_at']);
            $endsAt = isset($data['ends_at']) ? new Carbon($data['ends_at']) : null;

            // Overlap check
            $ignoreOverlap = filter_var($data['ignore_overlap'] ?? false, FILTER_VALIDATE_BOOLEAN);
            if (!$ignoreOverlap) {
                $newEnd = $endsAt ? clone $endsAt : (clone $startsAt)->addHour();

                $conflicts = Booking::where('user_id', $user->id)
                    ->whereNull('deleted_at')
                    ->where('starts_at', '<', $newEnd)
                    ->whereRaw('COALESCE(ends_at, DATE_ADD(starts_at, INTERVAL 1 HOUR)) > ?', [$startsAt])
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
                'timezone' => $data['timezone'] ?? config('app.timezone', 'UTC'),
                'location' => $data['location'] ?? null,
                'status' => $data['status'] ?? 'pending',
                'price' => $data['price'] ?? null,
                'currency' => $data['currency'] ?? 'RWF',
                'notes' => $data['notes'] ?? null,
            ]);
        });
    }
}
