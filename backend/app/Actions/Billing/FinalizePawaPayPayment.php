<?php

namespace App\Actions\Billing;

use App\DTO\PaymentStatusData;
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
                    $booking = Booking::find($payment->booking_id);
                    if ($booking && $booking->status === \App\Enums\BookingStatus::Pending) {
                        $booking->update(['status' => \App\Enums\BookingStatus::Confirmed]);
                    }
                }

                // 6. Dispatch side-effects strictly after transaction commit
                DB::afterCommit(function () use ($payment) {
                    event(new PaymentCompleted($payment));
                });

                Log::info("Payment {$payment->id} successfully completed for user {$payment->user_id}");
            } elseif ($statusData->isFailed()) {
                $payment->update([
                    'status' => 'failed',
                    'provider_status' => $statusData->status,
                    'provider_transaction_id' => $statusData->providerTransactionId,
                    'failure_reason' => $statusData->failureReason ?? 'Transaction failed or rejected by provider',
                ]);

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
}
