<?php

namespace App\Mail;

use App\Models\Subscription;

class SubscriptionExpiredMail extends NoticeMail
{
    public function __construct(public Subscription $subscription) {}

    protected function subjectLine(): string
    {
        return 'Your ifotoset plan has ended';
    }

    protected function notice(): array
    {
        $subscription = $this->subscription->loadMissing(['user', 'plan']);
        $plan = $subscription->plan?->name ?? 'paid plan';

        return [
            'eyebrow' => 'Subscription',
            'title' => 'Your plan has ended',
            'preheader' => "Your {$plan} has ended and your account is now on the Free plan.",
            'greeting' => $subscription->user?->name,
            'paragraphs' => [
                "Your {$plan} has ended and your account is now on the Free plan.",
                'Your galleries and photos are still online and your clients can keep viewing them. If you are above the Free plan limits, new uploads are paused until you upgrade or free up space.',
            ],
            'button' => ['label' => 'Choose a plan', 'url' => route('studio.billing.index')],
        ];
    }
}
