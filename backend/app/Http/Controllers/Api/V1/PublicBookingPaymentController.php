<?php

namespace App\Http\Controllers\Api\V1;

use App\Contracts\PaymentGateway;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Ramsey\Uuid\Uuid;
use Exception;

class PublicBookingPaymentController extends Controller
{
    public function __construct(
        protected PaymentGateway $paymentGateway
    ) {}

    /**
     * Initiate a PawaPay deposit for an existing pending booking.
     *
     * POST /api/v1/public/bookings/{bookingUuid}/payments
     */
    public function store(Request $request, string $bookingUuid): JsonResponse
    {
        // Honeypot: reject if bots fill the _h field
        if ($request->filled('_h')) {
            return response()->json(['message' => 'Invalid request.'], 422);
        }

        $validated = $request->validate([
            'phone_number'    => ['required', 'string', 'max:30'],
            'provider'        => ['required', 'string', 'in:MTN,AIRTEL'],
            'idempotency_key' => ['required', 'string', 'max:128'],
            '_h'              => ['sometimes', 'string', 'max:0'], // honeypot
        ]);

        // Scope to pending bookings only (can't pay for confirmed/completed/cancelled)
        $booking = Booking::where('uuid', $bookingUuid)
            ->where('status', 'pending')
            ->with('package')
            ->firstOrFail();

        // Determine the deposit amount
        $depositAmount = null;
        if ($booking->package) {
            $depositAmount = $booking->package->computedDepositAmount();
        }

        // If the package has no deposit requirement, fall back to full booking price
        if (!$depositAmount && $booking->price) {
            $depositAmount = (float) $booking->price;
        }

        if (!$depositAmount || $depositAmount <= 0) {
            return response()->json([
                'code'    => 'NO_DEPOSIT_REQUIRED',
                'message' => 'This booking does not require a deposit.',
            ], 422);
        }

        $currency = $booking->currency ?? 'RWF';

        // Idempotency: return existing payment if same key submitted
        $existingPayment = Payment::where('booking_id', $booking->id)
            ->where('idempotency_key', $validated['idempotency_key'])
            ->publiclyAccessible()
            ->first();

        if ($existingPayment) {
            return response()->json([
                'payment_uuid' => $existingPayment->uuid,
                'status'       => $existingPayment->status,
                'amount'       => (float) $existingPayment->amount,
                'currency'     => $existingPayment->currency,
            ], 200);
        }

        $depositUuid = Uuid::uuid7()->toString();

        try {
            $payment = DB::transaction(function () use ($booking, $validated, $depositUuid, $depositAmount, $currency) {
                return Payment::create([
                    'uuid'               => Uuid::uuid7()->toString(),
                    'user_id'            => $booking->user_id,
                    'booking_id'         => $booking->id,
                    'purpose'            => 'booking_deposit',
                    'plan_id'            => null,
                    'amount'             => $depositAmount,
                    'currency'           => $currency,
                    'phone_number'       => $validated['phone_number'],
                    'provider'           => $validated['provider'],
                    'idempotency_key'    => $validated['idempotency_key'],
                    'pawapay_deposit_id' => $depositUuid,
                    'status'             => 'created',
                ]);
            });

            // Initiate PawaPay deposit
            $this->paymentGateway->initiateDeposit([
                'deposit_id'  => $depositUuid,
                'amount'      => $payment->amount,
                'currency'    => $payment->currency,
                'provider'    => $payment->provider,
                'phone_number'=> $payment->phone_number,
                'description' => "Deposit for booking: {$booking->title}",
            ]);

            $payment->update(['status' => 'pending']);

            return response()->json([
                'payment_uuid' => $payment->uuid,
                'status'       => $payment->status,
                'amount'       => (float) $payment->amount,
                'currency'     => $payment->currency,
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'code'    => 'PAYMENT_INITIATION_FAILED',
                'message' => mb_convert_encoding($e->getMessage(), 'UTF-8', 'UTF-8'),
            ], 400);
        }
    }

    /**
     * Poll status of a booking deposit payment.
     *
     * GET /api/v1/public/payments/{uuid}/status
     */
    public function getStatus(string $paymentUuid): JsonResponse
    {
        // Only expose booking deposit payments publicly
        $payment = Payment::where('uuid', $paymentUuid)
            ->publiclyAccessible()
            ->firstOrFail();

        return response()->json([
            'uuid'          => $payment->uuid,
            'amount'        => (float) $payment->amount,
            'currency'      => $payment->currency,
            'phone_number'  => $payment->phone_number,
            'provider'      => $payment->provider,
            'status'        => $payment->status,
            'error_message' => $payment->error_message,
        ]);
    }
}
