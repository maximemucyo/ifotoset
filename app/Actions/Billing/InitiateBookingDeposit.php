<?php

namespace App\Actions\Billing;

use App\Contracts\PaymentGateway;
use App\Models\Booking;
use App\Models\Payment;
use Exception;
use Ramsey\Uuid\Uuid;

class InitiateBookingDeposit
{
    public function __construct(
        protected PaymentGateway $gateway
    ) {}

    /**
     * Ask the client's phone to approve the booking deposit through PawaPay.
     * The money goes to the photographer's booking; FinalizePawaPayPayment confirms it.
     *
     * @throws Exception when PawaPay refuses the request (the payment is kept as failed)
     */
    public function execute(Booking $booking, string $phoneNumber, ?string $provider = null): Payment
    {
        $amount = (float) $booking->deposit_amount;
        if ($amount <= 0) {
            throw new Exception('This booking has no deposit to pay.');
        }

        // A deposit already on its way blocks a second prompt to the client's phone
        $inFlight = Payment::where('booking_id', $booking->id)
            ->where('purpose', 'booking_deposit')
            ->whereIn('status', ['created', 'pending', 'completed'])
            ->latest('id')
            ->first();

        if ($inFlight) {
            return $inFlight;
        }

        // PawaPay Contract explicitly requires standard RFC 4122 UUID v4
        $depositId = Uuid::uuid4()->toString();

        $payment = Payment::create([
            'uuid' => Uuid::uuid7()->toString(),
            'user_id' => $booking->user_id,
            'booking_id' => $booking->id,
            'purpose' => 'booking_deposit',
            'billing_cycle' => 'one_time',
            'amount' => $amount,
            'currency' => $booking->currency,
            'phone_number' => $phoneNumber,
            'provider' => $provider ?? 'MTN',
            'idempotency_key' => "booking:{$booking->id}:{$depositId}",
            'pawapay_deposit_id' => $depositId,
            'status' => 'created',
        ]);

        try {
            $this->gateway->initiateDeposit([
                'deposit_id' => $depositId,
                'amount' => $amount,
                'currency' => $booking->currency,
                'phone_number' => $phoneNumber,
                'provider' => $provider,
                'description' => "Booking deposit: {$booking->title}",
            ]);

            $payment->update(['status' => 'pending']);
            $booking->update(['deposit_status' => 'pending']);

            return $payment;
        } catch (Exception $e) {
            $payment->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'failure_reason' => $e->getMessage(),
            ]);
            $booking->update(['deposit_status' => 'failed']);
            throw $e;
        }
    }
}
