<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\PaymentWebhook;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Ramsey\Uuid\Uuid;
use Tests\TestCase;

class PawaPayCallbackTest extends TestCase
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
            'name' => 'Callback Tester',
            'email' => 'callback@test.com',
            'password' => bcrypt('password'),
            'plan_id' => $this->freePlan->id,
            'role' => 'photographer',
            'storage_used_bytes' => 0,
        ]);
    }

    public function test_handles_valid_direct_callback(): void
    {
        $depositId = Uuid::uuid4()->toString();

        Payment::create([
            'uuid' => Uuid::uuid7()->toString(),
            'user_id' => $this->user->id,
            'plan_id' => $this->proPlan->id,
            'purpose' => 'plan_subscription',
            'billing_cycle' => 'monthly',
            'amount' => 29999.00,
            'currency' => 'RWF',
            'phone_number' => '250788000000',
            'provider' => 'MTN_MOMO_RWA',
            'idempotency_key' => 'cb_idem_1',
            'pawapay_deposit_id' => $depositId,
            'status' => 'pending',
        ]);

        $payload = [
            'depositId' => $depositId,
            'status' => 'COMPLETED',
            'amount' => 29999.00,
            'currency' => 'RWF',
            'providerTransactionId' => 'prov_tx_123',
        ];

        $response = $this->postJson('/api/v1/callbacks/pawapay', $payload, [
            'X-Event-ID' => 'evt_direct_1',
        ]);

        $response->assertStatus(200);
        $response->assertJson(['status' => 'success']);

        $this->user->refresh();
        $this->assertEquals($this->proPlan->id, $this->user->plan_id);

        $webhook = PaymentWebhook::where('event_id', 'evt_direct_1')->first();
        $this->assertNotNull($webhook);
        $this->assertEquals('completed', $webhook->processing_status);
    }

    public function test_handles_authenticated_relayed_callback(): void
    {
        $depositId = Uuid::uuid4()->toString();

        Payment::create([
            'uuid' => Uuid::uuid7()->toString(),
            'user_id' => $this->user->id,
            'plan_id' => $this->proPlan->id,
            'purpose' => 'plan_subscription',
            'billing_cycle' => 'monthly',
            'amount' => 29999.00,
            'currency' => 'RWF',
            'phone_number' => '250788000000',
            'provider' => 'MTN_MOMO_RWA',
            'idempotency_key' => 'cb_idem_2',
            'pawapay_deposit_id' => $depositId,
            'status' => 'pending',
        ]);

        $payload = [
            [
                'depositId' => $depositId,
                'status' => 'COMPLETED',
                'amount' => 29999.00,
                'currency' => 'RWF',
                'providerTransactionId' => 'prov_relay_456',
            ]
        ];

        $response = $this->postJson('/api/v1/callbacks/pawapay', $payload, [
            'X-Relay-Secret' => config('services.pawapay.relay_secret'),
            'X-Event-ID' => 'evt_relay_1',
        ]);

        $response->assertStatus(200);
        $response->assertJson(['status' => 'success']);

        $this->user->refresh();
        $this->assertEquals($this->proPlan->id, $this->user->plan_id);
    }

    public function test_rejects_unauthorized_relay_secret(): void
    {
        $payload = [
            'depositId' => Uuid::uuid4()->toString(),
            'status' => 'COMPLETED',
        ];

        $response = $this->postJson('/api/v1/callbacks/pawapay', $payload, [
            'X-Relay-Secret' => 'invalid_bogus_secret',
        ]);

        $response->assertStatus(401);
    }

    public function test_replay_protection_returns_200_without_reprocessing(): void
    {
        $depositId = Uuid::uuid4()->toString();

        Payment::create([
            'uuid' => Uuid::uuid7()->toString(),
            'user_id' => $this->user->id,
            'plan_id' => $this->proPlan->id,
            'purpose' => 'plan_subscription',
            'billing_cycle' => 'monthly',
            'amount' => 29999.00,
            'currency' => 'RWF',
            'phone_number' => '250788000000',
            'provider' => 'MTN_MOMO_RWA',
            'idempotency_key' => 'cb_idem_3',
            'pawapay_deposit_id' => $depositId,
            'status' => 'completed',
        ]);

        // Existing completed webhook
        PaymentWebhook::create([
            'provider' => 'pawapay',
            'event_id' => 'evt_replay_test',
            'deposit_id' => $depositId,
            'headers' => [],
            'payload' => [],
            'processing_status' => 'completed',
            'received_at' => now(),
            'processed_at' => now(),
        ]);

        $payload = [
            'depositId' => $depositId,
            'status' => 'COMPLETED',
            'amount' => 29999.00,
            'currency' => 'RWF',
        ];

        $response = $this->postJson('/api/v1/callbacks/pawapay', $payload, [
            'X-Event-ID' => 'evt_replay_test',
        ]);

        $response->assertStatus(200);
        $response->assertJson(['message' => 'Event already processed.']);
    }
}
