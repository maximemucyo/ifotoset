<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\QueuedResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        try {
            \Illuminate\Support\Facades\Redis::flushall();
        } catch (\Throwable $e) {
        }
    }

    protected function createUser(array $attributes = [])
    {
        $plan = \App\Models\Plan::firstOrCreate([
            'slug' => 'free',
        ], [
            'uuid' => \Ramsey\Uuid\Uuid::uuid7()->toString(),
            'name' => 'Free Plan',
            'storage_limit' => 5 * 1024 * 1024 * 1024,
            'video_limit' => 0,
            'gallery_limit' => 3,
            'team_limit' => 0,
        ]);

        return User::forceCreate(array_merge([
            'email_verified_at' => now(),
            'uuid' => \Ramsey\Uuid\Uuid::uuid7()->toString(),
            'plan_id' => $plan->id,
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => Hash::make('password123'),
        ], $attributes));
    }

    /**
     * Test forgot-password returns generic success for existing email and triggers notification.
     */
    public function test_forgot_password_sends_notification_for_existing_user(): void
    {
        Notification::fake();
        $user = $this->createUser(['email' => 'user@example.com']);

        $response = $this->post('/forgot-password', [
            'email' => 'user@example.com',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('status', 'If an account exists for that email, a password reset link has been sent.');

        Notification::assertSentTo($user, QueuedResetPassword::class);
    }

    /**
     * Test forgot-password returns generic success for non-existing email without notifying.
     */
    public function test_forgot_password_returns_success_for_non_existing_user(): void
    {
        Notification::fake();

        $response = $this->post('/forgot-password', [
            'email' => 'nonexistent@example.com',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('status', 'If an account exists for that email, a password reset link has been sent.');

        Notification::assertNothingSent();
    }

    /**
     * Test the reset link URL contains correct path and query parameters.
     */
    public function test_reset_url_is_correctly_generated_and_encoded(): void
    {
        $user = $this->createUser(['email' => 'user@example.com']);
        $token = Password::createToken($user);

        // Fetch URL using the callback configured in service provider
        $notification = new QueuedResetPassword($token);
        $mailMessage = $notification->toMail($user);
        $actionUrl = $mailMessage->actionUrl;

        $this->assertStringStartsWith(url('/reset-password/' . $token), $actionUrl);
        $this->assertStringContainsString('email=' . urlencode('user@example.com'), $actionUrl);
    }

    /**
     * The reset link must point at the Laravel reset form, not an external frontend.
     */
    public function test_reset_url_uses_laravel_route(): void
    {
        $user = $this->createUser(['email' => 'user@example.com']);
        $token = Password::createToken($user);

        $actionUrl = (new QueuedResetPassword($token))->toMail($user)->actionUrl;

        $this->assertSame(route('password.reset', ['token' => $token, 'email' => 'user@example.com']), $actionUrl);
    }

    /**
     * Test successful password reset with valid token.
     */
    public function test_password_can_be_reset_with_valid_token(): void
    {
        $user = $this->createUser(['email' => 'user@example.com']);
        $token = Password::createToken($user);

        $response = $this->post('/reset-password', [
            'token' => $token,
            'email' => 'user@example.com',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertRedirect('/login');

        // Verify password changed
        $this->assertTrue(Hash::check('newpassword123', $user->fresh()->password));
    }

    /**
     * Test password reset fails with invalid token.
     */
    public function test_password_reset_fails_with_invalid_token(): void
    {
        $user = $this->createUser(['email' => 'user@example.com']);

        $response = $this->post('/reset-password', [
            'token' => 'invalid-token',
            'email' => 'user@example.com',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertSessionHasErrors('email');

        // Verify password did not change
        $this->assertTrue(Hash::check('password123', $user->fresh()->password));
    }

    /**
     * Test forgot-password rate limiting.
     */
    public function test_forgot_password_rate_limiting(): void
    {
        try {
            \Illuminate\Support\Facades\Redis::flushall();
        } catch (\Throwable $e) {
        }

        // Hit the endpoint 5 times (limit is 5 requests per minute)
        for ($i = 0; $i < 5; $i++) {
            $response = $this->post('/forgot-password', [
                'email' => 'user@example.com',
            ]);
            $response->assertRedirect();
        }

        // 6th request should be throttled
        $response = $this->post('/forgot-password', [
            'email' => 'user@example.com',
        ]);
        $response->assertStatus(429);
    }
}
