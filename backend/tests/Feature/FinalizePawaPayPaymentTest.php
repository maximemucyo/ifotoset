<?php

namespace Tests\Feature;

use App\Actions\Billing\FinalizePawaPayPayment;
use App\DTO\PaymentStatusData;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Ramsey\Uuid\Uuid;
use Tests\TestCase;

class FinalizePawaPayPaymentTest extends TestCase
{
    use RefreshDatabase;

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

        $this->user = User::create([
            'uuid' => Uuid::uuid7()->toString(),
            'name' => 'Jean Luc',
            'email' => 'jeanluc@test.com',
            'password' => bcrypt('password'),
            'plan_id' => $this->freePlan->id,
            'role' => 'photographer',
            'storage_used_bytes' => 1048576,
        ]);
    }

    public function test_successfully_finalizes_completed_payment_and_activates_subscription(): void
    {
        $depositId = Uuid::uuid4()->toString();

        $payment = Payment::create([
            'uuid' => Uuid::uuid7()->toString(),
            'user_id' => $this->user->id,
            'plan_id' => $this->proPlan->id,
            'purpose' => 'plan_subscription',
            'billing_cycle' => 'monthly',
            'amount' => 29999.00,
            'currency' => 'RWF',
            'phone_number' => '250788000000',
            'provider' => 'MTN_MOMO_RWA',
            'idempotency_key' => 'idem_test_1',
            'pawapay_deposit_id' => $depositId,
            'status' => 'pending',
        ]);

        $statusData = new PaymentStatusData(
            found: true,
            status: 'COMPLETED',
            depositId: $depositId,
            amount: 29999.00,
            currency: 'RWF',
            provider: 'MTN_MOMO_RWA',
            providerTransactionId: 'momo_tx_999'
        );

        $finalizer = app(FinalizePawaPayPayment::class);
        $finalized = $finalizer->execute($depositId, $statusData);

        $this->assertEquals('completed', $finalized->status);
        $this->assertNotNull($finalized->paid_at);

        // Verify user plan was upgraded
        $this->user->refresh();
        $this->assertEquals($this->proPlan->id, $this->user->plan_id);

        // Verify active subscription record was created
        $sub = Subscription::where('user_id', $this->user->id)->where('status', 'active')->first();
        $this->assertNotNull($sub);
        $this->assertEquals($this->proPlan->id, $sub->plan_id);
        $this->assertEquals('monthly', $sub->billing_cycle);
        $this->assertTrue($sub->ends_at > now());
    }

    public function test_amount_mismatch_sets_verification_failed_and_grants_no_entitlement(): void
    {
        $depositId = Uuid::uuid4()->toString();

        $payment = Payment::create([
            'uuid' => Uuid::uuid7()->toString(),
            'user_id' => $this->user->id,
            'plan_id' => $this->proPlan->id,
            'purpose' => 'plan_subscription',
            'billing_cycle' => 'monthly',
            'amount' => 29999.00,
            'currency' => 'RWF',
            'phone_number' => '250788000000',
            'provider' => 'MTN_MOMO_RWA',
            'idempotency_key' => 'idem_test_2',
            'pawapay_deposit_id' => $depositId,
            'status' => 'pending',
        ]);

        // Mismatched amount from PawaPay (e.g. only 10,000 paid)
        $statusData = new PaymentStatusData(
            found: true,
            status: 'COMPLETED',
            depositId: $depositId,
            amount: 10000.00,
            currency: 'RWF',
            provider: 'MTN_MOMO_RWA',
            providerTransactionId: 'momo_tx_mismatch'
        );

        $finalizer = app(FinalizePawaPayPayment::class);
        $finalized = $finalizer->execute($depositId, $statusData);

        $this->assertEquals('verification_failed', $finalized->status);
        $this->assertStringContainsString('differs from expected', $finalized->failure_reason);

        // User plan MUST NOT be upgraded
        $this->user->refresh();
        $this->assertEquals($this->freePlan->id, $this->user->plan_id);

        // No active subscription created
        $this->assertFalse(Subscription::where('user_id', $this->user->id)->where('status', 'active')->exists());
    }

    public function test_finalization_is_idempotent_on_duplicate_callbacks(): void
    {
        $depositId = Uuid::uuid4()->toString();

        $payment = Payment::create([
            'uuid' => Uuid::uuid7()->toString(),
            'user_id' => $this->user->id,
            'plan_id' => $this->proPlan->id,
            'purpose' => 'plan_subscription',
            'billing_cycle' => 'monthly',
            'amount' => 29999.00,
            'currency' => 'RWF',
            'phone_number' => '250788000000',
            'provider' => 'MTN_MOMO_RWA',
            'idempotency_key' => 'idem_test_3',
            'pawapay_deposit_id' => $depositId,
            'status' => 'pending',
        ]);

        $statusData = new PaymentStatusData(
            found: true,
            status: 'COMPLETED',
            depositId: $depositId,
            amount: 29999.00,
            currency: 'RWF',
            provider: 'MTN_MOMO_RWA'
        );

        $finalizer = app(FinalizePawaPayPayment::class);
        $first = $finalizer->execute($depositId, $statusData);
        $second = $finalizer->execute($depositId, $statusData);

        $this->assertEquals('completed', $first->status);
        $this->assertEquals('completed', $second->status);

        // Exactly one subscription record should exist
        $this->assertEquals(1, Subscription::where('user_id', $this->user->id)->count());
    }

    public function test_same_plan_renewal_extends_existing_expiry_without_losing_days(): void
    {
        // Existing active subscription ending in 15 days
        $futureEnd = now()->addDays(15);
        $existingSub = Subscription::create([
            'uuid' => Uuid::uuid7()->toString(),
            'user_id' => $this->user->id,
            'plan_id' => $this->proPlan->id,
            'provider' => 'pawapay',
            'status' => 'active',
            'billing_cycle' => 'monthly',
            'starts_at' => now()->subDays(15),
            'ends_at' => $futureEnd,
        ]);

        $depositId = Uuid::uuid4()->toString();
        $payment = Payment::create([
            'uuid' => Uuid::uuid7()->toString(),
            'user_id' => $this->user->id,
            'plan_id' => $this->proPlan->id,
            'purpose' => 'plan_subscription',
            'billing_cycle' => 'monthly',
            'amount' => 29999.00,
            'currency' => 'RWF',
            'phone_number' => '250788000000',
            'provider' => 'MTN_MOMO_RWA',
            'idempotency_key' => 'idem_test_renew',
            'pawapay_deposit_id' => $depositId,
            'status' => 'pending',
        ]);

        $statusData = new PaymentStatusData(
            found: true,
            status: 'COMPLETED',
            depositId: $depositId,
            amount: 29999.00,
            currency: 'RWF'
        );

        $finalizer = app(FinalizePawaPayPayment::class);
        $finalizer->execute($depositId, $statusData);

        $existingSub->refresh();
        // Should extend from the futureEnd date by 30 days (+45 days from now)
        $this->assertEquals(
            $futureEnd->copy()->addDays(30)->toDateString(),
            $existingSub->ends_at->toDateString()
        );
        $this->assertEquals(1, Subscription::where('user_id', $this->user->id)->count());
    }
}
