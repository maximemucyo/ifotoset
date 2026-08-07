<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\CreateBookingAction;
use App\Exceptions\BookingOverlapException;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\BookingResource;
use App\Models\Client;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicBookingController extends Controller
{
    /**
     * Submit an online booking from the photographer's public page.
     *
     * POST /api/v1/public/booking/{username}
     */
    public function store(Request $request, string $username, CreateBookingAction $action): JsonResponse
    {
        // Honeypot: reject if bots fill the _h field
        if ($request->filled('_h')) {
            return response()->json(['message' => 'Invalid request.'], 422);
        }

        $photographer = User::where('username', strtolower($username))->firstOrFail();

        // Plan Gate: Only paid plans can accept online bookings
        if ($photographer->plan->slug === 'free') {
            return response()->json([
                'code'    => 'PLAN_REQUIRED',
                'message' => 'Online bookings require a paid plan.',
            ], 403);
        }

        $validated = $request->validate([
            'title'          => ['required', 'string', 'max:255'],
            'client_name'    => ['required', 'string', 'max:255'],
            'client_email'   => ['required', 'email', 'max:255'],
            'client_phone'   => ['nullable', 'string', 'max:50'],
            'package_id'     => ['required', 'string'],
            'starts_at'      => ['required', 'date', 'after:now'],       // must be a future date
            'ends_at'        => ['nullable', 'date', 'after:starts_at'],
            'location'       => ['nullable', 'string', 'max:255'],
            'notes'          => ['nullable', 'string', 'max:2000'],
            'ignore_overlap' => ['sometimes', 'boolean'],
            '_h'             => ['sometimes', 'string', 'max:0'],        // honeypot
        ]);

        // Auto-create/resolve client in the photographer's CRM
        $client = Client::firstOrCreate(
            [
                'user_id' => $photographer->id,
                'email'   => strtolower(trim($validated['client_email'])),
            ],
            [
                'name'  => $validated['client_name'],
                'phone' => $validated['client_phone'] ?? null,
            ]
        );

        $bookingData = [
            'title'                 => $validated['title'],
            'client_id'             => $client->uuid,
            'package_id'            => $validated['package_id'],
            'starts_at'             => $validated['starts_at'],
            'ends_at'               => $validated['ends_at'] ?? null,
            'location'              => $validated['location'] ?? null,
            'status'                => 'pending',
            'notes'                 => $validated['notes'] ?? null,
            'ignore_overlap'        => $validated['ignore_overlap'] ?? false,
            'validate_availability' => true,
        ];

        try {
            $booking = $action->execute($photographer, $bookingData);

            $photographer->clearAvailabilityCache();

            return (new BookingResource($booking->load(['client', 'package'])))
                ->response()
                ->setStatusCode(201);
        } catch (BookingOverlapException $e) {
            return response()->json([
                'code'      => 'BOOKING_OVERLAP',
                'message'   => $e->getMessage(),
                'conflicts' => BookingResource::collection($e->getConflictingBookings()),
            ], 422);
        } catch (\RuntimeException $e) {
            return response()->json([
                'code'      => 'SLOT_NOT_AVAILABLE',
                'message'   => $e->getMessage(),
            ], 422);
        }
    }
}
