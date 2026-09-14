<?php

namespace App\Actions\Studio;

use App\Models\AvailabilitySetting;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class UpdateAvailabilitySchedule
{
    /**
     * Update recurring weekly schedule and photographer timezone/interval settings.
     */
    public function execute(User $user, array $daysData, string $timezone, int $slotInterval): void
    {
        DB::transaction(function () use ($user, $daysData, $timezone, $slotInterval) {
            foreach ($daysData as $day) {
                AvailabilitySetting::updateOrCreate(
                    [
                        'user_id'     => $user->id,
                        'day_of_week' => (int) $day['day_of_week'],
                    ],
                    [
                        'start_time' => $day['start_time'] . ':00',
                        'end_time'   => $day['end_time'] . ':00',
                        'is_active'  => ! empty($day['is_active']),
                    ]
                );
            }

            $user->update([
                'timezone'              => $timezone,
                'slot_interval_minutes' => $slotInterval,
            ]);

            $user->clearAvailabilityCache();
        });
    }
}
