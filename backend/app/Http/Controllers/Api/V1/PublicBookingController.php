<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\CreateBookingAction;
use App\Exceptions\BookingOverlapException;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\BookingResource;
use App\Http\Resources\V1\PackageResource;
use App\Models\Client;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicBookingController extends Controller
{
    /**
     * Show public booking page for a photographer.
     */
    public function show(string $username): JsonResponse
    {
        $photographer = User::where('username', strtolower($username))->firstOrFail();
        $packages = $photographer->packages()->active()->orderBy('sort_order', 'asc')->get();

        $cdnDomain = config('filesystems.disks.b2.cdn_domain', 'cdn.ifotoset.com');
        $avatarUrl = $photographer->avatar_path ? "https://{$cdnDomain}/" . ltrim($photographer->avatar_path, '/') : null;

        return response()->json([
            'photographer' => [
                'name' => $photographer->name,
                'username' => $photographer->username,
                'bio' => $photographer->bio,
                'avatar_url' => $avatarUrl,
                'location' => $photographer->location,
            ],
            'packages' => PackageResource::collection($packages),
            'availability' => (object)[],
        ]);
    }

    /**
     * Submit an online booking.
     */
    public function store(Request $request, string $username, CreateBookingAction $action): JsonResponse
    {
        $photographer = User::where('username', strtolower($username))->firstOrFail();

        // Plan Gate: Only paid plans can accept online bookings
        if ($photographer->plan->slug === 'free') {
            return response()->json([
                'code' => 'PLAN_REQUIRED',
                'message' => 'Online bookings require a paid plan.',
            ], 403);
        }

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'client_name' => ['required', 'string', 'max:255'],
            'client_email' => ['required', 'email', 'max:255'],
            'client_phone' => ['nullable', 'string', 'max:50'],
            'package_id' => ['required', 'string'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'notes' => ['nullable', 'string'],
            'ignore_overlap' => ['sometimes', 'boolean'],
        ]);

        // Auto-create/resolve client in the photographer's CRM
        $client = Client::firstOrCreate(
            [
                'user_id' => $photographer->id,
                'email' => strtolower(trim($validated['client_email'])),
            ],
            [
                'name' => $validated['client_name'],
                'phone' => $validated['client_phone'],
            ]
        );

        $bookingData = [
            'title' => $validated['title'],
            'client_id' => $client->uuid,
            'package_id' => $validated['package_id'],
            'starts_at' => $validated['starts_at'],
            'ends_at' => $validated['ends_at'] ?? null,
            'status' => 'pending',
            'notes' => $validated['notes'] ?? null,
            'ignore_overlap' => $validated['ignore_overlap'] ?? false,
        ];

        try {
            $booking = $action->execute($photographer, $bookingData);

            return (new BookingResource($booking->load(['client', 'package'])))
                ->response()
                ->setStatusCode(201);
        } catch (BookingOverlapException $e) {
            return response()->json([
                'code' => 'BOOKING_OVERLAP',
                'message' => $e->getMessage(),
                'conflicts' => BookingResource::collection($e->getConflictingBookings()),
            ], 422);
        }
    }
}
