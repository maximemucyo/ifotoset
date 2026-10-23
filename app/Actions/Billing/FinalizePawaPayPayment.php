<?php

namespace App\Actions\Billing;

use App\DTO\PaymentStatusData;
use App\Enums\BookingStatus;
use App\Events\PaymentCompleted;
use App\Models\Booking;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FinalizePawaPayPayment
{
    public function __construct(
        protected ApplySubscriptionEntitlement $applyEntitlement
    ) {}

    /**
     * Authoritatively finalize a PawaPay payment based on normalized status.
     * Webhook callbacks, checkout polling, admin manual sync, and scheduled
     * reconciliation all invoke this exact action.
     */
    public function execute(string $depositId, PaymentStatusData $statusData): Payment
    {
        return DB::transaction(function () use ($depositId, $statusData) {
            // 1. Lock payment row to guarantee atomic state transitions
            $payment = Payment::where('pawapay_deposit_id', $depositId)
                ->lockForUpdate()
                ->first();

            if (!$payment) {
                Log::warning("FinalizePawaPayPayment: payment not found for deposit: {$depositId}");
                throw new \Exception("Payment record not found for deposit ID: {$depositId}");
            }

            // 2. Idempotency protection: do not re-process already completed payments
            if ($payment->status === 'completed') {
                return $payment;
            }

            // 3. Security verification against catalog price & expected transaction
            if ($payment->purpose === 'plan_subscription' && $payment->plan) {
                $plan = $payment->plan;
                $months = (int) ($payment->metadata['months'] ?? ($payment->billing_cycle === 'annual' ? 12 : 1));
                if ($months === 12 && (float) $plan->annual_price > 0) {
                    $expectedCatalogPrice = (float) $plan->annual_price;
                } else {
                    $expectedCatalogPrice = (float) $plan->monthly_price * $months;
                }

                if (abs((float) $payment->amount - $expectedCatalogPrice) > 0.01) {
                    $payment->update([
                        'status' => 'verification_failed',
                        'failure_reason' => "Payment amount ({$payment->amount}) does not match plan catalog price ({$expectedCatalogPrice}) for {$months} month(s).",
                    ]);
                    Log::critical("SECURITY ALERT: Payment amount mismatch with catalog price for Payment ID: {$payment->id}", [
                        'expected' => $expectedCatalogPrice,
                        'actual' => $payment->amount,
                        'months' => $months,
                        'depositId' => $depositId,
                    ]);
                    return $payment;
                }
            }

            // Verify actual PawaPay paid amount and currency if PawaPay reports completed
            if ($statusData->isCompleted()) {
                $amountMismatch = abs((float) $payment->amount - (float) $statusData->amount) > 0.01;
                $currencyMismatch = strtoupper((string) $payment->currency) !== strtoupper((string) $statusData->currency);

                if ($amountMismatch || $currencyMismatch) {
                    $payment->update([
                        'status' => 'verification_failed',
                        'provider_status' => $statusData->status,
                        'provider_transaction_id' => $statusData->providerTransactionId,
                        'failure_reason' => "PawaPay completed amount ({$statusData->amount} {$statusData->currency}) differs from expected ({$payment->amount} {$payment->currency}).",
                    ]);
                    DB::afterCommit(fn () => event(new \App\Events\PaymentFailed($payment, amountMismatch: true)));
                    Log::critical("SECURITY ALERT: PawaPay paid amount/currency mismatch for Payment ID: {$payment->id}", [
                        'expected_amount' => $payment->amount,
                        'expected_currency' => $payment->currency,
                        'received_amount' => $statusData->amount,
                        'received_currency' => $statusData->currency,
                        'depositId' => $depositId,
                    ]);
                    return $payment;
                }

                // 4. Mark payment as completed
                $payment->update([
                    'status' => 'completed',
                    'provider' => $statusData->provider ?? $payment->provider,
                    'provider_status' => $statusData->status,
                    'provider_transaction_id' => $statusData->providerTransactionId,
                    'paid_at' => now(),
                    'failure_reason' => null,
                ]);

                // 5. Apply entitlement through single cohesive action
                if ($payment->purpose === 'plan_subscription') {
                    $this->applyEntitlement->execute($payment);
                } elseif ($payment->purpose === 'booking_deposit' && $payment->booking_id) {
                    $this->applyBookingDeposit($payment);
                }

                // 6. Dispatch side-effects strictly after transaction commit
                DB::afterCommit(function () use ($payment) {
                    event(new PaymentCompleted($payment));
                });

                Log::info("Payment {$payment->id} successfully completed for user {$payment->user_id}");
            } elseif ($statusData->isFailed()) {
                // Only announce the first transition to failed (callbacks and reconcile can repeat)
                $alreadyFailed = $payment->status === 'failed';

                $payment->update([
                    'status' => 'failed',
                    'provider_status' => $statusData->status,
                    'provider_transaction_id' => $statusData->providerTransactionId,
                    'failure_reason' => $statusData->failureReason ?? 'Transaction failed or rejected by provider',
                ]);

                if ($payment->purpose === 'booking_deposit' && $payment->booking_id) {
                    // The hold stays until it expires so the client can retry with another number
                    Booking::where('id', $payment->booking_id)
                        ->where('deposit_status', 'pending')
                        ->update(['deposit_status' => 'failed']);
                }

                if (!$alreadyFailed) {
                    DB::afterCommit(fn () => event(new \App\Events\PaymentFailed($payment)));
                }

                Log::warning("Payment {$payment->id} marked failed: {$statusData->failureReason}");
            } elseif ($statusData->isPending()) {
                $payment->update([
                    'status' => 'pending',
                    'provider_status' => $statusData->status,
                    'provider_transaction_id' => $statusData->providerTransactionId ?? $payment->provider_transaction_id,
                ]);
            }

            return $payment;
        });
    }

    /**
     * Mark the booking's deposit paid and confirm it. A payment that lands after the hold
     * expired reinstates the booking when the slot is still free; otherwise it is flagged
     * so the photographer can refund or rebook the client.
     */
    protected function applyBookingDeposit(Payment $payment): void
    {
        $booking = Booking::where('id', $payment->booking_id)->lockForUpdate()->first();
        if (!$booking) {
            return;
        }

        $updates = [
            'deposit_status' => 'paid',
            'deposit_paid_at' => now(),
            'hold_expires_at' => null,
        ];

        if ($booking->status === BookingStatus::Pending) {
            $updates['status'] = BookingStatus::Confirmed;
        } elseif ($booking->status === BookingStatus::Cancelled && $booking->deposit_status === 'expired') {
            $slotTaken = Booking::where('user_id', $booking->user_id)
                ->where('id', '!=', $booking->id)
                ->blocking()
                ->overlapping($booking->starts_at, $booking->ends_at)
                ->exists();

            if ($slotTaken) {
                Log::critical("Booking deposit paid after its hold expired and the slot was rebooked; refund due.", [
                    'booking_id' => $booking->id,
                    'payment_id' => $payment->id,
                ]);
            } else {
                $updates['status'] = BookingStatus::Confirmed;
            }
        }

        $booking->update($updates);
    }
}
