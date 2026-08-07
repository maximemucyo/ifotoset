<?php

namespace App\Services;

use App\Models\Package;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;

class SlotGenerator
{
    /**
     * Generates local candidate booking slots based on work hours, package duration, and photographer interval.
     * Returns a Collection of arrays: [starts_at (Carbon), ends_at (Carbon), display (string)]
     */
    public function generateCandidates(
        User $photographer,
        Carbon $date,
        Package $package,
        array $workingHours
    ): Collection {
        $timezone = $photographer->timezone ?: 'Africa/Kigali';
        
        // 1. Calculate occupied duration (Session duration + buffers)
        $duration = (int) $package->duration_minutes;
        $bufferBefore = (int) ($package->buffer_before_minutes ?? 0);
        $bufferAfter = (int) ($package->buffer_after_minutes ?? 0);
        $totalOccupiedMinutes = $duration + $bufferBefore + $bufferAfter;

        // 2. Parse working hours in photographer local timezone
        $startTimeString = $workingHours['start_time'];
        $endTimeString = $workingHours['end_time'];

        $localWorkStart = Carbon::createFromFormat('Y-m-d H:i:s', $date->format('Y-m-d') . ' ' . $startTimeString, $timezone);
        $localWorkEnd = Carbon::createFromFormat('Y-m-d H:i:s', $date->format('Y-m-d') . ' ' . $endTimeString, $timezone);

        // 3. Compute last valid slot start time (so the whole occupied range fits before workday end)
        // lastStartLocal = workEndLocal - occupiedDuration
        $lastPossibleStart = (clone $localWorkEnd)->subMinutes($totalOccupiedMinutes);

        if ($localWorkStart->greaterThanOrEqualTo($lastPossibleStart)) {
            return collect();
        }

        // 4. Determine step/interval (default 30 minutes)
        $intervalMinutes = (int) ($photographer->slot_interval_minutes ?: 30);
        $period = CarbonPeriod::create($localWorkStart, "{$intervalMinutes} minutes", $lastPossibleStart);

        $slots = collect();

        foreach ($period as $time) {
            $startsAt = clone $time;
            $endsAt = (clone $startsAt)->addMinutes($duration);

            $slots->push([
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'display' => $startsAt->format('h:i A'),
            ]);
        }

        return $slots;
    }
}
