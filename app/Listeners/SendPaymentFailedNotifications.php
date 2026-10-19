<?php

namespace App\Listeners;

use App\Events\PaymentFailed;
use App\Mail\AdminAlertMail;
use App\Mail\PaymentFailedMail;
use App\Support\AdminAlerts;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Mail;

class SendPaymentFailedNotifications implements ShouldQueue
{
    use InteractsWithQueue;

    public bool $afterCommit = true;

    public function handle(PaymentFailed $event): void
    {
        $payment = $event->payment->loadMissing('user');
        $amount = number_format((float) $payment->amount) . ' ' . $payment->currency;

        // Plan payers are told so they can retry; booking clients see the result on the payment page
        if (!$event->amountMismatch && $payment->purpose === 'plan_subscription' && $payment->user?->email) {
            Mail::to($payment->user->email)->send(new PaymentFailedMail($payment));
        }

        AdminAlerts::send(new AdminAlertMail(
            headline: $event->amountMismatch ? 'Payment amount mismatch' : 'Payment failed',
            summary: $event->amountMismatch
                ? "PawaPay reported a completed payment whose amount or currency does not match payment #{$payment->id}. It was not applied."
                : "Payment #{$payment->id} of {$amount} failed.",
            details: array_filter([
                'Payment' => '#' . $payment->id,
                'User' => $payment->user ? "{$payment->user->name} ({$payment->user->email})" : null,
                'Purpose' => $payment->purpose,
                'Amount' => $amount,
                'Reason' => $payment->failure_reason,
            ]),
            actionUrl: route('admin.payments.index'),
            actionLabel: 'View payments',
        ));
    }
}
