<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Ramsey\Uuid\Uuid;
use Tests\TestCase;

class GoogleAuthTest extends TestCase
{
    use RefreshDatabase;

    protected Plan $freePlan;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.google.client_id' => 'client-id', 'services.google.client_secret' => 'client-secret']);

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
    }

    private function fakeGoogle(array $profile = []): void
    {
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'token', 'id_token' => 'x']),
            'openidconnect.googleapis.com/v1/userinfo' => Http::response(array_merge([
                'sub' => 'google-123',
                'email' => 'alex@example.com',
                'email_verified' => true,
                'name' => 'Alex Morgan',
            ], $profile)),
        ]);
    }

    private function googleCallback(string $state = 'state-abc')
    {
        return $this->withSession(['google_auth.state' => 'state-abc'])
            ->get(route('auth.google.callback', ['state' => $state, 'code' => 'auth-code']));
    }

    private function user(array $attributes = []): User
    {
        return User::forceCreate(array_merge([
            'uuid' => Uuid::uuid7()->toString(),
            'plan_id' => $this->freePlan->id,
            'name' => 'Alex',
            'username' => 'alex',
            'email' => 'alex@example.com',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
        ], $attributes));
    }

    public function test_redirect_sends_visitor_to_google_with_state(): void
    {
        $response = $this->get(route('auth.google'));

        $response->assertRedirect();
        $location = $response->headers->get('Location');
        $this->assertStringStartsWith('https://accounts.google.com/o/oauth2/v2/auth?', $location);
        $this->assertStringContainsString('scope=openid+email+profile', $location);
        $this->assertStringContainsString('state=' . session('google_auth.state'), $location);
    }

    public function test_routes_are_hidden_when_not_configured(): void
    {
        config(['services.google.client_id' => null]);

        $this->get(route('auth.google'))->assertNotFound();
        $this->get(route('login'))->assertOk()->assertDontSee('Continue with Google');
    }

    public function test_login_page_shows_google_button_when_configured(): void
    {
        $this->get(route('login'))->assertOk()->assertSee('Continue with Google');
        $this->get(route('register'))->assertOk()->assertSee('Sign up with Google');
    }

    public function test_callback_rejects_mismatched_state(): void
    {
        $this->fakeGoogle();

        $this->googleCallback('other-state')->assertRedirect(route('login'));

        $this->assertGuest();
        Http::assertNothingSent();
    }

    public function test_existing_user_signs_in_and_gets_linked(): void
    {
        $this->fakeGoogle(['email' => 'Alex@Example.com']);
        $user = $this->user(['email_verified_at' => null]);

        $this->googleCallback()->assertRedirect(route('studio.dashboard'));

        $this->assertAuthenticatedAs($user);
        $user->refresh();
        $this->assertSame('google-123', $user->google_id);
        $this->assertNotNull($user->email_verified_at);
    }

    public function test_linked_user_signs_in_by_google_id_after_email_change(): void
    {
        $this->fakeGoogle(['email' => 'new@example.com']);
        $user = $this->user(['email' => 'old@example.com', 'google_id' => 'google-123']);

        $this->googleCallback()->assertRedirect(route('studio.dashboard'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_unverified_google_email_is_refused(): void
    {
        $this->fakeGoogle(['email_verified' => false]);
        $this->user();

        $this->googleCallback()->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_suspended_and_admin_accounts_cannot_use_google(): void
    {
        $this->fakeGoogle();
        $user = $this->user(['is_active' => false]);

        $this->googleCallback()->assertRedirect(route('login'))->assertSessionHas('error');
        $this->assertGuest();

        $user->forceFill(['is_active' => true, 'role' => 'admin'])->save();

        $this->googleCallback()->assertRedirect(route('login'))->assertSessionHas('error');
        $this->assertGuest();
    }

    public function test_email_linked_to_another_google_account_is_refused(): void
    {
        $this->fakeGoogle();
        $this->user(['google_id' => 'google-other']);

        $this->googleCallback()->assertRedirect(route('login'))->assertSessionHas('error');

        $this->assertGuest();
    }

    public function test_new_visitor_completes_sign_up_with_chosen_username(): void
    {
        $this->fakeGoogle();
        $this->user(['email' => 'someone@example.com', 'username' => 'alexmorgan']);

        $this->googleCallback()->assertRedirect(route('auth.google.complete'));
        $this->assertGuest();

        $this->get(route('auth.google.complete'))
            ->assertOk()
            ->assertSee('alex@example.com')
            ->assertSee('alexmorgan2');

        $this->post(route('auth.google.complete'), ['name' => 'Alex Morgan', 'username' => 'AlexStudio'])
            ->assertRedirect(route('studio.dashboard'));

        $user = User::where('email', 'alex@example.com')->firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertSame('alexstudio', $user->username);
        $this->assertSame('google-123', $user->google_id);
        $this->assertSame($this->freePlan->id, $user->plan_id);
        $this->assertNotNull($user->email_verified_at);
        $this->assertNull(session('google_auth.profile'));
    }

    public function test_complete_validates_username(): void
    {
        $this->fakeGoogle();
        $this->user(['email' => 'someone@example.com', 'username' => 'taken']);
        $this->googleCallback();

        $this->post(route('auth.google.complete'), ['name' => 'Alex', 'username' => 'taken'])
            ->assertSessionHasErrors('username');
        $this->post(route('auth.google.complete'), ['name' => 'Alex', 'username' => 'admin'])
            ->assertSessionHasErrors('username');

        $this->assertGuest();
    }

    public function test_complete_without_google_profile_redirects_to_register(): void
    {
        $this->get(route('auth.google.complete'))->assertRedirect(route('register'));
        $this->post(route('auth.google.complete'), ['name' => 'Alex', 'username' => 'alex'])
            ->assertRedirect(route('register'));
    }
}
