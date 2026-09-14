<?php

namespace Tests\Feature;

use App\Actions\CreateBookingAction;
use App\Actions\UpdateBookingAction;
use App\Events\BookingCreated;
use App\Events\BookingDepositPaid;
use App\Events\BookingStatusChanged;
use App\Events\SubscriptionPaymentSucceeded;
use App\Listeners\SendBookingDepositReceipts;
use App\Listeners\SendBookingNotifications;
use App\Listeners\SendBookingStatusNotification;
use App\Listeners\SendPlanUpgradeReceipt;
use App\Mail\BookingConfirmationClientMail;
use App\Mail\BookingDepositPaidClientMail;
use App\Mail\BookingDepositPaidPhotographerMail;
use App\Mail\BookingStatusUpdatedMail;
use App\Mail\NewBookingPhotographerMail;
use App\Mail\PlanUpgradedMail;
use App\Models\Booking;
use App\Models\Client;
use App\Models\Package;
use App\Models\Payment;
use App\Models\PaymentReceipt;
use App\Models\Plan;
use App\Models\User;
use App\Notifications\QueuedResetPassword;
use App\Notifications\QueuedVerifyEmail;
use App\Services\AvailabilityService;
use App\Services\SmtpSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Ramsey\Uuid\Uuid;
use Tests\TestCase;

class EmailSystemTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        try {
            \Illuminate\Support\Facades\Redis::flushall();
        } catch (\Throwable $e) {
        }
        Config::set('app.frontend_url', 'http://localhost:3000');
    }

    protected function createPhotographer(array $attributes = []): User
    {
        $plan = Plan::firstOrCreate([
            'slug' => 'pro',
        ], [
            'uuid' => Uuid::uuid7()->toString(),
            'name' => 'Pro Plan',
            'monthly_price' => 29.00,
            'annual_price' => 290.00,
            'currency' => 'USD',
            'storage_limit' => 50 * 1024 * 1024 * 1024,
            'video_limit' => 5,
            'gallery_limit' => 50,
            'team_limit' => 3,
        ]);

        return User::create(array_merge([
            'uuid' => Uuid::uuid7()->toString(),
            'plan_id' => $plan->id,
            'name' => 'Photographer User',
            'username' => 'photo' . uniqid(),
            'email' => 'photographer_' . uniqid() . '@example.com',
            'password' => bcrypt('Password123!'),
            'notification_preferences' => [
                'new_bookings' => true,
                'payment_received' => true,
            ],
        ], $attributes));
    }

    protected function createClient(User $photographer, array $attributes = []): Client
    {
        return Client::create(array_merge([
            'uuid' => Uuid::uuid7()->toString(),
            'user_id' => $photographer->id,
            'name' => 'Jane Client',
            'email' => 'client_' . uniqid() . '@example.com',
            'phone' => '+250788123456',
        ], $attributes));
    }

    protected function createPackage(User $photographer): Package
    {
        return Package::create([
            'uuid' => Uuid::uuid7()->toString(),
            'user_id' => $photographer->id,
            'name' => 'Wedding Premium',
            'price' => 500000,
            'currency' => 'RWF',
            'deposit_type' => 'percentage',
            'deposit_value' => 30,
            'duration_minutes' => 120,
            'deliverables' => ['50 edited photos', 'Online gallery access'],
        ]);
    }

    // --- 1. Email Verification & Dynamic Expiration ---

    public function test_registration_sends_branded_verification_email(): void
    {
        Notification::fake();

        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'John Doe',
            'username' => 'johndoe' . rand(100, 999),
            'email' => 'john' . rand(100, 999) . '@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response->assertStatus(201);

        Notification::assertSentTo(
            User::where('email', $response->json('data.user.email'))->first(),
            QueuedVerifyEmail::class
        );
    }

    public function test_verification_email_renders_with_dynamic_expiration(): void
    {
        Config::set('auth.verification.expire', 120);

        $user = $this->createPhotographer(['name' => 'Alice Photographer']);
        $notification = new QueuedVerifyEmail();
        $mailMessage = $notification->toMail($user);

        $this->assertEquals('Verify Email Address - ifotoset', $mailMessage->subject);
        $this->assertEquals('emails.verify_email', $mailMessage->view);
        $this->assertEquals(120, $mailMessage->viewData['expireMinutes']);
        $this->assertEquals('Alice Photographer', $mailMessage->viewData['userName']);

        $renderedHtml = view($mailMessage->view, $mailMessage->viewData)->render();
        $this->assertStringContainsString('120 minutes', $renderedHtml);
        $this->assertStringContainsString('Verify Email Address', $renderedHtml);
    }

    // --- 2. Password Reset Account Enumeration & Template ---

    public function test_password_reset_endpoint_does_not_leak_account_existence(): void
    {
        Notification::fake();
        $user = $this->createPhotographer();

        // Existing email
        $res1 = $this->postJson('/api/v1/auth/forgot-password', ['email' => $user->email]);
        $res1->assertStatus(200)
             ->assertJson(['message' => 'If an account exists for that email, a password reset link has been sent.']);

        // Non-existing email
        $res2 = $this->postJson('/api/v1/auth/forgot-password', ['email' => 'nobody@nonexistent.test']);
        $res2->assertStatus(200)
             ->assertJson(['message' => 'If an account exists for that email, a password reset link has been sent.']);
    }

    public function test_password_reset_email_renders_branded_template(): void
    {
        Config::set('auth.passwords.users.expire', 45);

        $user = $this->createPhotographer(['name' => 'Reset User']);
        $notification = new QueuedResetPassword('fake-token-12345');
        $mailMessage = $notification->toMail($user);

        $this->assertEquals('Reset Password Notification - ifotoset', $mailMessage->subject);
        $this->assertEquals('emails.reset_password', $mailMessage->view);
        $this->assertEquals(45, $mailMessage->viewData['expireMinutes']);

        $renderedHtml = view($mailMessage->view, $mailMessage->viewData)->render();
        $this->assertStringContainsString('45 minutes', $renderedHtml);
        $this->assertStringContainsString('Reset Password', $renderedHtml);
    }

    // --- 3. Booking Creation & Preferences ---

    public function test_booking_creation_dispatches_photographer_and_client_emails(): void
    {
        Mail::fake();

        $photographer = $this->createPhotographer();
        $client = $this->createClient($photographer);
        $package = $this->createPackage($photographer);

        $booking = Booking::create([
            'user_id' => $photographer->id,
            'client_id' => $client->id,
            'package_id' => $package->id,
            'title' => 'Wedding Shoot',
            'starts_at' => now()->addDays(5),
            'ends_at' => now()->addDays(5)->addHours(2),
            'status' => 'pending',
            'price' => 500000,
            'currency' => 'RWF',
        ]);

        $listener = new SendBookingNotifications();
        $listener->handle(new BookingCreated($booking));

        Mail::assertQueued(NewBookingPhotographerMail::class, function ($mail) use ($photographer) {
            return $mail->hasTo($photographer->email);
        });

        Mail::assertQueued(BookingConfirmationClientMail::class, function ($mail) use ($client) {
            return $mail->hasTo($client->email);
        });
    }

    public function test_booking_creation_respects_photographer_preference(): void
    {
        Mail::fake();

        $photographer = $this->createPhotographer([
            'notification_preferences' => ['new_bookings' => false]
        ]);
        $client = $this->createClient($photographer);
        $package = $this->createPackage($photographer);

        $booking = Booking::create([
            'user_id' => $photographer->id,
            'client_id' => $client->id,
            'package_id' => $package->id,
            'title' => 'Portrait Shoot',
            'starts_at' => now()->addDays(2),
            'status' => 'pending',
        ]);

        $listener = new SendBookingNotifications();
        $listener->handle(new BookingCreated($booking));

        // Photographer disabled new_bookings notifications
        Mail::assertNotQueued(NewBookingPhotographerMail::class);

        // Client still receives confirmation (transactional)
        Mail::assertQueued(BookingConfirmationClientMail::class, function ($mail) use ($client) {
            return $mail->hasTo($client->email);
        });
    }

    // --- 4. Booking Status Transitions ---

    public function test_confirmed_to_confirmed_does_not_send_email(): void
    {
        Mail::fake();

        $photographer = $this->createPhotographer();
        $client = $this->createClient($photographer);
        $booking = Booking::create([
            'user_id' => $photographer->id,
            'client_id' => $client->id,
            'title' => 'Session',
            'starts_at' => now()->addDays(3),
            'status' => 'confirmed',
        ]);

        $listener = new SendBookingStatusNotification();
        $listener->handle(new BookingStatusChanged($booking, 'confirmed', 'confirmed'));

        Mail::assertNothingQueued();
    }

    public function test_cancelled_to_cancelled_does_not_send_email(): void
    {
        Mail::fake();

        $photographer = $this->createPhotographer();
        $client = $this->createClient($photographer);
        $booking = Booking::create([
            'user_id' => $photographer->id,
            'client_id' => $client->id,
            'title' => 'Session',
            'starts_at' => now()->addDays(3),
            'status' => 'cancelled',
        ]);

        $listener = new SendBookingStatusNotification();
        $listener->handle(new BookingStatusChanged($booking, 'cancelled', 'cancelled'));

        Mail::assertNothingQueued();
    }

    public function test_pending_to_confirmed_sends_email(): void
    {
        Mail::fake();

        $photographer = $this->createPhotographer();
        $client = $this->createClient($photographer);
        $booking = Booking::create([
            'user_id' => $photographer->id,
            'client_id' => $client->id,
            'title' => 'Session',
            'starts_at' => now()->addDays(3),
            'status' => 'confirmed',
        ]);

        $listener = new SendBookingStatusNotification();
        $listener->handle(new BookingStatusChanged($booking, 'pending', 'confirmed'));

        Mail::assertQueued(BookingStatusUpdatedMail::class, function ($mail) use ($client) {
            return $mail->hasTo($client->email) && $mail->newStatus === 'confirmed';
        });
    }

    public function test_confirmed_to_cancelled_sends_email(): void
    {
        Mail::fake();

        $photographer = $this->createPhotographer();
        $client = $this->createClient($photographer);
        $booking = Booking::create([
            'user_id' => $photographer->id,
            'client_id' => $client->id,
            'title' => 'Session',
            'starts_at' => now()->addDays(3),
            'status' => 'cancelled',
        ]);

        $listener = new SendBookingStatusNotification();
        $listener->handle(new BookingStatusChanged($booking, 'confirmed', 'cancelled'));

        Mail::assertQueued(BookingStatusUpdatedMail::class, function ($mail) use ($client) {
            return $mail->hasTo($client->email) && $mail->newStatus === 'cancelled';
        });
    }

    // --- 5. Payment & Deposit Idempotency ---

    public function test_payment_webhook_retry_does_not_send_duplicate_subscription_receipt(): void
    {
        Mail::fake();

        $photographer = $this->createPhotographer();
        $payment = Payment::create([
            'uuid' => Uuid::uuid7()->toString(),
            'user_id' => $photographer->id,
            'plan_id' => $photographer->plan_id,
            'amount' => 29.00,
            'currency' => 'USD',
            'phone_number' => '+250788111222',
            'provider' => 'MTN',
            'idempotency_key' => 'sub-key-' . uniqid(),
            'pawapay_deposit_id' => 'dep-' . uniqid(),
            'status' => 'completed',
        ]);

        $listener = new SendPlanUpgradeReceipt();

        // 1st invocation: queues receipt
        $listener->handle(new SubscriptionPaymentSucceeded($photographer, $payment));
        Mail::assertQueued(PlanUpgradedMail::class, 1);

        // 2nd invocation (webhook retry): skips duplicate
        $listener->handle(new SubscriptionPaymentSucceeded($photographer, $payment));
        Mail::assertQueued(PlanUpgradedMail::class, 1);

        $this->assertEquals(1, PaymentReceipt::where('payment_id', $payment->id)->where('type', 'subscription_upgrade')->count());
    }

    public function test_payment_webhook_retry_does_not_send_duplicate_deposit_receipts(): void
    {
        Mail::fake();

        $photographer = $this->createPhotographer();
        $client = $this->createClient($photographer);
        $booking = Booking::create([
            'user_id' => $photographer->id,
            'client_id' => $client->id,
            'title' => 'Shoot',
            'starts_at' => now()->addDays(4),
            'status' => 'confirmed',
        ]);

        $payment = Payment::create([
            'uuid' => Uuid::uuid7()->toString(),
            'user_id' => $photographer->id,
            'plan_id' => $photographer->plan_id,
            'booking_id' => $booking->id,
            'purpose' => 'booking_deposit',
            'amount' => 50000,
            'currency' => 'RWF',
            'phone_number' => '+250788111333',
            'provider' => 'MTN',
            'idempotency_key' => 'dep-key-' . uniqid(),
            'pawapay_deposit_id' => 'pawadep-' . uniqid(),
            'status' => 'completed',
        ]);

        $listener = new SendBookingDepositReceipts();

        // 1st invocation
        $listener->handle(new BookingDepositPaid($booking, $payment));
        Mail::assertQueued(BookingDepositPaidClientMail::class, 1);
        Mail::assertQueued(BookingDepositPaidPhotographerMail::class, 1);

        // 2nd invocation (webhook retry)
        $listener->handle(new BookingDepositPaid($booking, $payment));
        Mail::assertQueued(BookingDepositPaidClientMail::class, 1);
        Mail::assertQueued(BookingDepositPaidPhotographerMail::class, 1);

        $this->assertEquals(2, PaymentReceipt::where('payment_id', $payment->id)->count());
    }

    // --- 6. Transaction Safety (afterCommit) ---

    public function test_email_is_not_queued_when_booking_transaction_rolls_back(): void
    {
        Mail::fake();

        $photographer = $this->createPhotographer();
        $client = $this->createClient($photographer);

        try {
            DB::transaction(function () use ($photographer, $client) {
                $booking = Booking::create([
                    'user_id' => $photographer->id,
                    'client_id' => $client->id,
                    'title' => 'Rollback Booking',
                    'starts_at' => now()->addDays(1),
                    'status' => 'pending',
                ]);

                event(new BookingCreated($booking));

                // Force transaction abort
                throw new \Exception('Simulated database error');
            });
        } catch (\Throwable $e) {
            // caught
        }

        // Listener should NOT have queued emails because transaction rolled back
        Mail::assertNotQueued(NewBookingPhotographerMail::class);
        Mail::assertNotQueued(BookingConfirmationClientMail::class);
    }

    // --- 7. Web Monolith Email Verification & Notice ---

    public function test_web_login_page_displays_verified_success_banner(): void
    {
        $response = $this->get('/login?verified=1');
        $response->assertStatus(200);
        $response->assertSee('Your email address has been verified successfully!');
    }

    public function test_web_login_page_displays_invalid_verification_banner(): void
    {
        $response = $this->get('/login?verified=0');
        $response->assertStatus(200);
        $response->assertSee('The verification link is invalid or has expired.');
    }

    public function test_web_resend_verification_sends_notification(): void
    {
        Notification::fake();

        $user = $this->createPhotographer(['email_verified_at' => null]);

        $response = $this->actingAs($user)->post('/email/verification-notification');
        $response->assertRedirect();
        $response->assertSessionHas('status', 'verification-link-sent');

        Notification::assertSentTo($user, QueuedVerifyEmail::class);
    }

    public function test_web_verified_user_accessing_verify_notice_redirects_to_dashboard(): void
    {
        $user = $this->createPhotographer(['email_verified_at' => now()]);

        $response = $this->actingAs($user)->get('/email/verify');
        $response->assertRedirect('/studio/dashboard');
    }
}
