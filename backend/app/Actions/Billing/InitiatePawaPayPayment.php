<?php

namespace App\Actions\Billing;

use App\Contracts\PaymentGateway;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\User;
use Exception;
use Illuminate\Support\Facades\DB;
use Ramsey\Uuid\Uuid;

class InitiatePawaPayPayment
{
    public function __construct(
        protected PaymentGateway $gateway
    ) {}

    /**
     * Initiates a PawaPay subscription payment.
     *
     * @throws Exception
     */
    public function execute(
        User $user,
        Plan $plan,
        string $billingCycle,
        string $phoneNumber,
        ?string $provider = null,
        ?string $idempotencyKey = null,
        int $months = 1
    ): Payment {
        $months = max(1, $months);
        if (in_array(strtolower($billingCycle), ['annual', 'yearly'])) {
            $months = 12;
        }

        if ($months === 12 && (float) $plan->annual_price > 0) {
            $amount = (float) $plan->annual_price;
            $billingCycle = 'annual';
        } else {
            $amount = (float) $plan->monthly_price * $months;
            $billingCycle = ($months === 1) ? 'monthly' : "{$months}_months";
        }
        $currency = $plan->currency ?? 'RWF';

        $idempotencyKey = $idempotencyKey ?: hash('sha256', "{$user->id}:{$plan->id}:{$billingCycle}:{$months}:" . floor(time() / 120));

        // Check if an identical payment was initiated in the last 2 minutes
        $existingPayment = Payment::where('user_id', $user->id)
            ->where('idempotency_key', $idempotencyKey)
            ->whereIn('status', ['created', 'pending'])
            ->first();

        if ($existingPayment) {
            return $existingPayment;
        }

        // PawaPay Contract explicitly requires standard RFC 4122 UUID v4
        $depositId = Uuid::uuid4()->toString();

        $payment = DB::transaction(function () use ($user, $plan, $billingCycle, $amount, $currency, $phoneNumber, $provider, $idempotencyKey, $depositId, $months) {
            return Payment::create([
                'uuid' => Uuid::uuid7()->toString(),
                'user_id' => $user->id,
                'plan_id' => $plan->id,
                'purpose' => 'plan_subscription',
                'billing_cycle' => $billingCycle,
                'amount' => $amount,
                'currency' => $currency,
                'phone_number' => $phoneNumber,
                'provider' => $provider ?? 'MTN',
                'idempotency_key' => $idempotencyKey,
                'pawapay_deposit_id' => $depositId,
                'status' => 'created',
                'metadata' => [
                    'months' => $months,
                ],
            ]);
        });

        try {
            $durationLabel = $months === 1 ? '1 month' : ($months === 12 ? '1 year' : "{$months} months");
            $this->gateway->initiateDeposit([
                'deposit_id' => $depositId,
                'amount' => $amount,
                'currency' => $currency,
                'phone_number' => $phoneNumber,
                'provider' => $provider,
                'description' => "Subscription to {$plan->name} ({$durationLabel})",
            ]);

            $payment->update([
                'status' => 'pending',
            ]);

            return $payment;
        } catch (Exception $e) {
            $payment->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'failure_reason' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
