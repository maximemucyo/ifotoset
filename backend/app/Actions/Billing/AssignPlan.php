<?php

namespace App\Actions\Billing;

use App\Models\AdminAuditLog;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\StorageStatisticsService;
use Illuminate\Support\Facades\DB;
use Ramsey\Uuid\Uuid;

class AssignPlan
{
    /**
     * Admin manually assigns a plan to a user with audit logging.
     */
    public function execute(
        User $admin,
        User $targetUser,
        Plan $newPlan,
        string $billingCycle = 'monthly',
        ?string $reason = null
    ): void {
        DB::transaction(function () use ($admin, $targetUser, $newPlan, $billingCycle, $reason) {
            $user = User::where('id', $targetUser->id)->lockForUpdate()->firstOrFail();
            $oldPlan = $user->plan;

            // Mark any current active subscription as replaced
            Subscription::where('user_id', $user->id)
                ->where('status', 'active')
                ->lockForUpdate()
                ->update(['status' => 'replaced']);

            if ($newPlan->slug === 'free') {
                $user->update(['plan_id' => $newPlan->id]);
            } else {
                $days = ($billingCycle === 'annual') ? 365 : 30;
                $startsAt = now();
                $endsAt = (clone $startsAt)->addDays($days);

                Subscription::create([
                    'uuid' => Uuid::uuid7()->toString(),
                    'user_id' => $user->id,
                    'plan_id' => $newPlan->id,
                    'provider' => 'admin_assigned',
                    'status' => 'active',
                    'billing_cycle' => $billingCycle,
                    'assigned_by' => $admin->id,
                    'starts_at' => $startsAt,
                    'ends_at' => $endsAt,
                ]);

                $user->update(['plan_id' => $newPlan->id]);
            }

            StorageStatisticsService::clearCache($user->id);

            AdminAuditLog::record(
                $admin,
                'subscription.plan_assigned',
                'user',
                (string) $user->id,
                [
                    'old_plan' => $oldPlan?->slug,
                    'new_plan' => $newPlan->slug,
                    'billing_cycle' => $billingCycle,
                    'reason' => $reason,
                ]
            );
        });
    }
}
