<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Package;
use App\Models\User;
use App\Services\AvailabilityService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicAvailabilityController extends Controller
{
    public function __construct(
        protected AvailabilityService $availabilityService
    ) {}

    /**
     * Get structured available time slots for a photographer on a given date.
     *
     * GET /api/v1/public/photographers/{username}/slots
     */
    public function slots(Request $request, string $username): JsonResponse
    {
        $validated = $request->validate([
            'date'         => ['required', 'date_format:Y-m-d'],
            'package_uuid' => ['required', 'string'],
        ]);

        $photographer = User::where('username', strtolower($username))->firstOrFail();
        
        $package = Package::where('uuid', $validated['package_uuid'])
            ->where('user_id', $photographer->id)
            ->firstOrFail();

        $date = Carbon::createFromFormat('Y-m-d', $validated['date']);

        $slots = $this->availabilityService->getAvailableSlots($photographer, $date, $package);

        return response()->json([
            'data' => $slots,
        ]);
    }

    /**
     * Get dates with availability metadata in a given month.
     *
     * GET /api/v1/public/photographers/{username}/available-days
     */
    public function availableDays(Request $request, string $username): JsonResponse
    {
        $validated = $request->validate([
            'month'        => ['required', 'date_format:Y-m'], // e.g. 2026-08
            'package_uuid' => ['required', 'string'],
        ]);

        $photographer = User::where('username', strtolower($username))->firstOrFail();
        
        $package = Package::where('uuid', $validated['package_uuid'])
            ->where('user_id', $photographer->id)
            ->firstOrFail();

        $monthStr = $validated['month']; // "2026-08"
        $timezone = $photographer->timezone ?: 'Africa/Kigali';
        $nowLocal = Carbon::now($timezone);

        $startOfMonth = Carbon::createFromFormat('Y-m', $monthStr, $timezone)->startOfMonth();
        $endOfMonth = Carbon::createFromFormat('Y-m', $monthStr, $timezone)->endOfMonth();

        // Min/max booking window
        $minDate = $nowLocal->copy()->addHours(24)->startOfDay();
        $maxDate = $nowLocal->copy()->addDays(365)->endOfDay();

        $cacheKey = sprintf(
            'available-days:photographer_id:%d:package_uuid:%s:month:%s',
            $photographer->id,
            $package->uuid,
            $monthStr
        );

        $days = \Illuminate\Support\Facades\Cache::remember($cacheKey, 90, function () use ($photographer, $startOfMonth, $endOfMonth, $package) {
            return $this->availabilityService->getAvailableDaysForMonth($photographer, $startOfMonth, $endOfMonth, $package);
        });

        return response()->json([
            'month' => $monthStr,
            'timezone' => $timezone,
            'booking_window' => [
                'min_date' => $minDate->toDateString(),
                'max_date' => $maxDate->toDateString(),
            ],
            'days' => $days,
        ]);
    }
}
