<?php

namespace App\Actions\Billing;

use App\Models\AdminAuditLog;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\StorageStatisticsService;
use Illuminate\Support\Facades\DB;

class RevokePlan
{
    /**
     * Admin revokes a paid plan from a user, returning them to Free entitlement.
     */
    public function execute(
        User $admin,
        User $targetUser,
        ?string $reason = null
    ): void {
        DB::transaction(function () use ($admin, $targetUser, $reason) {
            $user = User::where('id', $targetUser->id)->lockForUpdate()->firstOrFail();
            $oldPlan = $user->plan;
            $freePlan = Plan::where('slug', 'free')->firstOrFail();

            // Mark active subscriptions as revoked (preserve historical record)
            Subscription::where('user_id', $user->id)
                ->where('status', 'active')
                ->lockForUpdate()
                ->update([
                    'status' => 'revoked',
                    'revoked_by' => $admin->id,
                    'revoked_at' => now(),
                ]);

            // Revert user to free plan
            $user->update(['plan_id' => $freePlan->id]);

            StorageStatisticsService::clearCache($user->id);

            AdminAuditLog::record(
                $admin,
                'subscription.plan_revoked',
                'user',
                (string) $user->id,
                [
                    'previous_plan' => $oldPlan?->slug,
                    'reverted_to' => 'free',
                    'reason' => $reason,
                ]
            );
        });
    }
}
