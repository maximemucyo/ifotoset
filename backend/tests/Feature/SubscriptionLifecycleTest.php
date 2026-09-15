<?php

namespace Tests\Feature;

use App\Actions\Billing\AssignPlan;
use App\Actions\Billing\ExpireSubscriptions;
use App\Actions\Billing\RevokePlan;
use App\Models\AdminAuditLog;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Ramsey\Uuid\Uuid;
use Tests\TestCase;

class SubscriptionLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $user;
    protected Plan $freePlan;
    protected Plan $proPlan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->freePlan = Plan::create([
            'uuid' => Uuid::uuid7()->toString(),
            'slug' => 'free',
            'name' => 'Free',
            'monthly_price' => 0.00,
            'annual_price' => 0.00,
            'currency' => 'RWF',
            'storage_limit' => 2147483648,
            'gallery_limit' => null,
            'video_limit' => 0,
            'team_limit' => 0,
        ]);

        $this->proPlan = Plan::create([
            'uuid' => Uuid::uuid7()->toString(),
            'slug' => 'pro',
            'name' => 'Professional',
            'monthly_price' => 29999.00,
            'annual_price' => 299988.00,
            'currency' => 'RWF',
            'storage_limit' => 1000000000000,
            'gallery_limit' => null,
            'video_limit' => 18000,
            'team_limit' => 1,
        ]);

        $this->admin = User::create([
            'uuid' => Uuid::uuid7()->toString(),
            'name' => 'Admin User',
            'email' => 'admin@test.com',
            'password' => bcrypt('password'),
            'plan_id' => $this->freePlan->id,
            'role' => 'admin',
        ]);

        $this->user = User::create([
            'uuid' => Uuid::uuid7()->toString(),
            'name' => 'Photographer User',
            'email' => 'photo@test.com',
            'password' => bcrypt('password'),
            'plan_id' => $this->freePlan->id,
            'role' => 'photographer',
            'storage_used_bytes' => 500000000, // 500 MB used
        ]);
    }

    public function test_admin_can_assign_plan_and_creates_audit_log(): void
    {
        $action = app(AssignPlan::class);
        $action->execute($this->admin, $this->user, $this->proPlan, 'monthly', 'Complimentary VIP partner');

        $this->user->refresh();
        $this->assertEquals($this->proPlan->id, $this->user->plan_id);

        $sub = Subscription::where('user_id', $this->user->id)->where('status', 'active')->first();
        $this->assertNotNull($sub);
        $this->assertEquals($this->admin->id, $sub->assigned_by);
        $this->assertEquals($this->proPlan->id, $sub->plan_id);

        // Verify audit log
        $log = AdminAuditLog::where('action', 'subscription.plan_assigned')->first();
        $this->assertNotNull($log);
        $this->assertEquals($this->admin->id, $log->admin_user_id);
        $this->assertEquals((string) $this->user->id, $log->target_id);
        $this->assertEquals('pro', $log->metadata['new_plan']);
    }

    public function test_admin_can_revoke_plan_and_reverts_user_to_free_tier(): void
    {
        // First assign
        app(AssignPlan::class)->execute($this->admin, $this->user, $this->proPlan, 'monthly');
        $this->assertEquals($this->proPlan->id, $this->user->fresh()->plan_id);

        // Now revoke
        app(RevokePlan::class)->execute($this->admin, $this->user, 'Partner agreement expired');

        $this->user->refresh();
        $this->assertEquals($this->freePlan->id, $this->user->plan_id);

        // Subscription should be marked revoked, not deleted
        $sub = Subscription::where('user_id', $this->user->id)->first();
        $this->assertEquals('revoked', $sub->status);
        $this->assertEquals($this->admin->id, $sub->revoked_by);
        $this->assertNotNull($sub->revoked_at);

        // Verify audit log
        $log = AdminAuditLog::where('action', 'subscription.plan_revoked')->first();
        $this->assertNotNull($log);
        $this->assertEquals('Partner agreement expired', $log->metadata['reason']);
    }

    public function test_expire_subscriptions_action_reverts_expired_users_to_free_tier(): void
    {
        // User with an active subscription that expired yesterday
        Subscription::create([
            'uuid' => Uuid::uuid7()->toString(),
            'user_id' => $this->user->id,
            'plan_id' => $this->proPlan->id,
            'provider' => 'pawapay',
            'status' => 'active',
            'billing_cycle' => 'monthly',
            'starts_at' => now()->subDays(31),
            'ends_at' => now()->subDay(),
        ]);
        $this->user->update(['plan_id' => $this->proPlan->id]);

        $action = app(ExpireSubscriptions::class);
        $count = $action->execute();

        $this->assertEquals(1, $count);

        $this->user->refresh();
        $this->assertEquals($this->freePlan->id, $this->user->plan_id);

        $sub = Subscription::where('user_id', $this->user->id)->first();
        $this->assertEquals('expired', $sub->status);
    }
}
