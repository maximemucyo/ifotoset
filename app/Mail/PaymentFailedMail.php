<?php

namespace App\Mail;

use App\Models\Payment;

class PaymentFailedMail extends NoticeMail
{
    public function __construct(public Payment $payment) {}

    protected function subjectLine(): string
    {
        return 'Your ifotoset payment did not go through';
    }

    protected function notice(): array
    {
        $payment = $this->payment->loadMissing(['user', 'plan']);
        $amount = number_format((float) $payment->amount) . ' ' . $payment->currency;

        return [
            'eyebrow' => 'Billing',
            'title' => 'Payment not completed',
            'preheader' => "Your mobile money payment of {$amount} was not completed. No money was taken.",
            'greeting' => $payment->user?->name,
            'paragraphs' => [
                "Your mobile money payment of {$amount}" . ($payment->plan ? " for the {$payment->plan->name} plan" : '') . ' was not completed, so your plan has not changed.',
                'This usually means the request was declined on the phone, timed out, or the wallet balance was too low. You can try again at any time.',
            ],
            'details' => array_filter([
                'Amount' => $amount,
                'Reason' => $payment->failure_reason,
                'Date' => $payment->updated_at?->format('F j, Y g:i A'),
            ]),
            'button' => ['label' => 'Try again', 'url' => route('studio.billing.index')],
        ];
    }
}
