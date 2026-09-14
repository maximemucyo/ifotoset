<?php

namespace App\Listeners;

use App\Events\SubscriptionPaymentSucceeded;
use App\Mail\PlanUpgradedMail;
use App\Models\PaymentReceipt;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendPlanUpgradeReceipt implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Defer execution until the payment database transaction has committed.
     */
    public bool $afterCommit = true;

    public function handle(SubscriptionPaymentSucceeded $event): void
    {
        $user = $event->user;
        $payment = $event->payment;
        $plan = $user->plan;

        if (!$plan || empty($user->email)) {
            return;
        }

        // Idempotency barrier: Attempt unique insert for (payment_id, type)
        $receipt = null;
        try {
            $receipt = PaymentReceipt::create([
                'payment_id' => $payment->id,
                'type' => 'subscription_upgrade',
                'recipient_email' => $user->email,
                'queued_at' => now(),
            ]);
        } catch (UniqueConstraintViolationException $e) {
            Log::info("Plan upgrade receipt already queued/sent for payment #{$payment->id}. Skipping duplicate.");
            return;
        }

        try {
            Mail::to($user->email)->send(
                new PlanUpgradedMail($user, $payment, $plan)
            );
            $receipt->update(['sent_at' => now()]);
        } catch (\Throwable $e) {
            Log::error("Failed sending subscription upgrade receipt to [{$user->email}]: " . $e->getMessage());
        }
    }
}
