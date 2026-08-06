<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\CreateBookingAction;
use App\Actions\UpdateBookingAction;
use App\Exceptions\BookingOverlapException;
use App\Http\Controllers\Controller;
use App\Http\Requests\V1\StoreBookingRequest;
use App\Http\Requests\V1\UpdateBookingRequest;
use App\Http\Resources\V1\BookingResource;
use App\Models\Booking;
use App\Models\Client;
use App\Models\Package;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BookingController extends Controller
{
    /**
     * List bookings.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Booking::where('user_id', $request->user()->id);

        if ($status = $request->input('status')) {
            $statuses = explode(',', $status);
            $query->whereIn('status', $statuses);
        }

        if ($from = $request->input('from')) {
            $query->where('starts_at', '>=', $from);
        }

        if ($to = $request->input('to')) {
            $query->where('starts_at', '<=', $to);
        }

        if ($clientUuid = $request->input('client')) {
            $client = Client::where('uuid', $clientUuid)->first();
            $query->where('client_id', $client?->id ?? 0);
        }

        if ($packageUuid = $request->input('package')) {
            $package = Package::where('uuid', $packageUuid)->first();
            $query->where('package_id', $package?->id ?? 0);
        }

        $bookings = $query->with(['client', 'package'])
            ->orderBy('starts_at', 'asc')
            ->paginate($request->integer('per_page', 20));

        return BookingResource::collection($bookings)->response();
    }

    /**
     * Store booking.
     */
    public function store(StoreBookingRequest $request, CreateBookingAction $action): JsonResponse
    {
        try {
            $booking = $action->execute($request->user(), $request->validated());

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

    /**
     * Show booking.
     */
    public function show(Request $request, string $uuid): JsonResponse
    {
        $booking = Booking::where('uuid', $uuid)->firstOrFail();

        $this->authorize('view', $booking);

        return (new BookingResource($booking->load(['client', 'package'])))->response();
    }

    /**
     * Update booking.
     */
    public function update(UpdateBookingRequest $request, string $uuid, UpdateBookingAction $action): JsonResponse
    {
        $booking = Booking::where('uuid', $uuid)->firstOrFail();

        $this->authorize('update', $booking);

        try {
            $updatedBooking = $action->execute($booking, $request->validated());

            return (new BookingResource($updatedBooking->load(['client', 'package'])))->response();
        } catch (BookingOverlapException $e) {
            return response()->json([
                'code' => 'BOOKING_OVERLAP',
                'message' => $e->getMessage(),
                'conflicts' => BookingResource::collection($e->getConflictingBookings()),
            ], 422);
        }
    }

    /**
     * Destroy booking.
     */
    public function destroy(Request $request, string $uuid): JsonResponse
    {
        $booking = Booking::where('uuid', $uuid)->firstOrFail();

        $this->authorize('delete', $booking);

        $booking->delete();

        return response()->json(null, 204);
    }
}
