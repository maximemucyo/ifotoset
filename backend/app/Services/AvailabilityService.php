<?php

namespace App\Services;

use App\Models\Package;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class AvailabilityService
{
    protected AvailabilityResolver $resolver;
    protected SlotGenerator $generator;
    protected ConflictDetector $detector;

    public function __construct(
        AvailabilityResolver $resolver,
        SlotGenerator $generator,
        ConflictDetector $detector
    ) {
        $this->resolver = $resolver;
        $this->generator = $generator;
        $this->detector = $detector;
    }

    /**
     * Get all structured available time slots for a photographer on a specific date.
     * Returns a Collection of arrays: [starts_at (string), ends_at (string), display (string)]
     */
    public function getAvailableSlots(User $photographer, Carbon $date, Package $package): Collection
    {
        $timezone = $photographer->timezone ?: 'Africa/Kigali';
        $nowLocal = Carbon::now($timezone);

        // 1. Enforce Booking Horizon Limits
        // Minimum notice: 24 hours notice required
        // Maximum horizon: Bookings allowed up to 365 days in advance
        $targetDateLocal = (clone $date)->setTimezone($timezone);
        $diffInDays = $nowLocal->diffInDays($targetDateLocal, false);
        
        if ($diffInDays < 0 || $diffInDays > 365) {
            return collect();
        }

        // 2. Resolve working hours (weekly schedule or custom override exceptions)
        $workingHours = $this->resolver->resolveWorkingHours($photographer, $date);
        if (!$workingHours) {
            return collect();
        }

        // 3. Generate candidate time intervals
        $candidates = $this->generator->generateCandidates($photographer, $date, $package, $workingHours);

        // 4. Filter candidate time slots that have already passed (same-day bookings notice)
        $candidates = $candidates->filter(function ($cand) use ($nowLocal) {
            // Must be at least 24 hours in the future
            return $cand['starts_at']->greaterThan($nowLocal->addHours(24));
        });

        if ($candidates->isEmpty()) {
            return collect();
        }

        // 5. Screen out slots with conflicts (bookings + blocked slots + buffers)
        $available = $this->detector->filterConflicts($photographer, $date, $package, $candidates);

        // 6. Format to structured objects (using ISO8601 string in photographer timezone)
        return $available->map(function ($slot) {
            return [
                'starts_at' => $slot['starts_at']->toIso8601String(),
                'ends_at' => $slot['ends_at']->toIso8601String(),
                'display' => $slot['display'],
            ];
        });
    }

    /**
     * Revalidates if a specific local starts_at time slot is available for booking.
     */
    public function isSlotAvailable(User $photographer, Carbon $startsAt, Package $package): bool
    {
        $timezone = $photographer->timezone ?: 'Africa/Kigali';
        
        // Convert to photographer local timezone for calculation
        $startsAtLocal = (clone $startsAt)->setTimezone($timezone);
        $date = (clone $startsAtLocal)->startOfDay();

        $slots = $this->getAvailableSlots($photographer, $date, $package);

        foreach ($slots as $slot) {
            $slotStart = Carbon::parse($slot['starts_at']);
            if ($slotStart->equalTo($startsAt)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get available days for a photographer in a given month range in a highly optimized way.
     */
    public function getAvailableDaysForMonth(User $photographer, Carbon $startOfMonth, Carbon $endOfMonth, Package $package): array
    {
        $timezone = $photographer->timezone ?: 'Africa/Kigali';
        $nowLocal = Carbon::now($timezone);

        // Pre-load settings (only 7 rows max)
        $settings = \App\Models\AvailabilitySetting::where('user_id', $photographer->id)
            ->get()
            ->keyBy('day_of_week');

        // Pre-load exceptions for the month
        $exceptions = \App\Models\AvailabilityException::where('user_id', $photographer->id)
            ->whereBetween('date', [$startOfMonth->toDateString(), $endOfMonth->toDateString()])
            ->get()
            ->keyBy(function ($item) {
                return Carbon::parse($item->date)->toDateString();
            });

        // Pre-load bookings for the month + buffers
        $bookings = \App\Models\Booking::where('user_id', $photographer->id)
            ->whereIn('status', \App\Models\Booking::blockingStatuses())
            ->where('starts_at', '<=', $endOfMonth->copy()->addDay())
            ->where('ends_at', '>=', $startOfMonth->copy()->subDay())
            ->with('package')
            ->get();

        // Pre-load blocked slots
        $blockedSlots = \App\Models\BlockedSlot::where('user_id', $photographer->id)
            ->where('starts_at', '<=', $endOfMonth)
            ->where('ends_at', '>=', $startOfMonth)
            ->get();

        $days = [];

        // Loop through each day of the month
        for ($date = clone $startOfMonth; $date->lte($endOfMonth); $date->addDay()) {
            $dateStr = $date->toDateString();
            
            // Check horizons first before running heavy slot generation
            $targetDateLocal = (clone $date)->setTimezone($timezone);
            $diffInDays = $nowLocal->diffInDays($targetDateLocal, false);
            if ($diffInDays < 0 || $diffInDays > 365) {
                $days[$dateStr] = [
                    'available' => false,
                    'slot_count' => 0,
                ];
                continue;
            }

            // 1. Resolve working hours using preloaded exceptions and settings
            $workingHours = null;
            if ($exceptions->has($dateStr)) {
                $exception = $exceptions->get($dateStr);
                if (!$exception->is_closed) {
                    $workingHours = [
                        'start_time' => $exception->start_time,
                        'end_time' => $exception->end_time,
                    ];
                }
            } else {
                $dayOfWeek = $date->dayOfWeek;
                if ($settings->has($dayOfWeek)) {
                    $setting = $settings->get($dayOfWeek);
                    if ($setting->is_active) {
                        $workingHours = [
                            'start_time' => $setting->start_time,
                            'end_time' => $setting->end_time,
                        ];
                    }
                }
            }

            if (!$workingHours) {
                $days[$dateStr] = [
                    'available' => false,
                    'slot_count' => 0,
                ];
                continue;
            }

            // 2. Generate candidate time intervals
            $candidates = $this->generator->generateCandidates($photographer, $date, $package, $workingHours);

            // 3. Filter candidate time slots that have already passed (same-day bookings notice)
            $candidates = $candidates->filter(function ($cand) use ($nowLocal) {
                return $cand['starts_at']->greaterThan($nowLocal->copy()->addHours(24));
            });

            if ($candidates->isEmpty()) {
                $days[$dateStr] = [
                    'available' => false,
                    'slot_count' => 0,
                ];
                continue;
            }

            // 4. Screen out conflicts using preloaded collections
            $startOfDayUtc = (clone $date)->startOfDay()->setTimezone('UTC');
            $endOfDayUtc = (clone $date)->endOfDay()->setTimezone('UTC');

            $dayBookings = $bookings->filter(function ($booking) use ($startOfDayUtc, $endOfDayUtc) {
                return $booking->starts_at->lessThanOrEqualTo($endOfDayUtc->copy()->addDay())
                    && $booking->ends_at->greaterThanOrEqualTo($startOfDayUtc->copy()->subDay());
            });

            $dayBlockedSlots = $blockedSlots->filter(function ($blocked) use ($startOfDayUtc, $endOfDayUtc) {
                return $blocked->starts_at->lessThanOrEqualTo($endOfDayUtc)
                    && $blocked->ends_at->greaterThanOrEqualTo($startOfDayUtc);
            });

            // Pre-compute package buffer values
            $candidateBufferBefore = (int) ($package->buffer_before_minutes ?? 0);
            $candidateBufferAfter = (int) ($package->buffer_after_minutes ?? 0);

            $available = $candidates->filter(function ($candidate) use ($dayBookings, $dayBlockedSlots, $candidateBufferBefore, $candidateBufferAfter) {
                $candStart = $candidate['starts_at'];
                $candEnd = $candidate['ends_at'];

                $candOccupiedStart = $candStart->copy()->subMinutes($candidateBufferBefore);
                $candOccupiedEnd = $candEnd->copy()->addMinutes($candidateBufferAfter);

                foreach ($dayBookings as $booking) {
                    $bookingBufferBefore = (int) ($booking->package?->buffer_before_minutes ?? 0);
                    $bookingBufferAfter = (int) ($booking->package?->buffer_after_minutes ?? 0);

                    // CLONE/COPY Carbon object to avoid mutation side-effects across day loops
                    $bookOccupiedStart = $booking->starts_at->copy()->subMinutes($bookingBufferBefore);
                    $bookOccupiedEnd = $booking->ends_at->copy()->addMinutes($bookingBufferAfter);

                    if ($candOccupiedStart->lessThan($bookOccupiedEnd) && $candOccupiedEnd->greaterThan($bookOccupiedStart)) {
                        return false;
                    }
                }

                foreach ($dayBlockedSlots as $blocked) {
                    // CLONE/COPY Carbon object
                    $blockStart = $blocked->starts_at->copy();
                    $blockEnd = $blocked->ends_at->copy();

                    if ($candOccupiedStart->lessThan($blockEnd) && $candOccupiedEnd->greaterThan($blockStart)) {
                        return false;
                    }
                }

                return true;
            })->values();

            $days[$dateStr] = [
                'available' => $available->isNotEmpty(),
                'slot_count' => $available->count(),
            ];
        }

        return $days;
    }
}
