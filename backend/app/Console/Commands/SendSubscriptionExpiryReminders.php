<?php

namespace App\Console\Commands;

use App\Mail\SubscriptionExpiringMail;
use App\Models\Subscription;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendSubscriptionExpiryReminders extends Command
{
    protected $signature = 'subscriptions:send-expiry-reminders';

    protected $description = 'Email photographers 7 days and 1 day before a paid plan ends';

    public function handle(): int
    {
        $sent = 0;

        // Larger window first, so a subscription inside both windows only gets the 1-day mail
        foreach ([7 => 'expiry_reminder_7d_sent_at', 1 => 'expiry_reminder_1d_sent_at'] as $days => $column) {
            Subscription::query()
                ->with(['user', 'plan'])
                ->where('status', 'active')
                ->whereNull($column)
                ->where('ends_at', '>', now())
                ->where('ends_at', '<=', now()->addDays($days))
                ->when($days === 7, fn ($q) => $q->where('ends_at', '>', now()->addDay()))
                ->chunkById(100, function ($subscriptions) use ($days, $column, &$sent) {
                    foreach ($subscriptions as $subscription) {
                        // Claim first so overlapping runs never double-send
                        $claimed = Subscription::whereKey($subscription->id)->whereNull($column)->update([$column => now()]);
                        if (!$claimed || !$subscription->user?->email || !$subscription->user->is_active) {
                            continue;
                        }

                        // Skip if the user already renewed into a later subscription
                        $renewed = Subscription::where('user_id', $subscription->user_id)
                            ->where('status', 'active')
                            ->where('ends_at', '>', $subscription->ends_at)
                            ->exists();
                        if ($renewed) {
                            continue;
                        }

                        $daysLeft = max(1, (int) ceil(now()->diffInHours($subscription->ends_at) / 24));
                        Mail::to($subscription->user->email)->queue(new SubscriptionExpiringMail($subscription, min($days, $daysLeft)));
                        $sent++;
                    }
                });
        }

        $this->info("Queued {$sent} expiry reminders.");

        return self::SUCCESS;
    }
}
