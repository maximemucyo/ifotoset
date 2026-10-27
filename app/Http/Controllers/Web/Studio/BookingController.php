<?php

namespace App\Http\Controllers\Web\Studio;

use App\Actions\CreateBookingAction;
use App\Exceptions\BookingOverlapException;
use App\Http\Controllers\Controller;
use App\Mail\BookingStatusUpdatedMail;
use App\Models\Booking;
use App\Models\Client;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;
use Ramsey\Uuid\Uuid;

class BookingController extends Controller
{
    /**
     * List all bookings for the photographer.
     */
    public function index(Request $request): View
    {
        $user = $request->account();

        $query = Booking::where('user_id', $user->id)
            ->visibleInStudio()
            ->with(['client', 'package'])
            ->orderBy('starts_at', 'desc');

        if ($request->has('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        $bookings = $query->paginate(15);

        return view('studio.bookings.index', [
            'bookings' => $bookings,
            'clients' => $user->clients()->orderBy('name')->get(['id', 'uuid', 'name', 'email']),
            'packages' => $user->packages()->active()->orderBy('sort_order')->get(['id', 'uuid', 'name', 'duration_minutes']),
            'timezone' => $user->timezone ?: 'Africa/Kigali',
        ]);
    }

    /**
     * Add a booking by hand (phone, WhatsApp or walk-in clients).
     */
    public function store(Request $request, CreateBookingAction $createBookingAction): RedirectResponse
    {
        $user = $request->account();

        $validated = $request->validate([
            'client_id' => ['nullable', 'string', 'required_without:client_name'],
            'client_name' => ['nullable', 'string', 'max:255', 'required_without:client_id'],
            'client_email' => ['nullable', 'email', 'max:255'],
            'client_phone' => ['nullable', 'string', 'max:30'],
            'package_id' => ['nullable', 'string'],
            'date' => ['required', 'date_format:Y-m-d'],
            'time' => ['required', 'date_format:H:i'],
            'duration_minutes' => ['nullable', 'integer', 'min:15', 'max:1440', 'required_without:package_id'],
            'location' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'status' => ['required', 'in:pending,confirmed'],
            'notify_client' => ['nullable', 'boolean'],
            'allow_overlap' => ['nullable', 'boolean'],
        ], [
            'client_name.required_without' => 'Choose a client or enter a new client\'s name.',
            'duration_minutes.required_without' => 'Set a duration or choose a package.',
        ]);

        if (!empty($validated['client_id'])) {
            $client = $user->clients()->where('uuid', $validated['client_id'])->firstOrFail();
        } else {
            $email = !empty($validated['client_email']) ? strtolower(trim($validated['client_email'])) : null;
            $client = $email ? $user->clients()->where('email', $email)->first() : null;
            $client ??= Client::create([
                'uuid' => Uuid::uuid7()->toString(),
                'user_id' => $user->id,
                'name' => $validated['client_name'],
                'email' => $email,
                'phone' => $validated['client_phone'] ?? null,
            ]);
        }

        try {
            $booking = $createBookingAction->execute($user, [
                'client_id' => $client->uuid,
                'package_id' => $validated['package_id'] ?? null,
                'title' => "Booking: {$client->name}",
                'starts_at' => "{$validated['date']} {$validated['time']}",
                'duration_minutes' => $validated['duration_minutes'] ?? null,
                'location' => $validated['location'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'status' => $validated['status'],
                'ignore_overlap' => !empty($validated['allow_overlap']),
                // The photographer entered it themselves: no "new booking request" emails
                'notify' => false,
            ]);
        } catch (BookingOverlapException $e) {
            $other = $e->getConflictingBookings()->first();
            $when = $other?->localStartsAt()->format('M j, g:i A');

            return back()->withInput()->with('error', "This overlaps another booking" . ($when ? " ({$when})" : '') . ". Tick \"Book anyway\" to save it regardless.");
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return back()->withInput()->with('error', 'That package is no longer available.');
        }

        if (!empty($validated['notify_client']) && $validated['status'] === 'confirmed' && $client->email) {
            Mail::to($client->email)->send(new BookingStatusUpdatedMail($booking, 'confirmed'));
        }

        return back()->with('success', 'Booking added for ' . $booking->localStartsAt()->format('D, M j \a\t g:i A') . '.');
    }

    /**
     * Update booking status.
     */
    public function updateStatus(Request $request, string $uuid): RedirectResponse
    {
        $user = $request->account();

        $booking = Booking::where('uuid', $uuid)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $validated = $request->validate([
            'status' => ['required', 'string', 'in:pending,confirmed,completed,cancelled'],
        ]);

        $oldStatus = $booking->status->value;
        if ($validated['status'] === $oldStatus) {
            return back()->with('success', "Booking is already {$oldStatus}.");
        }

        $booking->update(['status' => $validated['status']]);

        event(new \App\Events\BookingStatusChanged($booking, $oldStatus, $validated['status']));

        return back()->with('success', "Booking marked as {$validated['status']}.");
    }
}
