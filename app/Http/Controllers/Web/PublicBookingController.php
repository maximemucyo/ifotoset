<?php

namespace App\Http\Controllers\Web;

use App\Actions\Billing\FinalizePawaPayPayment;
use App\Actions\Billing\InitiateBookingDeposit;
use App\Actions\CreateBookingAction;
use App\Contracts\PaymentGateway;
use App\Enums\BookingStatus;
use App\Exceptions\BookingOverlapException;
use App\Exceptions\SlotUnavailableException;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Client;
use App\Models\Package;
use App\Models\Payment;
use App\Models\User;
use App\Services\AvailabilityService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Ramsey\Uuid\Uuid;

/**
 * Client-facing booking flow on {username}.{root}/book: pick a package and an open slot,
 * leave contact details, then (on plans with deposits) approve a Mobile Money deposit.
 */
class PublicBookingController extends Controller
{
    /**
     * Session key listing the bookings this visitor created, so only they can see their status page.
     */
    private const SESSION_KEY = 'booking_checkouts';

    public function __construct(
        protected AvailabilityService $availability
    ) {}

    /**
     * Dedicated, shareable booking page.
     */
    public function page(Request $request, string $username): View
    {
        $photographer = $this->photographer($username);

        $packages = $photographer->packages()
            ->active()
            ->orderBy('sort_order', 'asc')
            ->get();

        $depositsEnabled = $photographer->hasFeature('booking_deposits');

        return view('public.book', [
            'photographer' => $photographer,
            'packages' => $packages,
            'depositsEnabled' => $depositsEnabled,
            'selectedPackage' => old('package_id', $request->query('package')),
            'timezone' => $this->timezone($photographer),
            'seo' => \App\Support\Seo\SeoMetadata::forPhotographer($photographer, $packages->all()),
        ]);
    }

    /**
     * Which days of a month have at least one open slot for a package.
     * GET /book/availability?package={uuid}&month=YYYY-MM
     */
    public function availability(Request $request, string $username): JsonResponse
    {
        $photographer = $this->photographer($username);
        $validated = $request->validate([
            'package' => ['required', 'string'],
            'month' => ['required', 'date_format:Y-m'],
        ]);

        $package = $this->package($photographer, $validated['package']);
        $tz = $this->timezone($photographer);
        $start = Carbon::createFromFormat('Y-m-d', $validated['month'] . '-01', $tz)->startOfDay();
        $end = $start->copy()->endOfMonth();

        $days = $this->availability->getAvailableDaysForMonth($photographer, $start, $end, $package);

        return response()->json([
            'timezone' => $tz,
            'days' => collect($days)->filter(fn ($d) => $d['available'])->keys()->values(),
        ]);
    }

    /**
     * Open start times for a package on one date.
     * GET /book/slots?package={uuid}&date=YYYY-MM-DD
     */
    public function slots(Request $request, string $username): JsonResponse
    {
        $photographer = $this->photographer($username);
        $validated = $request->validate([
            'package' => ['required', 'string'],
            'date' => ['required', 'date_format:Y-m-d'],
        ]);

        $package = $this->package($photographer, $validated['package']);
        $tz = $this->timezone($photographer);
        $date = Carbon::createFromFormat('Y-m-d', $validated['date'], $tz)->startOfDay();

        return response()->json([
            'timezone' => $tz,
            'slots' => $this->availability->getAvailableSlots($photographer, $date, $package)->values(),
        ]);
    }

    /**
     * Create the booking request and, when the package takes a deposit, start the Mobile Money payment.
     */
    public function book(Request $request, string $username, CreateBookingAction $createBookingAction, InitiateBookingDeposit $initiateDeposit): RedirectResponse
    {
        $photographer = $this->photographer($username);

        // Honeypot: real visitors never see or fill this field
        if (filled($request->input('website'))) {
            return back()->with('success', 'Thank you! Your booking request has been submitted successfully. The photographer will review and confirm shortly.');
        }

        $validated = $request->validate([
            'client_name' => ['required', 'string', 'max:255'],
            'client_email' => ['required', 'email', 'max:255'],
            'client_phone' => ['required', 'string', 'min:9', 'max:30', 'regex:/^[+0-9 ()-]+$/'],
            'package_id' => ['required', 'string'],
            'starts_at' => ['required', 'date'],
            'location' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'payment_phone' => ['nullable', 'string', 'min:9', 'max:30', 'regex:/^[+0-9 ()-]+$/'],
        ], [
            'client_phone.required' => 'Please add a phone or WhatsApp number so the photographer can reach you.',
        ]);

        $package = $photographer->packages()->active()->where('uuid', $validated['package_id'])->first();
        if (!$package) {
            return back()->withInput()->with('error', 'Please choose one of the available packages.');
        }

        $collectDeposit = $photographer->hasFeature('booking_deposits') && ($package->computedDepositAmount() ?? 0) > 0;

        // Returning clients keep one record per photographer; refresh their contact details
        $client = Client::firstOrNew([
            'user_id' => $photographer->id,
            'email' => strtolower(trim($validated['client_email'])),
        ]);
        $client->uuid ??= Uuid::uuid7()->toString();
        $client->name = $validated['client_name'];
        $client->phone = $validated['client_phone'];
        $client->save();

        $this->releaseAbandonedHolds($request, $photographer);

        try {
            $booking = $createBookingAction->execute($photographer, [
                'client_id' => $client->uuid,
                'package_id' => $package->uuid,
                'title' => "Booking: {$client->name}",
                'starts_at' => $validated['starts_at'],
                'location' => $validated['location'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'validate_availability' => true,
                'collect_deposit' => $collectDeposit,
                // With a deposit, the photographer and client hear about it once it's paid
                'notify' => !$collectDeposit,
                'status' => 'pending',
            ]);
        } catch (SlotUnavailableException|BookingOverlapException $e) {
            return back()->withInput()->with('error', 'That time is no longer available. Please choose another time.');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return back()->withInput()->with('error', 'Please choose one of the available packages.');
        } catch (\Throwable $e) {
            report($e);
            return back()->withInput()->with('error', 'We could not submit your booking. Please try again.');
        }

        $request->session()->push(self::SESSION_KEY, (string) $booking->uuid);
        $redirect = redirect()->route('subdomain.booking.show', ['username' => $photographer->username, 'booking' => (string) $booking->uuid]);

        if (!$collectDeposit) {
            return $redirect->with('success', 'Thank you! Your booking request has been submitted successfully. The photographer will review and confirm shortly.');
        }

        try {
            $initiateDeposit->execute($booking, $validated['payment_phone'] ?? $validated['client_phone']);
        } catch (\Throwable $e) {
            return $redirect->with('error', 'We could not start the Mobile Money payment: ' . $e->getMessage());
        }

        return $redirect;
    }

    /**
     * Confirmation / payment status page for a booking this visitor just made.
     */
    public function show(Request $request, string $username, string $booking): View
    {
        $photographer = $this->photographer($username);
        $booking = $this->ownedBooking($request, $photographer, $booking);

        return view('public.booking-status', [
            'photographer' => $photographer,
            'booking' => $booking->loadMissing(['package', 'client']),
            'payment' => $this->latestDeposit($booking),
        ]);
    }

    /**
     * Polled by the status page while the client approves the payment on their phone.
     */
    public function status(Request $request, string $username, string $booking, PaymentGateway $gateway, FinalizePawaPayPayment $finalizer): JsonResponse
    {
        $photographer = $this->photographer($username);
        $booking = $this->ownedBooking($request, $photographer, $booking);
        $payment = $this->latestDeposit($booking);

        // Ask PawaPay directly in case the callback is slow
        if ($payment && $payment->status === 'pending') {
            try {
                $statusData = $gateway->verifyDepositStatus($payment->pawapay_deposit_id);
                if ($statusData->found && ($statusData->isCompleted() || $statusData->isFailed())) {
                    $payment = $finalizer->execute($payment->pawapay_deposit_id, $statusData);
                }
            } catch (\Exception $e) {
                // Keep local state on API network error
            }
        }

        $booking->refresh();

        return response()->json([
            'booking_status' => $booking->status->value,
            'deposit_status' => $booking->deposit_status,
            'payment_status' => $payment?->status,
            'failure_reason' => in_array($payment?->status, ['failed', 'verification_failed'], true) ? ($payment->failure_reason ?? 'The payment was not completed.') : null,
            'hold_expires_at' => $booking->hold_expires_at?->toIso8601String(),
        ]);
    }

    /**
     * Try the deposit again (e.g. with another Mobile Money number) after a failed attempt.
     */
    public function pay(Request $request, string $username, string $booking, InitiateBookingDeposit $initiateDeposit): RedirectResponse
    {
        $photographer = $this->photographer($username);
        $booking = $this->ownedBooking($request, $photographer, $booking);

        $validated = $request->validate([
            'payment_phone' => ['required', 'string', 'min:9', 'max:30', 'regex:/^[+0-9 ()-]+$/'],
        ]);

        $back = redirect()->route('subdomain.booking.show', ['username' => $photographer->username, 'booking' => (string) $booking->uuid]);

        if ($booking->status !== BookingStatus::Pending || !in_array($booking->deposit_status, ['pending', 'failed'], true)) {
            return $back;
        }

        // The hold ran out: take the slot again only if nobody else did
        if (!$booking->hold_expires_at?->isFuture()) {
            $package = $booking->package;
            if (!$package || !$this->availability->isSlotAvailable($photographer, $booking->starts_at, $package)) {
                return $back->with('error', 'Sorry, this time is no longer available. Please choose another time.');
            }
            $booking->update(['hold_expires_at' => now()->addMinutes(Booking::DEPOSIT_HOLD_MINUTES)]);
        }

        try {
            $initiateDeposit->execute($booking, $validated['payment_phone']);
        } catch (\Throwable $e) {
            return $back->with('error', 'We could not start the Mobile Money payment: ' . $e->getMessage());
        }

        return $back;
    }

    protected function photographer(string $username): User
    {
        $photographer = User::where('username', strtolower($username))
            ->where('is_active', true)
            ->firstOrFail();

        // Hidden profiles only take bookings from their owner's preview
        abort_if($photographer->profile_public === false && auth()->id() !== $photographer->id, 404);

        return $photographer;
    }

    protected function package(User $photographer, string $uuid): Package
    {
        return $photographer->packages()->active()->where('uuid', $uuid)->firstOrFail();
    }

    protected function timezone(User $photographer): string
    {
        return $photographer->timezone ?: 'Africa/Kigali';
    }

    protected function ownedBooking(Request $request, User $photographer, string $uuid): Booking
    {
        abort_unless(in_array($uuid, $request->session()->get(self::SESSION_KEY, []), true), 404);

        return Booking::where('uuid', $uuid)
            ->where('user_id', $photographer->id)
            ->firstOrFail();
    }

    protected function latestDeposit(Booking $booking): ?Payment
    {
        return Payment::where('booking_id', $booking->id)
            ->where('purpose', 'booking_deposit')
            ->latest('id')
            ->first();
    }

    /**
     * A visitor starting over frees the slots of their own earlier attempts whose deposit failed,
     * so they can pick the same time again. Attempts with a payment still in flight are kept.
     */
    protected function releaseAbandonedHolds(Request $request, User $photographer): void
    {
        $uuids = $request->session()->get(self::SESSION_KEY, []);
        if (empty($uuids)) {
            return;
        }

        Booking::where('user_id', $photographer->id)
            ->whereIn('uuid', $uuids)
            ->where('status', BookingStatus::Pending->value)
            ->where('deposit_status', 'failed')
            ->update([
                'status' => BookingStatus::Cancelled->value,
                'deposit_status' => 'expired',
            ]);
    }
}
