<?php

namespace App\Http\Controllers\Api\V1;

use App\Contracts\PaymentGateway;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Plan;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Ramsey\Uuid\Uuid;

class PaymentController extends Controller
{
    public function __construct(
        protected PaymentGateway $paymentGateway
    ) {}

    /**
     * Initiates MoMo payment.
     * POST /api/v1/payments/initiate
     */
    public function initiate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'plan_id' => ['required', 'string'],
            'amount' => ['required', 'numeric', 'min:1'],
            'phone_number' => ['required', 'string', 'max:30'],
            'provider' => ['required', 'string', 'max:50'],
            'idempotency_key' => ['required', 'string', 'max:128'],
        ]);

        $user = $request->user();
        $plan = Plan::where('uuid', $validated['plan_id'])->firstOrFail();

        // 1. Idempotency Check: Return existing payment if duplicate key found
        $existingPayment = Payment::where('user_id', $user->id)
            ->where('idempotency_key', $validated['idempotency_key'])
            ->first();

        if ($existingPayment) {
            return response()->json([
                'payment_uuid' => $existingPayment->uuid,
                'status' => $existingPayment->status,
            ], 200);
        }

        $depositUuid = Uuid::uuid7()->toString();

        try {
            $payment = DB::transaction(function () use ($user, $plan, $validated, $depositUuid) {
                // Create payment in 'created' state
                return Payment::create([
                    'uuid' => Uuid::uuid7()->toString(),
                    'user_id' => $user->id,
                    'plan_id' => $plan->id,
                    'amount' => $validated['amount'],
                    'currency' => $plan->currency,
                    'phone_number' => $validated['phone_number'],
                    'provider' => $validated['provider'],
                    'idempotency_key' => $validated['idempotency_key'],
                    'pawapay_deposit_id' => $depositUuid,
                    'status' => 'created',
                ]);
            });

            // 2. Initiate deposit request to PawaPay via abstraction service
            $this->paymentGateway->initiateDeposit([
                'deposit_id' => $depositUuid,
                'amount' => $payment->amount,
                'currency' => $payment->currency,
                'provider' => $payment->provider,
                'phone_number' => $payment->phone_number,
                'description' => "Subscription to Plan: {$plan->name}",
            ]);

            // Update payment to 'submitted' / 'pending' state
            $payment->update(['status' => 'pending']);

            // 3. Dispatch PollPaymentStatusJob here asynchronously (in production)

            return response()->json([
                'payment_uuid' => $payment->uuid,
                'status' => $payment->status,
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'code' => 'PAYMENT_INITIATION_FAILED',
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Get payment status.
     * GET /api/v1/payments/{uuid}/status
     */
    public function getStatus(Request $request, string $uuid): JsonResponse
    {
        $payment = Payment::where('uuid', $uuid)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        return response()->json([
            'uuid' => $payment->uuid,
            'amount' => $payment->amount,
            'currency' => $payment->currency,
            'phone_number' => $payment->phone_number,
            'provider' => $payment->provider,
            'status' => $payment->status,
            'error_message' => $payment->error_message,
        ]);
    }
}
?>
