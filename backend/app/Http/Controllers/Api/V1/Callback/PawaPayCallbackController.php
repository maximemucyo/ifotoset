<?php

namespace App\Http\Controllers\Api\V1\Callback;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\PaymentWebhook;
use App\Events\PaymentCompleted;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PawaPayCallbackController extends Controller
{
    /**
     * Handles PawaPay Mobile Money deposit callbacks.
     * POST /api/v1/callbacks/pawapay
     */
    public function handleCallback(Request $request): JsonResponse
    {
        $payload = $request->json()->all();
        $eventId = $request->header('X-Event-ID') ?? ($payload['eventId'] ?? null);

        if (!$eventId) {
            return response()->json(['error' => 'Missing event identifier.'], 400);
        }

        // 1. Replay protection: Check if this webhook event ID was already processed
        if (PaymentWebhook::where('event_id', $eventId)->exists()) {
            return response()->json(['message' => 'Event already processed.'], 200);
        }

        // 2. Persist the raw webhook payload for debugging and audit
        $webhook = PaymentWebhook::create([
            'provider' => 'pawapay',
            'event_id' => $eventId,
            'headers' => json_encode($request->headers->all()),
            'payload' => json_encode($payload),
        ]);

        // Validate payload parameters
        $depositId = $payload['depositId'] ?? null;
        $status = $payload['status'] ?? null;

        if (!$depositId || !$status) {
            return response()->json(['error' => 'Invalid callback payload structure.'], 422);
        }

        // 3. Locate corresponding transaction
        $payment = Payment::where('pawapay_deposit_id', $depositId)->first();
        if (!$payment) {
            Log::warning("PawaPay payment record not found for deposit: {$depositId}");
            return response()->json(['message' => 'Payment record not found.'], 200);
        }

        // 4. Update transaction status inside database transaction
        DB::transaction(function () use ($payment, $status, $payload) {
            // Map PawaPay status to internal payment states
            // PawaPay statuses: COMPLETED, FAILED, EXPIRED, CANCELLED
            $internalStatus = strtolower($status);

            $payment->update([
                'status' => $internalStatus,
                'error_message' => $payload['failureReason'] ?? null,
            ]);

            // 5. Fire PaymentCompleted event if status is completed
            if ($internalStatus === 'completed') {
                event(new PaymentCompleted($payment));
            }
        });

        return response()->json(['message' => 'Callback processed successfully.'], 200);
    }
}
?>
