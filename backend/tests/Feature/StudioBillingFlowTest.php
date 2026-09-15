<?php

namespace Tests\Feature;

use App\Contracts\PaymentGateway;
use App\DTO\PaymentStatusData;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Ramsey\Uuid\Uuid;
use Tests\TestCase;

class StudioBillingFlowTest extends TestCase
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
            'name' => 'Admin Boss',
            'email' => 'boss@ifotoset.com',
            'password' => bcrypt('password'),
            'plan_id' => $this->freePlan->id,
        ]);
        $this->admin->role = 'admin';
        $this->admin->save();

        $this->user = User::create([
            'uuid' => Uuid::uuid7()->toString(),
            'name' => 'Studio Shooter',
            'email' => 'shooter@ifotoset.com',
            'password' => bcrypt('password'),
            'plan_id' => $this->freePlan->id,
            'role' => 'photographer',
            'storage_used_bytes' => 1048576,
        ]);
    }

    public function test_photographer_can_view_studio_billing_matrix(): void
    {
        $response = $this->actingAs($this->user)->get('/studio/billing');

        $response->assertStatus(200);
        $response->assertSee('Subscription &amp; Storage Plans', false);
        $response->assertSee('Professional');
        $response->assertSee('Unlimited');
    }

    public function test_photographer_can_view_checkout_screen(): void
    {
        $response = $this->actingAs($this->user)->get('/studio/billing/checkout/pro?cycle=monthly');

        $response->assertStatus(200);
        $response->assertSee('Upgrade to Professional');
        $response->assertSee('29,999 RWF');
    }

    public function test_photographer_can_initiate_payment_and_poll_status_to_completion(): void
    {
        $mockGateway = Mockery::mock(PaymentGateway::class);
        $mockGateway->shouldReceive('initiateDeposit')
            ->once()
            ->andReturn(['status' => 'ACCEPTED']);

        $this->app->instance(PaymentGateway::class, $mockGateway);

        // 1. Initiate
        $initResponse = $this->actingAs($this->user)->postJson('/studio/billing/initiate', [
            'plan_slug' => 'pro',
            'billing_cycle' => 'monthly',
            'phone_number' => '0788123456',
            'provider' => 'MTN_MOMO_RWA',
        ]);

        $initResponse->assertStatus(200);
        $initResponse->assertJson(['success' => true]);
        $paymentUuid = $initResponse->json('payment_uuid');
        $depositId = $initResponse->json('deposit_id');

        $this->assertDatabaseHas('payments', [
            'uuid' => Uuid::fromString($paymentUuid)->getBytes(),
            'status' => 'pending',
            'billing_cycle' => 'monthly',
        ]);

        // 2. Mock Gateway status check returning COMPLETED
        $mockGateway->shouldReceive('verifyDepositStatus')
            ->with($depositId)
            ->once()
            ->andReturn(new PaymentStatusData(
                found: true,
                status: 'COMPLETED',
                depositId: $depositId,
                amount: 29999.00,
                currency: 'RWF',
                provider: 'MTN_MOMO_RWA',
                providerTransactionId: 'prov_9988'
            ));

        // 3. Poll status
        $pollResponse = $this->actingAs($this->user)->getJson('/studio/billing/check/' . $paymentUuid);

        $pollResponse->assertStatus(200);
        $pollResponse->assertJson([
            'status' => 'completed',
            'is_completed' => true,
            'is_failed' => false,
        ]);

        // Verify account upgraded
        $this->user->refresh();
        $this->assertEquals($this->proPlan->id, $this->user->plan_id);

        // 4. View printable receipt
        $receiptResponse = $this->actingAs($this->user)->get('/studio/billing/receipt/' . $paymentUuid);
        $receiptResponse->assertStatus(200);
        $receiptResponse->assertSee('Subscription Payment Receipt');
        $receiptResponse->assertSee('Professional Plan');
    }

    public function test_photographer_can_pay_for_multiple_months_at_once(): void
    {
        $mockGateway = Mockery::mock(PaymentGateway::class);
        $mockGateway->shouldReceive('initiateDeposit')
            ->once()
            ->with(Mockery::on(function ($data) {
                return $data['amount'] == (29999.00 * 3) && str_contains($data['description'], '3 months');
            }))
            ->andReturn(['status' => 'ACCEPTED']);

        $this->app->instance(PaymentGateway::class, $mockGateway);

        // 1. Checkout view with months parameter
        $checkoutResponse = $this->actingAs($this->user)->get('/studio/billing/checkout/pro?months=3');
        $checkoutResponse->assertStatus(200);
        $checkoutResponse->assertSee('Upgrade to Professional');
        $checkoutResponse->assertSee('Subscription Duration');
        $checkoutResponse->assertSee('+250');
        $checkoutResponse->assertDontSee('256-bit SSL');
        $checkoutResponse->assertDontSee('PawaPay Rwanda Mobile Money gateway');

        // 2. Initiate payment for 3 months
        $initResponse = $this->actingAs($this->user)->postJson('/studio/billing/initiate', [
            'plan_slug' => 'pro',
            'months' => 3,
            'phone_number' => '0788123456',
            'provider' => 'MTN_MOMO_RWA',
        ]);

        $initResponse->assertStatus(200);
        $initResponse->assertJson([
            'success' => true,
            'amount' => 89997.00,
            'months' => 3,
        ]);
        $paymentUuid = $initResponse->json('payment_uuid');
        $depositId = $initResponse->json('deposit_id');

        // Verify payment stored
        $payment = Payment::where('uuid', Uuid::fromString($paymentUuid)->getBytes())->first();
        $this->assertNotNull($payment);
        $this->assertEquals(89997.00, (float) $payment->amount);
        $this->assertEquals(3, $payment->metadata['months']);

        // 3. Mock completed callback / status check
        $mockGateway->shouldReceive('verifyDepositStatus')
            ->with($depositId)
            ->once()
            ->andReturn(new PaymentStatusData(
                found: true,
                status: 'COMPLETED',
                depositId: $depositId,
                amount: 89997.00,
                currency: 'RWF',
                provider: 'MTN_MOMO_RWA',
                providerTransactionId: 'tx_multimonth_123'
            ));

        $pollResponse = $this->actingAs($this->user)->getJson('/studio/billing/check/' . $paymentUuid);
        $pollResponse->assertStatus(200);
        $pollResponse->assertJson(['is_completed' => true]);

        // Verify subscription duration is ~90 days
        $sub = Subscription::where('user_id', $this->user->id)->where('status', 'active')->first();
        $this->assertNotNull($sub);
        $days = (int) round(now()->diffInDays($sub->ends_at));
        $this->assertGreaterThanOrEqual(89, $days);
        $this->assertLessThanOrEqual(91, $days);

        // 4. Verify receipt shows 3 Months
        $receiptResponse = $this->actingAs($this->user)->get('/studio/billing/receipt/' . $paymentUuid);
        $receiptResponse->assertStatus(200);
        $receiptResponse->assertSee('3 Months');
        $receiptResponse->assertSee('89,997 RWF');
        $receiptResponse->assertSee('MTN Mobile Money');
        $receiptResponse->assertDontSee('pawapay');
    }

    public function test_photographer_can_initiate_with_formatted_billing_cycle_string(): void
    {
        $mockGateway = Mockery::mock(PaymentGateway::class);
        $mockGateway->shouldReceive('initiateDeposit')
            ->once()
            ->with(Mockery::on(function ($data) {
                return $data['amount'] == (29999.00 * 2) && str_contains($data['description'], '2 months');
            }))
            ->andReturn(['status' => 'ACCEPTED']);

        $this->app->instance(PaymentGateway::class, $mockGateway);

        $initResponse = $this->actingAs($this->user)->postJson('/studio/billing/initiate', [
            'plan_slug' => 'pro',
            'billing_cycle' => '2_months',
            'months' => 2,
            'phone_number' => '0788123456',
            'provider' => 'MTN_MOMO_RWA',
        ]);

        $initResponse->assertStatus(200);
        $initResponse->assertJson([
            'success' => true,
            'amount' => 59998.00,
            'months' => 2,
        ]);
    }

    public function test_admin_can_assign_and_revoke_plan_via_web_routes(): void
    {
        // 1. Assign Pro
        $assignResponse = $this->actingAs($this->admin)->post("/admin/users/{$this->user->id}/plan", [
            'plan_slug' => 'pro',
            'billing_cycle' => 'annual',
            'reason' => 'Annual sponsored creator',
        ]);

        $assignResponse->assertRedirect();
        $this->user->refresh();
        $this->assertEquals($this->proPlan->id, $this->user->plan_id);

        $sub = Subscription::where('user_id', $this->user->id)->where('status', 'active')->first();
        $this->assertNotNull($sub);
        $this->assertEquals('annual', $sub->billing_cycle);

        // 2. Revoke Pro back to Free
        $revokeResponse = $this->actingAs($this->admin)->post("/admin/users/{$this->user->id}/plan/revoke", [
            'reason' => 'Contract ended early',
        ]);

        $revokeResponse->assertRedirect();
        $this->user->refresh();
        $this->assertEquals($this->freePlan->id, $this->user->plan_id);
    }

    public function test_studio_dashboard_displays_current_plan_and_upgrade_button_on_free_tier(): void
    {
        // 1. User on Free plan
        \App\Services\StorageStatisticsService::clearCache($this->user->id);
        $response = $this->actingAs($this->user)->get('/studio/dashboard');
        $response->assertStatus(200);
        $response->assertSee('Storage Used');
        $response->assertSee('Free Plan');
        $response->assertSee('Upgrade');
        $response->assertSee(route('studio.billing.index'));

        // 2. User on Paid plan (Pro)
        $this->user->update(['plan_id' => $this->proPlan->id]);
        $this->user->refresh();
        \App\Services\StorageStatisticsService::clearCache($this->user->id);
        $paidResponse = $this->actingAs($this->user)->get('/studio/dashboard');
        $paidResponse->assertStatus(200);
        $paidResponse->assertSee('Professional Plan');
        $paidResponse->assertDontSee('Upgrade');
    }
}
