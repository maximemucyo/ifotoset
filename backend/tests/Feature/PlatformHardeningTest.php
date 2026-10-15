<?php

namespace Tests\Feature;

use App\Enums\NotificationPreference;
use App\Models\Gallery;
use App\Models\GooglePhotoAuthorization;
use App\Models\GooglePhotoSync;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Ramsey\Uuid\Uuid;
use Tests\TestCase;

/**
 * Regression tests for the audit fixes (scheduler, listeners, suspension,
 * verification, preferences, uploads, throttling, Google Photos callback).
 */
class PlatformHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected Plan $plan;
    protected User $user;
    protected Gallery $gallery;

    protected function setUp(): void
    {
        parent::setUp();

        try {
            \Illuminate\Support\Facades\Redis::connection()->flushdb();
        } catch (\Throwable $e) {
        }

        $this->plan = Plan::firstOrCreate(['slug' => 'free'], [
            'uuid' => Uuid::uuid7()->toString(),
            'name' => 'Free Plan',
            'storage_limit' => 5 * 1024 * 1024 * 1024,
            'video_limit' => 0,
            'gallery_limit' => 5,
            'team_limit' => 0,
        ]);

        $this->user = $this->makeUser('alexmorgan', 'alex@example.com');

        $this->gallery = Gallery::create([
            'uuid' => Uuid::uuid7()->toString(),
            'user_id' => $this->user->id,
            'title' => 'Wedding Collection',
            'slug' => 'wedding-collection',
            'visibility' => 'public',
            'allow_google_photos' => true,
        ]);
    }

    private function makeUser(string $username, string $email, array $attributes = []): User
    {
        return User::forceCreate(array_merge([
            'uuid' => Uuid::uuid7()->toString(),
            'plan_id' => $this->plan->id,
            'name' => ucfirst($username),
            'username' => $username,
            'email' => $email,
            'password' => Hash::make('password123'),
            'email_verified_at' => now(),
            'is_active' => true,
        ], $attributes))->fresh();
    }

    // --- 1.1 Scheduler ---

    public function test_maintenance_commands_are_scheduled(): void
    {
        Artisan::call('schedule:list');
        $output = Artisan::output();

        foreach ([
            'subscriptions:expire',
            'payments:reconcile',
            'uploads:reconcile-reservations',
            'media:reconcile-statistics',
            'ifotoset:purge-expired-trash',
            'gallery:cleanup-expired-zips',
        ] as $command) {
            $this->assertStringContainsString($command, $output);
        }
    }

    // --- 1.2 Duplicate listeners ---

    public function test_each_domain_listener_is_registered_once(): void
    {
        foreach ([
            \App\Events\BookingCreated::class,
            \App\Events\BookingStatusChanged::class,
            \App\Events\BookingDepositPaid::class,
            \App\Events\SubscriptionPaymentSucceeded::class,
            \App\Events\PaymentCompleted::class,
            \App\Events\StorageRecalculatedEvent::class,
        ] as $event) {
            $this->assertCount(1, Event::getRawListeners()[$event] ?? [], "{$event} should have exactly one listener");
        }
    }

    // --- 1.6 Suspension ---

    public function test_suspended_user_cannot_log_in(): void
    {
        $this->user->forceFill(['is_active' => false])->save();

        $this->post('/login', ['email' => 'alex@example.com', 'password' => 'password123'])
            ->assertSessionHasErrors(['email' => 'This account has been suspended. Please contact support.']);

        $this->assertGuest();
    }

    public function test_user_suspended_mid_session_is_logged_out(): void
    {
        $this->actingAs($this->user)->get('/studio/dashboard')->assertOk();

        $this->user->forceFill(['is_active' => false])->save();

        $this->actingAs($this->user->fresh())->get('/studio/dashboard')->assertRedirect('/login');
        $this->assertGuest();
    }

    // --- 1.8 Verification ---

    public function test_unverified_user_is_sent_to_verification_notice(): void
    {
        $unverified = $this->makeUser('newbie', 'newbie@example.com', ['email_verified_at' => null]);

        $this->actingAs($unverified)->get('/studio/dashboard')->assertRedirect(route('verification.notice'));
        $this->actingAs($unverified)->get('/email/verify')->assertOk();
    }

    // --- 1.7 Notification preferences ---

    public function test_saving_notification_form_merges_and_uses_canonical_keys(): void
    {
        $this->user->forceFill(['notification_preferences' => ['legacy_key' => true]])->save();

        $this->actingAs($this->user)->post(route('studio.settings.notifications'), [
            'payment_received' => '1',
        ])->assertRedirect();

        $prefs = $this->user->fresh()->notification_preferences;
        $this->assertTrue($prefs['legacy_key']);
        $this->assertFalse($prefs['new_bookings']);
        $this->assertTrue($prefs['payment_received']);
        $this->assertFalse(NotificationPreference::NewBookings->enabledFor($this->user->fresh()));
    }

    public function test_new_bookings_off_suppresses_photographer_email(): void
    {
        \Illuminate\Support\Facades\Mail::fake();
        $this->user->forceFill(['notification_preferences' => ['new_bookings' => false]])->save();

        $package = \App\Models\Package::create([
            'uuid' => Uuid::uuid7()->toString(),
            'user_id' => $this->user->id,
            'name' => 'Portrait',
            'price' => 50000,
            'currency' => 'RWF',
            'duration_minutes' => 60,
            'deliverables' => ['20 edited photos'],
        ]);

        $bookUrl = app(\App\Services\PublicUrlService::class)->photographerUrl('alexmorgan') . '/book';
        $this->post($bookUrl, [
            'client_name' => 'Alice Walker',
            'client_email' => 'alice@example.com',
            'package_id' => $package->uuid,
            'starts_at' => now()->addDays(3)->setTime(10, 0)->toDateTimeString(),
        ])->assertSessionHas('success');

        \Illuminate\Support\Facades\Mail::assertNotQueued(\App\Mail\NewBookingPhotographerMail::class);
        \Illuminate\Support\Facades\Mail::assertQueued(\App\Mail\BookingConfirmationClientMail::class, 1);
    }

    // --- 1.4 / 2.3 Booking form ---

    public function test_booking_honeypot_does_not_create_a_booking(): void
    {
        $bookUrl = app(\App\Services\PublicUrlService::class)->photographerUrl('alexmorgan') . '/book';

        $this->post($bookUrl, [
            'client_name' => 'Spam Bot',
            'client_email' => 'bot@example.com',
            'package_id' => 'x',
            'starts_at' => now()->addDay()->toDateTimeString(),
            'website' => 'http://spam.example',
        ])->assertSessionHas('success');

        $this->assertDatabaseCount('bookings', 0);
    }

    // --- 2.1 Uploads ---

    public function test_html_upload_is_rejected(): void
    {
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);

        $this->actingAs($this->user)->postJson('/studio/uploads/request', [
            'gallery_id' => $this->gallery->uuid,
            'filename' => 'evil.html',
            'file_size' => 1024,
            'mime_type' => 'text/html',
            'sha256' => str_repeat('a', 64),
        ])->assertStatus(422)->assertJsonPath('code', 'UNSUPPORTED_FILE_TYPE');

        // Image extension disguising a non-image MIME type
        $this->actingAs($this->user)->postJson('/studio/uploads/request', [
            'gallery_id' => $this->gallery->uuid,
            'filename' => 'evil.jpg',
            'file_size' => 1024,
            'mime_type' => 'text/html',
            'sha256' => str_repeat('b', 64),
        ])->assertStatus(422);
    }

    public function test_image_upload_signs_canonical_content_type(): void
    {
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);

        $this->actingAs($this->user)->postJson('/studio/uploads/request', [
            'gallery_id' => $this->gallery->uuid,
            'filename' => 'IMG_0001.JPG',
            'file_size' => 1024,
            'mime_type' => 'image/jpeg',
            'sha256' => str_repeat('c', 64),
        ])->assertStatus(201)->assertJsonPath('headers.Content-Type', 'image/jpeg');
    }

    // --- 2.2 Login throttling ---

    public function test_login_locks_after_five_failures_for_same_email(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => 'alex@example.com', 'password' => 'wrong']);
        }

        $response = $this->post('/login', ['email' => 'alex@example.com', 'password' => 'password123']);
        $response->assertSessionHasErrors('email');
        $this->assertStringContainsString('Too many', session('errors')->first('email'));
        $this->assertGuest();
    }

    // --- 2.4 PIN length ---

    public function test_gallery_pin_must_be_at_least_six_characters(): void
    {
        $this->actingAs($this->user)->post(route('studio.galleries.store'), [
            'title' => 'Short PIN',
            'visibility' => 'password',
            'password' => '1234',
        ])->assertSessionHasErrors('password');
    }

    // --- 2.5 Security headers ---

    public function test_security_headers_are_sent(): void
    {
        $this->get('/')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeaderMissing('X-XSS-Protection');

        $this->assertNotNull($this->get('/')->headers->get('Content-Security-Policy-Report-Only'));
    }

    // --- Google Photos OAuth callback ---

    public function test_google_callback_starts_sync_and_returns_to_export_page_once(): void
    {
        Queue::fake();
        Http::fake(['oauth2.googleapis.com/*' => Http::response(['access_token' => 'at', 'refresh_token' => 'rt', 'expires_in' => 3600])]);

        GooglePhotoAuthorization::create([
            'gallery_id' => $this->gallery->id,
            'state' => 'state-123',
            'photo_uuids' => null,
            'expires_at' => now()->addMinutes(15),
            'email' => null,
            'notify_when_ready' => false,
        ]);

        $response = $this->get('/google-photos/callback?state=state-123&code=abc');

        $sync = GooglePhotoSync::first();
        $this->assertNotNull($sync);
        $response->assertRedirectContains('/export');
        $response->assertRedirectContains('sync=' . $sync->uuid);
        Queue::assertPushed(\App\Jobs\SyncGooglePhotosJob::class);

        // Replaying the same callback does not start a second sync
        $this->get('/google-photos/callback?state=state-123&code=abc')->assertRedirectContains('error=used');
        $this->assertSame(1, GooglePhotoSync::count());

        // Unknown state goes home
        $this->get('/google-photos/callback?state=nope&code=abc')->assertRedirect(route('home'));
    }
}
