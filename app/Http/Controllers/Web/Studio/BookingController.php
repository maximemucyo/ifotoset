<?php

namespace App\Http\Controllers\Web\Studio;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BookingController extends Controller
{
    /**
     * List all bookings for the photographer.
     */
    public function index(Request $request): View
    {
        $user = $request->user();

        $query = Booking::where('user_id', $user->id)
            ->with(['client', 'package'])
            ->orderBy('starts_at', 'desc');

        if ($request->has('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        $bookings = $query->paginate(15);

        return view('studio.bookings.index', [
            'bookings' => $bookings,
        ]);
    }

    /**
     * Update booking status.
     */
    public function updateStatus(Request $request, string $uuid): RedirectResponse
    {
        $user = $request->user();

        $booking = Booking::where('uuid', $uuid)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $validated = $request->validate([
            'status' => ['required', 'string', 'in:pending,confirmed,completed,cancelled'],
        ]);

        $oldStatus = $booking->status;
        $booking->update(['status' => $validated['status']]);

        if ($validated['status'] !== $oldStatus) {
            event(new \App\Events\BookingStatusChanged($booking, $oldStatus, $validated['status']));
        }

        return back()->with('success', "Booking marked as {$validated['status']}.");
    }
}
