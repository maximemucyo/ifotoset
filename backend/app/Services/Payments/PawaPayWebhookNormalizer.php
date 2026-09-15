<?php

namespace App\Services\Payments;

use App\DTO\PaymentStatusData;
use Illuminate\Http\Request;

class PawaPayWebhookNormalizer
{
    /**
     * Normalize callback payload into a collection of PaymentStatusData objects.
     * PawaPay can send a single JSON object or an array of event objects.
     *
     * @return array{events: array<PaymentStatusData>, event_id: string, deposit_id: ?string, signature: ?string, payload_hash: string}
     */
    public function normalize(Request $request): array
    {
        $rawContent = $request->getContent();
        $payloadHash = hash('sha256', $rawContent);
        $signature = $request->header('X-PawaPay-Signature') 
            ?? $request->header('x-pawapay-signature')
            ?? $request->header('X-Signature');

        $input = $request->json()->all();
        if (empty($input)) {
            $input = json_decode($rawContent, true) ?? [];
        }

        $rawEvents = isset($input[0]) ? $input : [$input];
        $normalizedEvents = [];
        $primaryDepositId = null;

        foreach ($rawEvents as $event) {
            if (!is_array($event)) {
                continue;
            }

            $depositId = (string) ($event['depositId'] ?? $event['deposit_id'] ?? '');
            if (empty($depositId)) {
                continue;
            }

            if (!$primaryDepositId) {
                $primaryDepositId = $depositId;
            }

            $status = (string) ($event['status'] ?? 'UNKNOWN');
            $amount = (float) ($event['amount'] ?? 0);
            $currency = (string) ($event['currency'] ?? 'RWF');
            $provider = $event['provider'] ?? $event['correspondent'] ?? null;
            $providerTxId = $event['providerTransactionId'] ?? $event['provider_transaction_id'] ?? null;
            $failureReason = null;

            if (isset($event['failureReason'])) {
                $failureReason = is_array($event['failureReason'])
                    ? ($event['failureReason']['failureMessage'] ?? json_encode($event['failureReason']))
                    : (string) $event['failureReason'];
            }

            $normalizedEvents[] = new PaymentStatusData(
                found: true,
                status: strtoupper($status),
                depositId: $depositId,
                amount: $amount,
                currency: $currency,
                provider: $provider,
                providerTransactionId: $providerTxId,
                failureReason: $failureReason,
                raw: $event
            );
        }

        // Determine or derive event ID
        $eventId = $request->header('X-Event-ID') 
            ?? $request->header('x-event-id') 
            ?? ($input['eventId'] ?? null);

        if (!$eventId && $primaryDepositId) {
            // Deterministic event key derived from depositId + status + payload hash
            $firstStatus = $normalizedEvents[0]->status ?? 'UNKNOWN';
            $eventId = 'evt_' . hash('sha256', $primaryDepositId . ':' . $firstStatus . ':' . substr($payloadHash, 0, 16));
        }

        return [
            'events' => $normalizedEvents,
            'event_id' => $eventId ?? 'evt_' . substr($payloadHash, 0, 32),
            'deposit_id' => $primaryDepositId,
            'signature' => $signature,
            'payload_hash' => $payloadHash,
        ];
    }
}
