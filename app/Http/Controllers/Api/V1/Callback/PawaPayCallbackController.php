<?php

namespace App\Http\Controllers\Api\V1\Callback;

use App\Actions\Billing\FinalizePawaPayPayment;
use App\Contracts\PaymentGateway;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\PaymentWebhook;
use App\Services\Payments\PawaPayWebhookNormalizer;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PawaPayCallbackController extends Controller
{
    public function __construct(
        protected PawaPayWebhookNormalizer $normalizer,
        protected FinalizePawaPayPayment $finalizer,
        protected PaymentGateway $gateway
    ) {}

    /**
     * Handles PawaPay Mobile Money deposit callbacks (direct or relayed).
     * POST /api/v1/callbacks/pawapay
     *
     * The callback body is never trusted: it only tells us which deposit to look at.
     * Status, amount and currency always come from a fresh PawaPay status query.
     */
    public function handleCallback(Request $request): JsonResponse
    {
        $rawPayload = $request->getContent();
        $relaySecret = $request->header('X-Relay-Secret');
        $expectedRelaySecret = config('services.pawapay.relay_secret');

        // 1. If arriving via relay bridge, authenticate the relay source first
        $isRelayed = !empty($relaySecret);
        if ($isRelayed) {
            if (!$expectedRelaySecret || !hash_equals((string) $expectedRelaySecret, (string) $relaySecret)) {
                Log::warning("PawaPay webhook relay authentication failed from IP: {$request->ip()}");
                return response()->json(['error' => 'Unauthorized relay secret.'], 401);
            }
        }

        // 2. Normalize payload & extract events, signatures, and derived event ID
        $normalizedData = $this->normalizer->normalize($request);
        $events = $normalizedData['events'];
        $eventId = $normalizedData['event_id'];
        $depositId = $normalizedData['deposit_id'];
        $signature = $normalizedData['signature'];
        $payloadHash = $normalizedData['payload_hash'];

        // 3. Cryptographic PawaPay signature verification if configured
        $pawapayWebhookSecret = config('services.pawapay.webhook_secret');
        if (!$isRelayed && empty($pawapayWebhookSecret) && config('services.pawapay.require_callback_auth')) {
            Log::warning("PawaPay webhook rejected: no relay secret or signature from IP: {$request->ip()}");
            return response()->json(['error' => 'Unauthenticated callback.'], 401);
        }

        if (!$isRelayed && !empty($pawapayWebhookSecret)) {
            if (empty($signature)) {
                Log::warning("PawaPay webhook missing signature header from IP: {$request->ip()}");
                return response()->json(['error' => 'Missing cryptographic signature.'], 401);
            }

            $computedSignature = hash_hmac('sha256', $rawPayload, $pawapayWebhookSecret);
            if (!hash_equals($computedSignature, $signature)) {
                Log::warning("PawaPay cryptographic signature mismatch.", [
                    'signature_received' => $signature,
                    'ip' => $request->ip(),
                ]);
                return response()->json(['error' => 'Invalid cryptographic signature.'], 403);
            }
        }

        // 4. Replay protection based on unique Event ID
        $existingWebhook = PaymentWebhook::where('event_id', $eventId)->first();
        if ($existingWebhook && $existingWebhook->processing_status === 'completed') {
            return response()->json(['status' => 'success', 'message' => 'Event already processed.'], 200);
        }

        // 5. Persist the webhook record
        $webhookRecord = $existingWebhook ?: PaymentWebhook::create([
            'provider' => 'pawapay',
            'event_id' => $eventId,
            'deposit_id' => $depositId,
            'signature' => $signature,
            'payload_hash' => $payloadHash,
            'headers' => $request->headers->all(),
            'payload' => $request->json()->all() ?: json_decode($rawPayload, true) ?: [],
            'processing_status' => 'processing',
            'received_at' => now(),
        ]);

        if (empty($events)) {
            $webhookRecord->update(['processing_status' => 'empty_payload', 'processed_at' => now()]);
            return response()->json(['status' => 'ignored', 'message' => 'No deposit events in payload.'], 200);
        }

        // 6. Re-fetch each deposit from PawaPay and finalize on the provider's answer only
        try {
            $settled = true;

            foreach ($events as $event) {
                $payment = Payment::where('pawapay_deposit_id', $event->depositId)->first();
                if (!$payment) {
                    Log::warning("PawaPay callback for unknown deposit: {$event->depositId}", ['ip' => $request->ip()]);
                    continue;
                }

                $statusData = $this->gateway->verifyDepositStatus($event->depositId);
                if (!$statusData->found) {
                    throw new Exception("Deposit {$event->depositId} could not be verified with PawaPay: {$statusData->failureReason}");
                }

                $payment = $this->finalizer->execute($event->depositId, $statusData);
                if (!in_array($payment->status, ['completed', 'failed', 'verification_failed'], true)) {
                    $settled = false;
                }
            }

            // Only a settled event is closed for replay, so an early or forged delivery
            // cannot block the genuine one that follows with the same event ID.
            $webhookRecord->update([
                'processing_status' => $settled ? 'completed' : 'pending_provider',
                'processed_at' => now(),
            ]);

            return response()->json(['status' => 'success', 'message' => 'Callback processed successfully.'], 200);
        } catch (Exception $e) {
            Log::error("Error processing PawaPay callback event: " . $e->getMessage(), [
                'event_id' => $eventId,
                'deposit_id' => $depositId,
            ]);

            $webhookRecord->update([
                'processing_status' => 'error',
                'processed_at' => now(),
            ]);

            return response()->json(['error' => 'Internal processing error.'], 500);
        }
    }
}
