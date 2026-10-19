<?php

namespace Tests\Feature;

use App\Events\PaymentFailed;
use App\Mail\AdminAlertMail;
use App\Mail\BookingReminderMail;
use App\Mail\GalleryActivityDigestMail;
use App\Mail\PasswordChangedMail;
use App\Mail\PaymentFailedMail;
use App\Mail\SubscriptionExpiredMail;
use App\Mail\SubscriptionExpiringMail;
use App\Mail\WelcomeMail;
use App\Models\Booking;
use App\Models\Client;
use App\Models\Gallery;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Ramsey\Uuid\Uuid;
use Tests\TestCase;

class TransactionalEmailTest extends TestCase
{
    use RefreshDatabase;

    protected Plan $freePlan;
    protected Plan $proPlan;
    protected User $user;
    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        try {
            \Illuminate\Support\Facades\Redis::connection()->flushdb();
        } catch (\Throwable $e) {
        }

        $this->freePlan = Plan::firstOrCreate(['slug' => 'free'], [
            'uuid' => Uuid::uuid7()->toString(), 'name' => 'Free Plan',
            'storage_limit' => 5 * 1024 ** 3, 'video_limit' => 0, 'gallery_limit' => 3, 'team_limit' => 0,
        ]);
        $this->proPlan = Plan::firstOrCreate(['slug' => 'pro'], [
            'uuid' => Uuid::uuid7()->toString(), 'name' => 'Pro Plan', 'monthly_price' => 25000, 'currency' => 'RWF',
            'storage_limit' => 1024 ** 4, 'video_limit' => 5, 'gallery_limit' => 0, 'team_limit' => 0,
        ]);

        $this->user = User::forceCreate([
            'uuid' => Uuid::uuid7()->toString(), 'plan_id' => $this->proPlan->id, 'name' => 'Alex Morgan',
            'username' => 'alexmorgan', 'email' => 'alex@example.com', 'password' => Hash::make('password123'),
            'email_verified_at' => now(), 'is_active' => true,
        ])->fresh();

        $this->admin = User::forceCreate([
            'uuid' => Uuid::uuid7()->toString(), 'plan_id' => $this->freePlan->id, 'name' => 'Admin',
            'username' => 'siteadmin', 'email' => 'admin@example.com', 'password' => Hash::make('password123'),
            'email_verified_at' => now(), 'is_active' => true, 'role' => 'admin',
        ])->fresh();
    }

    private function subscription(array $attributes = []): Subscription
    {
        return Subscription::create(array_merge([
            'uuid' => Uuid::uuid7()->toString(),
            'user_id' => $this->user->id,
            'plan_id' => $this->proPlan->id,
            'provider' => 'pawapay',
            'status' => 'active',
            'billing_cycle' => 'monthly',
            'starts_at' => now()->subMonth(),
            'ends_at' => now()->addDays(6),
        ], $attributes));
    }

    public function test_every_email_gets_a_plain_text_part(): void
    {
        Mail::to('alex@example.com')->send(new WelcomeMail($this->user));

        $messages = app('mailer')->getSymfonyTransport()->messages();
        $this->assertCount(1, $messages);

        $email = $messages->first()->getOriginalMessage();
        $text = $email->getTextBody();
        $this->assertNotNull($text);
        $this->assertStringContainsString('Your studio is ready', $text);
        $this->assertStringContainsString(route('studio.dashboard'), $text);
        $this->assertStringNotContainsString('<', $text);
    }

    public function test_welcome_email_follows_verification(): void
    {
        Mail::fake();
        event(new Verified($this->user));
        Mail::assertQueued(WelcomeMail::class, fn ($mail) => $mail->hasTo('alex@example.com'));
    }

    public function test_password_change_sends_security_email(): void
    {
        Mail::fake();

        $this->actingAs($this->user)->post(route('studio.settings.password'), [
            'current_password' => 'password123',
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ])->assertSessionHasNoErrors();

        Mail::assertQueued(PasswordChangedMail::class, fn ($mail) => $mail->hasTo('alex@example.com'));
    }

    public function test_failed_plan_payment_emails_payer_and_alerts_admins(): void
    {
        Mail::fake();

        $payment = Payment::create([
            'uuid' => Uuid::uuid7()->toString(), 'user_id' => $this->user->id, 'plan_id' => $this->proPlan->id,
            'purpose' => 'plan_subscription', 'billing_cycle' => 'monthly', 'amount' => 25000, 'currency' => 'RWF',
            'phone_number' => '250788000000', 'provider' => 'MTN_MOMO_RWA', 'idempotency_key' => 'idem-f1',
            'pawapay_deposit_id' => Uuid::uuid4()->toString(), 'status' => 'failed', 'failure_reason' => 'Declined',
        ]);

        event(new PaymentFailed($payment));

        Mail::assertQueued(PaymentFailedMail::class, fn ($mail) => $mail->hasTo('alex@example.com'));
        Mail::assertQueued(AdminAlertMail::class, fn ($mail) => $mail->hasTo('admin@example.com'));
    }

    public function test_expiry_reminder_is_sent_once(): void
    {
        Mail::fake();
        $this->subscription();

        $this->artisan('subscriptions:send-expiry-reminders')->assertSuccessful();
        $this->artisan('subscriptions:send-expiry-reminders')->assertSuccessful();

        Mail::assertQueued(SubscriptionExpiringMail::class, 1);
    }

    public function test_expiring_subscription_sends_ended_email(): void
    {
        Mail::fake();
        $this->subscription(['ends_at' => now()->subMinute()]);

        $this->artisan('subscriptions:expire')->assertSuccessful();

        $this->assertSame($this->freePlan->id, $this->user->fresh()->plan_id);
        Mail::assertQueued(SubscriptionExpiredMail::class, fn ($mail) => $mail->hasTo('alex@example.com'));
    }

    public function test_booking_reminder_goes_to_client_and_photographer_once(): void
    {
        Mail::fake();

        $client = Client::create([
            'uuid' => Uuid::uuid7()->toString(), 'user_id' => $this->user->id, 'name' => 'Alice', 'email' => 'alice@example.com',
        ]);
        Booking::create([
            'uuid' => Uuid::uuid7()->toString(), 'user_id' => $this->user->id, 'client_id' => $client->id,
            'title' => 'Booking: Alice', 'starts_at' => now()->addHours(20), 'ends_at' => now()->addHours(21),
            'status' => 'confirmed', 'currency' => 'RWF',
        ]);

        $this->artisan('bookings:send-reminders')->assertSuccessful();
        $this->artisan('bookings:send-reminders')->assertSuccessful();

        Mail::assertQueued(BookingReminderMail::class, 2);
        Mail::assertQueued(BookingReminderMail::class, fn ($mail) => $mail->hasTo('alice@example.com') && !$mail->forPhotographer);
    }

    public function test_gallery_activity_digest_respects_preference(): void
    {
        Mail::fake();

        $gallery = Gallery::create([
            'uuid' => Uuid::uuid7()->toString(), 'user_id' => $this->user->id, 'title' => 'Wedding', 'slug' => 'wedding', 'visibility' => 'public',
        ]);
        DB::table('activity_logs')->insert([
            ['gallery_id' => $gallery->id, 'event' => 'photo_downloaded', 'created_at' => now()->subHours(2)],
            ['gallery_id' => $gallery->id, 'event' => 'photo_favorited', 'created_at' => now()->subHours(3)],
        ]);

        $this->artisan('notifications:gallery-activity-digest')->assertSuccessful();
        Mail::assertQueued(GalleryActivityDigestMail::class, fn ($mail) => $mail->galleries[0]['downloads'] === 1);

        $this->user->forceFill(['notification_preferences' => ['gallery_activity' => false]])->save();
        Mail::fake();
        $this->artisan('notifications:gallery-activity-digest')->assertSuccessful();
        Mail::assertNotQueued(GalleryActivityDigestMail::class);
    }

    public function test_health_check_alerts_on_failed_job_spike_once_per_hour(): void
    {
        Mail::fake();

        $rows = [];
        for ($i = 0; $i < 12; $i++) {
            $rows[] = [
                'uuid' => (string) \Illuminate\Support\Str::uuid(), 'connection' => 'redis', 'queue' => 'default',
                'payload' => json_encode(['displayName' => 'App\\Jobs\\ProcessPhotoJob']),
                'exception' => "RuntimeException: boom\n#0 trace", 'failed_at' => now()->subMinutes(5),
            ];
        }
        DB::table('failed_jobs')->insert($rows);

        $this->artisan('ops:check-health')->assertSuccessful();
        $this->artisan('ops:check-health')->assertSuccessful();

        Mail::assertQueued(AdminAlertMail::class, 1);
    }

    public function test_receipt_sent_at_is_stamped_on_delivery(): void
    {
        $payment = Payment::create([
            'uuid' => Uuid::uuid7()->toString(), 'user_id' => $this->user->id, 'plan_id' => $this->proPlan->id,
            'purpose' => 'plan_subscription', 'billing_cycle' => 'monthly', 'amount' => 25000, 'currency' => 'RWF',
            'phone_number' => '250788000000', 'provider' => 'MTN_MOMO_RWA', 'idempotency_key' => 'idem-r1',
            'pawapay_deposit_id' => Uuid::uuid4()->toString(), 'status' => 'completed',
        ]);

        (new \App\Listeners\SendPlanUpgradeReceipt())->handle(new \App\Events\SubscriptionPaymentSucceeded($this->user, $payment));

        $receipt = \App\Models\PaymentReceipt::where('payment_id', $payment->id)->first();
        $this->assertNotNull($receipt->sent_at);
    }
}
