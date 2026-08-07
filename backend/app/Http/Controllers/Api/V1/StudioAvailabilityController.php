<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AvailabilityException;
use App\Models\AvailabilitySetting;
use App\Models\BlockedSlot;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Ramsey\Uuid\Uuid;

class StudioAvailabilityController extends Controller
{
    /**
     * Get weekly availability settings.
     * GET /api/v1/availability/settings
     */
    public function getSettings(Request $request): JsonResponse
    {
        $user = $request->user();

        // Ensure default settings exist for all 7 days of the week (0 = Sunday, 6 = Saturday)
        $settings = [];
        for ($day = 0; $day <= 6; $day++) {
            $setting = AvailabilitySetting::firstOrCreate(
                ['user_id' => $user->id, 'day_of_week' => $day],
                ['start_time' => '09:00:00', 'end_time' => '17:00:00', 'is_active' => true]
            );
            $settings[] = [
                'day_of_week' => $setting->day_of_week,
                'start_time'  => substr($setting->start_time, 0, 5), // H:i
                'end_time'    => substr($setting->end_time, 0, 5),
                'is_active'   => $setting->is_active,
            ];
        }

        return response()->json([
            'settings' => $settings,
            'timezone' => $user->timezone,
            'slot_interval_minutes' => $user->slot_interval_minutes,
        ]);
    }

    /**
     * Update weekly availability settings + photographer timezone configs.
     * PUT /api/v1/availability/settings
     */
    public function updateSettings(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'settings'                => ['required', 'array', 'size:7'],
            'settings.*.day_of_week'  => ['required', 'integer', 'between:0,6'],
            'settings.*.start_time'   => ['required', 'date_format:H:i'],
            'settings.*.end_time'     => ['required', 'date_format:H:i', 'after:settings.*.start_time'],
            'settings.*.is_active'    => ['required', 'boolean'],
            'timezone'                => ['required', 'string', 'max:100'],
            'slot_interval_minutes'   => ['required', 'integer', 'in:15,30,60'],
        ]);

        // Update weekly settings
        foreach ($validated['settings'] as $dayData) {
            AvailabilitySetting::updateOrCreate(
                ['user_id' => $user->id, 'day_of_week' => $dayData['day_of_week']],
                [
                    'start_time' => $dayData['start_time'] . ':00',
                    'end_time'   => $dayData['end_time'] . ':00',
                    'is_active'  => $dayData['is_active'],
                ]
            );
        }

        // Update photographer configuration details
        $user->update([
            'timezone' => $validated['timezone'],
            'slot_interval_minutes' => $validated['slot_interval_minutes'],
        ]);

        $user->clearAvailabilityCache();

        return response()->json(['message' => 'Availability settings saved successfully.']);
    }

    /**
     * Get date exceptions.
     * GET /api/v1/availability/exceptions
     */
    public function getExceptions(Request $request): JsonResponse
    {
        $user = $request->user();

        $exceptions = AvailabilityException::where('user_id', $user->id)
            ->orderBy('date', 'asc')
            ->get()
            ->map(function ($ex) {
                return [
                    'uuid'       => $ex->uuid,
                    'date'       => $ex->date->toDateString(),
                    'start_time' => $ex->start_time ? substr($ex->start_time, 0, 5) : null,
                    'end_time'   => $ex->end_time ? substr($ex->end_time, 0, 5) : null,
                    'is_closed'  => $ex->is_closed,
                ];
            });

        return response()->json(['exceptions' => $exceptions]);
    }

    /**
     * Add or update a date exception.
     * POST /api/v1/availability/exceptions
     */
    public function storeException(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'date'       => ['required', 'date_format:Y-m-d'],
            'start_time' => ['nullable', 'required_if:is_closed,false', 'date_format:H:i'],
            'end_time'   => ['nullable', 'required_if:is_closed,false', 'date_format:H:i', 'after:start_time'],
            'is_closed'  => ['required', 'boolean'],
        ]);

        try {
            $exception = AvailabilityException::updateOrCreate(
                ['user_id' => $user->id, 'date' => $validated['date']],
                [
                    'uuid'       => Uuid::uuid7()->toString(),
                    'start_time' => $validated['is_closed'] ? null : ($validated['start_time'] . ':00'),
                    'end_time'   => $validated['is_closed'] ? null : ($validated['end_time'] . ':00'),
                    'is_closed'  => $validated['is_closed'],
                ]
            );

            $user->clearAvailabilityCache();

            return response()->json([
                'message'   => 'Exception saved successfully.',
                'exception' => [
                    'uuid'       => $exception->uuid,
                    'date'       => $exception->date->toDateString(),
                    'start_time' => $exception->start_time ? substr($exception->start_time, 0, 5) : null,
                    'end_time'   => $exception->end_time ? substr($exception->end_time, 0, 5) : null,
                    'is_closed'  => $exception->is_closed,
                ]
            ], 201);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Delete a date exception.
     * DELETE /api/v1/availability/exceptions/{uuid}
     */
    public function deleteException(Request $request, string $uuid): JsonResponse
    {
        $exception = AvailabilityException::where('uuid', $uuid)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $exception->delete();

        $request->user()->clearAvailabilityCache();

        return response()->json(['message' => 'Exception removed successfully.']);
    }

    /**
     * Get manual blocked slot blackouts.
     * GET /api/v1/availability/blocked
     */
    public function getBlocked(Request $request): JsonResponse
    {
        $user = $request->user();

        $blocked = BlockedSlot::where('user_id', $user->id)
            ->orderBy('starts_at', 'asc')
            ->get()
            ->map(function ($bl) {
                return [
                    'uuid'      => $bl->uuid,
                    'starts_at' => $bl->starts_at->toIso8601String(),
                    'ends_at'   => $bl->ends_at->toIso8601String(),
                    'reason'    => $bl->reason,
                    'source'    => $bl->source,
                ];
            });

        return response()->json(['blocked_slots' => $blocked]);
    }

    /**
     * Create a manual blocked range.
     * POST /api/v1/availability/blocked
     */
    public function storeBlocked(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'starts_at' => ['required', 'date'],
            'ends_at'   => ['required', 'date', 'after:starts_at'],
            'reason'    => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $startsAt = new Carbon($validated['starts_at']);
            $endsAt = new Carbon($validated['ends_at']);

            $blocked = BlockedSlot::create([
                'uuid'      => Uuid::uuid7()->toString(),
                'user_id'   => $user->id,
                'starts_at' => $startsAt,
                'ends_at'   => $endsAt,
                'reason'    => $validated['reason'] ?? null,
                'source'    => 'manual',
            ]);

            $user->clearAvailabilityCache();

            return response()->json([
                'message' => 'Time blocked successfully.',
                'blocked' => [
                    'uuid'      => $blocked->uuid,
                    'starts_at' => $blocked->starts_at->toIso8601String(),
                    'ends_at'   => $blocked->ends_at->toIso8601String(),
                    'reason'    => $blocked->reason,
                    'source'    => $blocked->source,
                ]
            ], 201);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Remove a manual blocked range.
     * DELETE /api/v1/availability/blocked/{uuid}
     */
    public function deleteBlocked(Request $request, string $uuid): JsonResponse
    {
        $blocked = BlockedSlot::where('uuid', $uuid)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $blocked->delete();

        $request->user()->clearAvailabilityCache();

        return response()->json(['message' => 'Time unblocked successfully.']);
    }
}
