<?php

namespace App\Actions;

use App\Exceptions\BookingOverlapException;
use App\Models\Booking;
use App\Models\Client;
use App\Models\Package;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class UpdateBookingAction
{
    /**
     * Update an existing booking.
     *
     * @throws BookingOverlapException
     */
    public function execute(Booking $booking, array $data): Booking
    {
        return DB::transaction(function () use ($booking, $data) {
            // Resolve Client ID from UUID if provided
            if (array_key_exists('client_id', $data)) {
                if (!empty($data['client_id'])) {
                    $client = Client::where('uuid', $data['client_id'])
                        ->where('user_id', $booking->user_id)
                        ->firstOrFail();
                    $data['client_id'] = $client->id;
                } else {
                    $data['client_id'] = null;
                }
            }

            // Resolve Package ID from UUID if provided
            if (array_key_exists('package_id', $data)) {
                if (!empty($data['package_id'])) {
                    $package = Package::where('uuid', $data['package_id'])
                        ->where('user_id', $booking->user_id)
                        ->firstOrFail();
                    $data['package_id'] = $package->id;
                } else {
                    $data['package_id'] = null;
                }
            }

            $startsAt = isset($data['starts_at']) ? new Carbon($data['starts_at']) : $booking->starts_at;
            $endsAt = array_key_exists('ends_at', $data)
                ? ($data['ends_at'] ? new Carbon($data['ends_at']) : null)
                : $booking->ends_at;

            // Check overlap if starts_at or ends_at changed AND ignore_overlap is not set
            $timeChanged = isset($data['starts_at']) || array_key_exists('ends_at', $data);
            $ignoreOverlap = filter_var($data['ignore_overlap'] ?? false, FILTER_VALIDATE_BOOLEAN);

            if ($timeChanged && !$ignoreOverlap) {
                $newEnd = $endsAt ? clone $endsAt : (clone $startsAt)->addHour();

                $conflicts = Booking::where('user_id', $booking->user_id)
                    ->where('id', '!=', $booking->id)
                    ->whereNull('deleted_at')
                    ->where('starts_at', '<', $newEnd)
                    ->whereRaw('COALESCE(ends_at, DATE_ADD(starts_at, INTERVAL 1 HOUR)) > ?', [$startsAt])
                    ->get();

                if ($conflicts->isNotEmpty()) {
                    throw new BookingOverlapException($conflicts);
                }
            }

            // Remove ignore_overlap from update data
            unset($data['ignore_overlap']);

            $booking->update($data);

            return $booking;
        });
    }
}
