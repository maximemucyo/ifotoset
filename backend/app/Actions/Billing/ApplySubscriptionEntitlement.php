<?php

namespace App\Actions\Billing;

use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use App\Services\StorageStatisticsService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Ramsey\Uuid\Uuid;

class ApplySubscriptionEntitlement
{
    /**
     * Applies subscription entitlement to user upon successful payment.
     * Must be called within an active DB transaction.
     */
    public function execute(Payment $payment): Subscription
    {
        $user = User::where('id', $payment->user_id)->lockForUpdate()->firstOrFail();
        $plan = $payment->plan;

        // Calculate subscription duration based on months paid
        $months = (int) ($payment->metadata['months'] ?? ($payment->billing_cycle === 'annual' ? 12 : 1));
        $daysToAdd = ($months === 12) ? 365 : ($months * 30);

        // Check for an existing active subscription for this user
        $currentSub = Subscription::where('user_id', $user->id)
            ->where('status', 'active')
            ->orderBy('ends_at', 'desc')
            ->lockForUpdate()
            ->first();

        if ($currentSub && $currentSub->plan_id === $plan->id && $currentSub->ends_at > now()) {
            // SAME-PLAN RENEWAL: Preserve unused days by extending from current expiry
            $newEndsAt = Carbon::parse($currentSub->ends_at)->addDays($daysToAdd);
            $currentSub->update([
                'ends_at' => $newEndsAt,
                'billing_cycle' => $payment->billing_cycle ?? $currentSub->billing_cycle,
                'payment_id' => $payment->id,
            ]);
            $subscription = $currentSub;

            Log::info("Renewed existing subscription ID {$currentSub->id} for user {$user->id} to {$newEndsAt}");
        } else {
            // PLAN UPGRADE or NEW SUBSCRIPTION
            if ($currentSub && $currentSub->ends_at > now()) {
                // Mark superseded subscription as replaced
                $currentSub->update(['status' => 'replaced']);
            }

            $startsAt = now();
            $endsAt = (clone $startsAt)->addDays($daysToAdd);

            $subscription = Subscription::create([
                'uuid' => Uuid::uuid7()->toString(),
                'user_id' => $user->id,
                'plan_id' => $plan->id,
                'provider' => 'pawapay',
                'provider_subscription_id' => $payment->pawapay_deposit_id,
                'status' => 'active',
                'billing_cycle' => $payment->billing_cycle ?? 'monthly',
                'payment_id' => $payment->id,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
            ]);

            Log::info("Created new subscription ID {$subscription->id} for user {$user->id} until {$endsAt}");
        }

        // Synchronize user plan entitlement
        $user->update([
            'plan_id' => $plan->id,
        ]);

        // Invalidate storage quota cache
        StorageStatisticsService::clearCache($user->id);

        return $subscription;
    }
}
