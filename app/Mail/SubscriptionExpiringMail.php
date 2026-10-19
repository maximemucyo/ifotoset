<?php

namespace App\Mail;

use App\Models\Subscription;

class SubscriptionExpiringMail extends NoticeMail
{
    public function __construct(public Subscription $subscription, public int $daysLeft) {}

    protected function subjectLine(): string
    {
        $plan = $this->subscription->plan?->name ?? 'plan';

        return $this->daysLeft <= 1
            ? "Your ifotoset {$plan} ends tomorrow"
            : "Your ifotoset {$plan} ends in {$this->daysLeft} days";
    }

    protected function notice(): array
    {
        $subscription = $this->subscription->loadMissing(['user', 'plan']);
        $plan = $subscription->plan?->name ?? 'paid plan';
        $endsOn = $subscription->ends_at->format('F j, Y');

        return [
            'eyebrow' => 'Subscription',
            'title' => $this->daysLeft <= 1 ? 'Your plan ends tomorrow' : "Your plan ends in {$this->daysLeft} days",
            'preheader' => "Your {$plan} ends on {$endsOn}. Renew to keep your storage and features.",
            'greeting' => $subscription->user?->name,
            'paragraphs' => [
                "Your {$plan} ends on {$endsOn}. After that your account moves to the Free plan.",
                'Your galleries stay online, but uploads are paused if you are above the Free plan limits. Renew now to avoid any interruption.',
            ],
            'button' => ['label' => 'Renew plan', 'url' => route('studio.billing.index')],
        ];
    }
}
