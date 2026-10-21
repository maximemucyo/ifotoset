<?php

namespace App\Listeners;

use App\Events\PaymentCompleted;
use App\Support\GoogleAnalytics;

/**
 * Reports completed plan subscriptions to GA4 as `purchase`.
 * Booking deposits are client money for the photographer, not platform revenue, so they are skipped.
 */
class TrackSubscriptionPurchase
{
    public function handle(PaymentCompleted $event): void
    {
        $payment = $event->payment;
        if ($payment->purpose !== 'plan_subscription' || !$payment->user) {
            return;
        }

        GoogleAnalytics::send(
            $payment->user,
            'purchase',
            [
                'transaction_id' => (string) $payment->uuid,
                'value' => (float) $payment->amount,
                'currency' => $payment->currency,
                'items' => [[
                    'item_id' => $payment->plan?->slug,
                    'item_name' => $payment->plan?->name,
                    'item_variant' => $payment->billing_cycle,
                    'price' => (float) $payment->amount,
                    'quantity' => 1,
                ]],
            ],
            clientId: $payment->metadata['ga_client_id'] ?? null,
        );
    }
}
