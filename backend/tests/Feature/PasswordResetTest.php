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
        // Configure frontend URL for testing
        Config::set('app.frontend_url', 'https://test-frontend.ifotoset.com');
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

        return User::create(array_merge([
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

        $response = $this->postJson('/api/v1/auth/forgot-password', [
            'email' => 'user@example.com',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'message' => 'If an account exists for that email, a password reset link has been sent.',
        ]);

        Notification::assertSentTo($user, QueuedResetPassword::class);
    }

    /**
     * Test forgot-password returns generic success for non-existing email without notifying.
     */
    public function test_forgot_password_returns_success_for_non_existing_user(): void
    {
        Notification::fake();

        $response = $this->postJson('/api/v1/auth/forgot-password', [
            'email' => 'nonexistent@example.com',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'message' => 'If an account exists for that email, a password reset link has been sent.',
        ]);

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

        $this->assertStringContainsString('https://test-frontend.ifotoset.com/reset-password', $actionUrl);
        $this->assertStringContainsString('token=' . urlencode($token), $actionUrl);
        $this->assertStringContainsString('email=' . urlencode('user@example.com'), $actionUrl);
    }

    /**
     * Test that missing FRONTEND_URL throws RuntimeException.
     */
    public function test_missing_frontend_url_throws_runtime_exception(): void
    {
        Config::set('app.frontend_url', null);

        $user = $this->createUser(['email' => 'user@example.com']);
        $token = Password::createToken($user);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('FRONTEND_URL is not configured.');

        $notification = new QueuedResetPassword($token);
        $notification->toMail($user);
    }

    /**
     * Test successful password reset with valid token.
     */
    public function test_password_can_be_reset_with_valid_token(): void
    {
        $user = $this->createUser(['email' => 'user@example.com']);
        $token = Password::createToken($user);

        $response = $this->postJson('/api/v1/auth/reset-password', [
            'token' => $token,
            'email' => 'user@example.com',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'message' => 'Your password has been reset.',
        ]);

        // Verify password changed
        $this->assertTrue(Hash::check('newpassword123', $user->fresh()->password));
    }

    /**
     * Test password reset fails with invalid token.
     */
    public function test_password_reset_fails_with_invalid_token(): void
    {
        $user = $this->createUser(['email' => 'user@example.com']);

        $response = $this->postJson('/api/v1/auth/reset-password', [
            'token' => 'invalid-token',
            'email' => 'user@example.com',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertStatus(422);
        $response->assertJson([
            'code' => 'INVALID_TOKEN_OR_EMAIL',
            'message' => 'This password reset link is invalid or has expired.',
        ]);

        // Verify password did not change
        $this->assertTrue(Hash::check('password123', $user->fresh()->password));
    }

    /**
     * Test forgot-password rate limiting.
     */
    public function test_forgot_password_rate_limiting(): void
    {
        // Hit the endpoint 5 times (limit is 5 requests per minute)
        for ($i = 0; $i < 5; $i++) {
            $response = $this->postJson('/api/v1/auth/forgot-password', [
                'email' => 'user@example.com',
            ]);
            $response->assertStatus(200);
        }

        // 6th request should be throttled
        $response = $this->postJson('/api/v1/auth/forgot-password', [
            'email' => 'user@example.com',
        ]);
        $response->assertStatus(429);
    }
}
