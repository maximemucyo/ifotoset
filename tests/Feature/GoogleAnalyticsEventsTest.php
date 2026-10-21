<?php

namespace Tests\Feature;

use App\Actions\Billing\FinalizePawaPayPayment;
use App\Contracts\PaymentGateway;
use App\DTO\PaymentStatusData;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Mockery;
use Ramsey\Uuid\Uuid;
use Tests\TestCase;

class GoogleAnalyticsEventsTest extends TestCase
{
    use RefreshDatabase;

    protected Plan $freePlan;
    protected Plan $proPlan;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.google_analytics.id' => 'G-HFSHFGP0LV', 'services.google_analytics.api_secret' => 'test-secret']);
        Http::fake(['www.google-analytics.com/*' => Http::response('', 204)]);

        $this->freePlan = Plan::create([
            'uuid' => Uuid::uuid7()->toString(),
            'slug' => 'free',
            'name' => 'Free',
            'monthly_price' => 0.00,
            'annual_price' => 0.00,
            'currency' => 'RWF',
            'storage_limit' => 2147483648,
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
            'video_limit' => 18000,
            'team_limit' => 1,
        ]);
    }

    private function photographer(): User
    {
        return User::forceCreate([
            'email_verified_at' => now(),
            'uuid' => Uuid::uuid7()->toString(),
            'name' => 'Studio Shooter',
            'email' => 'shooter@ifotoset.com',
            'password' => bcrypt('password'),
            'plan_id' => $this->freePlan->id,
            'role' => 'photographer',
        ]);
    }

    /**
     * @return list<array{0: HttpRequest, 1: array}> sent GA requests with their decoded payloads
     */
    private function sentEvents(string $name): array
    {
        return Http::recorded(fn (HttpRequest $r) => str_contains($r->url(), 'google-analytics.com/mp/collect')
            && in_array($name, array_column($r->data()['events'] ?? [], 'name'), true))
            ->map(fn ($pair) => [$pair[0], $pair[0]->data()])
            ->values()
            ->all();
    }

    public function test_sign_up_is_sent_with_the_visitors_ga_client_and_session(): void
    {
        $this->withUnencryptedCookies([
            '_ga' => 'GA1.1.1234567890.1700000000',
            '_ga_HFSHFGP0LV' => 'GS2.1.s1760000000$o3$g1$t1760000100$j60$l0$h0',
        ])->post('/register', [
            'name' => 'New Shooter',
            'username' => 'newshooter',
            'email' => 'new@ifotoset.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertRedirect();

        $sent = $this->sentEvents('sign_up');
        $this->assertCount(1, $sent);
        [$request, $payload] = $sent[0];

        $this->assertStringContainsString('measurement_id=G-HFSHFGP0LV', $request->url());
        $this->assertStringContainsString('api_secret=test-secret', $request->url());
        $this->assertSame('1234567890.1700000000', $payload['client_id']);
        $this->assertSame((string) User::where('email', 'new@ifotoset.com')->first()->uuid, $payload['user_id']);
        $this->assertSame('1760000000', $payload['events'][0]['params']['session_id']);
        $this->assertSame('email', $payload['events'][0]['params']['method']);
    }

    public function test_login_is_sent_and_falls_back_to_a_user_derived_client_id_without_cookie(): void
    {
        $user = $this->photographer();

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect();

        $sent = $this->sentEvents('login');
        $this->assertCount(1, $sent);
        $this->assertMatchesRegularExpression('/^\d+\.\d+$/', $sent[0][1]['client_id']);
        $this->assertArrayNotHasKey('session_id', $sent[0][1]['events'][0]['params']);
    }

    public function test_nothing_is_sent_without_an_api_secret(): void
    {
        config(['services.google_analytics.api_secret' => null]);
        $user = $this->photographer();

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect();

        Http::assertNothingSent();
    }

    public function test_checkout_and_purchase_share_the_client_id_captured_at_checkout(): void
    {
        $user = $this->photographer();
        $gateway = Mockery::mock(PaymentGateway::class);
        $gateway->shouldReceive('initiateDeposit')->once()->andReturn([]);
        $this->app->instance(PaymentGateway::class, $gateway);

        $this->actingAs($user)
            ->withCredentials()
            ->withUnencryptedCookies(['_ga' => 'GA1.1.555.1700000000'])
            ->postJson(route('studio.billing.initiate'), [
                'plan_slug' => 'pro',
                'months' => 1,
                'phone_number' => '250788000000',
            ])->assertOk();

        $checkout = $this->sentEvents('begin_checkout');
        $this->assertCount(1, $checkout);
        $this->assertSame('555.1700000000', $checkout[0][1]['client_id']);
        $this->assertEquals(29999, $checkout[0][1]['events'][0]['params']['value']);

        // Confirmation arrives by webhook: no browser cookies, so the stored client id is used
        $payment = Payment::where('user_id', $user->id)->firstOrFail();
        app(FinalizePawaPayPayment::class)->execute($payment->pawapay_deposit_id, new PaymentStatusData(
            found: true,
            status: 'COMPLETED',
            depositId: $payment->pawapay_deposit_id,
            amount: 29999.00,
            currency: 'RWF',
        ));

        $purchase = $this->sentEvents('purchase');
        $this->assertCount(1, $purchase);
        $params = $purchase[0][1]['events'][0]['params'];
        $this->assertSame('555.1700000000', $purchase[0][1]['client_id']);
        $this->assertSame((string) $payment->uuid, $params['transaction_id']);
        $this->assertEquals(29999, $params['value']);
        $this->assertSame('RWF', $params['currency']);
        $this->assertSame('pro', $params['items'][0]['item_id']);
    }
}
