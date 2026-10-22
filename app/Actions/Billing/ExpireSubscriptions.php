<?php

namespace App\Actions\Billing;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\StorageStatisticsService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ExpireSubscriptions
{
    /**
     * Process expired subscriptions safely with row locking to prevent
     * concurrency race conditions against simultaneous renewal payments.
     *
     * @return int Number of subscriptions expired
     */
    public function execute(): int
    {
        $expiredCount = 0;
        $candidateSubs = Subscription::where('status', 'active')
            ->where('ends_at', '<=', now())
            ->get();

        $freePlan = Plan::where('slug', 'free')->first();

        foreach ($candidateSubs as $candidate) {
            DB::transaction(function () use ($candidate, $freePlan, &$expiredCount) {
                // 1. Lock subscription row
                $sub = Subscription::where('id', $candidate->id)->lockForUpdate()->first();

                // 2. Concurrency guard: Ensure still active and genuinely expired
                if (!$sub || $sub->status !== 'active' || $sub->ends_at > now()) {
                    return; // Renewed or altered concurrently
                }

                // 3. Lock user row
                $user = User::where('id', $sub->user_id)->lockForUpdate()->first();
                if (!$user) {
                    return;
                }

                // 4. Mark subscription as expired
                $sub->update(['status' => 'expired']);

                // 5. Check if user has another active subscription (e.g. concurrent upgrade/renewal)
                $hasOtherActive = Subscription::where('user_id', $user->id)
                    ->where('status', 'active')
                    ->where('ends_at', '>', now())
                    ->exists();

                if (!$hasOtherActive && $freePlan) {
                    $user->forceFill(['plan_id' => $freePlan->id])->setRelation('plan', $freePlan);

                    // Start the over-quota timeline now; the expiry email below explains it,
                    // so the daily enforcement run treats the 'started' notice as sent
                    if (!$user->over_quota_since && $user->isOverFreeQuota()) {
                        $user->forceFill(['over_quota_since' => now(), 'over_quota_notice' => 'started']);
                    }

                    $user->save();
                    StorageStatisticsService::clearCache($user->id);

                    if ($user->email && $user->is_active) {
                        DB::afterCommit(fn () => \Illuminate\Support\Facades\Mail::to($user->email)
                            ->queue(new \App\Mail\SubscriptionExpiredMail($sub)));
                    }
                    Log::info("User ID {$user->id} reverted to Free plan due to expired subscription ID {$sub->id}");
                }

                $expiredCount++;
            });
        }

        return $expiredCount;
    }
}
