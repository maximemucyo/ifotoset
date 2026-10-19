<?php

namespace App\Services;

use App\Models\AvailabilityException;
use App\Models\AvailabilitySetting;
use App\Models\User;
use Carbon\Carbon;

class AvailabilityResolver
{
    /**
     * Resolves working hours/window for a given date.
     * Returns an array with ['start_time', 'end_time'] in H:i:s format, or null if closed.
     */
    public function resolveWorkingHours(User $photographer, Carbon $date): ?array
    {
        // 1. Check for single-date custom exceptions (e.g. holiday or custom hours)
        $exception = AvailabilityException::where('user_id', $photographer->id)
            ->whereDate('date', $date->toDateString())
            ->first();

        if ($exception) {
            if ($exception->is_closed) {
                return null;
            }
            return [
                'start_time' => $exception->start_time,
                'end_time' => $exception->end_time,
            ];
        }

        // 2. Fallback to weekly default hours setting
        $dayOfWeek = $date->dayOfWeek; // 0 (Sunday) to 6 (Saturday)
        $setting = AvailabilitySetting::where('user_id', $photographer->id)
            ->where('day_of_week', $dayOfWeek)
            ->first();

        if ($setting && $setting->is_active) {
            return [
                'start_time' => $setting->start_time,
                'end_time' => $setting->end_time,
            ];
        }

        // If no default setting is active, assume closed by default
        return null;
    }
}
