<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Ramsey\Uuid\Uuid;
use Tests\TestCase;

class RegisterEmailStepTest extends TestCase
{
    use RefreshDatabase;

    private function user(array $attributes = []): User
    {
        $plan = Plan::firstOrCreate(['slug' => 'free'], [
            'uuid' => Uuid::uuid7()->toString(),
            'name' => 'Free',
            'storage_limit' => 2147483648,
            'video_limit' => 0,
            'gallery_limit' => 3,
            'team_limit' => 0,
        ]);

        return User::forceCreate(array_merge([
            'uuid' => Uuid::uuid7()->toString(),
            'plan_id' => $plan->id,
            'name' => 'Alex',
            'username' => 'alex',
            'email' => 'alex@example.com',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
        ], $attributes));
    }

    protected function checkEmail(string $email)
    {
        return $this->postJson(route('register.check-email'), ['email' => $email]);
    }

    public function test_a_new_email_can_continue_to_the_details_step(): void
    {
        $this->checkEmail('owner@gmail.com')->assertOk()->assertJson(['ok' => true]);
    }

    public function test_an_invalid_email_is_rejected(): void
    {
        $this->checkEmail('not-an-email')->assertUnprocessable()->assertJsonValidationErrors('email');
    }

    public function test_a_taken_email_is_rejected(): void
    {
        $this->user(['email' => 'taken@gmail.com']);

        $this->checkEmail('taken@gmail.com')->assertUnprocessable()->assertJsonValidationErrors('email');
    }

    public function test_a_disposable_email_is_rejected(): void
    {
        $this->checkEmail('throwaway@yopmail.com')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email' => 'Please use a permanent email address. Temporary inboxes are not accepted.']);
    }

    public function test_a_team_member_without_a_studio_is_told_to_log_in(): void
    {
        $this->user(['email' => 'member@gmail.com', 'username' => null]);

        $this->checkEmail('Member@gmail.com ')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email' => 'You already have an account. Log in, then choose "Create my own studio" from the studio menu.']);
    }

    public function test_the_check_is_only_for_guests(): void
    {
        $this->actingAs($this->user())
            ->checkEmail('owner@gmail.com')
            ->assertRedirect();
    }

    public function test_the_form_opens_on_the_email_step(): void
    {
        $this->get(route('register'))
            ->assertOk()
            ->assertSee('signupForm({ step: 1', false);
    }

    public function test_an_email_error_reopens_the_email_step(): void
    {
        $this->user(['email' => 'taken@gmail.com']);

        $this->from(route('register'))
            ->post(route('register'), ['email' => 'taken@gmail.com', 'name' => 'Studio'])
            ->assertRedirect(route('register'));

        $this->get(route('register'))->assertSee('signupForm({ step: 1', false);
    }

    public function test_a_details_error_reopens_the_details_step(): void
    {
        $this->from(route('register'))
            ->post(route('register'), ['email' => 'owner@gmail.com', 'name' => 'Studio', 'username' => 'newstudio', 'phone' => '0788 123 456'])
            ->assertSessionHasErrors('password');

        $this->get(route('register'))->assertSee('signupForm({ step: 2', false);
    }
}
