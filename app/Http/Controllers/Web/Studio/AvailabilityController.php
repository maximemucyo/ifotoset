<?php

namespace App\Http\Controllers\Web\Studio;

use App\Actions\Studio\CreateAvailabilityException;
use App\Actions\Studio\CreateBlockedSlot;
use App\Actions\Studio\UpdateAvailabilitySchedule;
use App\Http\Controllers\Controller;
use App\Models\AvailabilityException;
use App\Models\AvailabilitySetting;
use App\Models\BlockedSlot;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AvailabilityController extends Controller
{
    /**
     * Display the availability and scheduling management suite.
     */
    public function index(Request $request): View
    {
        $user = $request->user();

        // Ensure 7 weekly default settings exist (0=Sunday to 6=Saturday)
        $settings = [];
        for ($day = 0; $day <= 6; $day++) {
            $setting = AvailabilitySetting::firstOrCreate(
                ['user_id' => $user->id, 'day_of_week' => $day],
                ['start_time' => '09:00:00', 'end_time' => '17:00:00', 'is_active' => true]
            );
            $settings[$day] = [
                'day_of_week' => $setting->day_of_week,
                'start_time'  => substr($setting->start_time, 0, 5),
                'end_time'    => substr($setting->end_time, 0, 5),
                'is_active'   => (bool) $setting->is_active,
            ];
        }

        $exceptions = AvailabilityException::where('user_id', $user->id)
            ->orderBy('date', 'asc')
            ->get();

        $blockedSlots = BlockedSlot::where('user_id', $user->id)
            ->orderBy('starts_at', 'asc')
            ->get();

        return view('studio.availability.index', [
            'user'         => $user,
            'settings'     => $settings,
            'exceptions'   => $exceptions,
            'blockedSlots' => $blockedSlots,
            'timezones'    => [
                'Africa/Kigali',
                'Africa/Nairobi',
                'Africa/Johannesburg',
                'Africa/Lagos',
                'Africa/Cairo',
                'UTC',
                'Europe/London',
                'Europe/Paris',
                'America/New_York',
            ],
        ]);
    }

    /**
     * Update weekly recurring schedule and photographer preferences.
     */
    public function updateSettings(Request $request, UpdateAvailabilitySchedule $action): RedirectResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'settings'                => ['required', 'array', 'size:7'],
            'settings.*.day_of_week'  => ['required', 'integer', 'between:0,6'],
            'settings.*.start_time'   => ['required', 'date_format:H:i'],
            'settings.*.end_time'     => ['required', 'date_format:H:i', 'after:settings.*.start_time'],
            'settings.*.is_active'    => ['nullable', 'boolean'],
            'timezone'                => ['required', 'string', 'max:100'],
            'slot_interval_minutes'   => ['required', 'integer', 'in:15,30,60'],
        ]);

        $action->execute(
            $user,
            $validated['settings'],
            $validated['timezone'],
            (int) $validated['slot_interval_minutes']
        );

        return back()->with('success', 'Weekly schedule and session preferences updated successfully.');
    }

    /**
     * Store a date-specific availability exception.
     */
    public function storeException(Request $request, CreateAvailabilityException $action): RedirectResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'date'       => ['required', 'date_format:Y-m-d'],
            'start_time' => ['nullable', 'required_if:is_closed,0', 'date_format:H:i'],
            'end_time'   => ['nullable', 'required_if:is_closed,0', 'date_format:H:i', 'after:start_time'],
            'is_closed'  => ['nullable', 'boolean'],
        ]);

        $action->execute($user, $validated);

        return back()->with('success', 'Date exception saved successfully.');
    }

    /**
     * Delete a date exception.
     */
    public function deleteException(Request $request, string $uuid): RedirectResponse
    {
        $exception = AvailabilityException::where('uuid', $uuid)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $exception->delete();
        $request->user()->clearAvailabilityCache();

        return back()->with('success', 'Date exception removed successfully.');
    }

    /**
     * Store a blackout/blocked time slot.
     */
    public function storeBlocked(Request $request, CreateBlockedSlot $action): RedirectResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'starts_at' => ['required', 'date'],
            'ends_at'   => ['required', 'date', 'after:starts_at'],
            'reason'    => ['nullable', 'string', 'max:255'],
        ]);

        $action->execute($user, $validated);

        return back()->with('success', 'Blackout time slot blocked successfully.');
    }

    /**
     * Delete a blackout slot.
     */
    public function deleteBlocked(Request $request, string $uuid): RedirectResponse
    {
        $blocked = BlockedSlot::where('uuid', $uuid)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $blocked->delete();
        $request->user()->clearAvailabilityCache();

        return back()->with('success', 'Time slot unblocked successfully.');
    }
}
