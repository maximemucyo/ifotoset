<?php

namespace App\Mail;

use App\Enums\OverQuotaStage;
use App\Models\Subscription;
use Illuminate\Support\Number;

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

        $user = $subscription->user;

        $notice = [
            'eyebrow' => 'Subscription',
            'title' => 'Your plan has ended',
            'preheader' => "Your {$plan} has ended and your account is now on the Free plan.",
            'greeting' => $user?->name,
            'paragraphs' => [
                "Your {$plan} has ended and your account is now on the Free plan.",
                'Your galleries and photos are still online and your clients can keep viewing them.',
            ],
            'button' => ['label' => 'Choose a plan', 'url' => route('studio.billing.index')],
        ];

        if ($user?->over_quota_since && $user->isOverFreeQuota()) {
            $limit = Number::fileSize((int) $user->plan->storage_limit, 1);
            $used = Number::fileSize((int) $user->storage_used_bytes, 1);
            $dates = OverQuotaStage::dates($user->over_quota_since);

            $notice['paragraphs'][1] = "You are using {$used}, more than the Free plan's {$limit}, so new uploads are paused. Upgrade, or delete photos or galleries until you are within the limit, to keep everything online. Otherwise:";
            $notice['detailsTitle'] = 'What happens next';
            $notice['details'] = [
                $dates['restrict']->format('F j, Y') => 'New galleries and client downloads pause',
                $dates['archive']->format('F j, Y') => 'Your galleries go offline for clients',
                $dates['delete']->format('F j, Y') => 'Your oldest galleries are permanently deleted until you are within the limit',
            ];
        }

        return $notice;
    }
}
