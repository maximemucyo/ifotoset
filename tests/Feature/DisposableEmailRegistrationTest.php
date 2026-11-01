<?php

namespace Tests\Feature;

use App\Models\User;
use App\Rules\NotDisposableEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DisposableEmailRegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function register(string $email)
    {
        return $this->post('/register', [
            'name' => 'New Studio',
            'username' => 'newstudio',
            'email' => $email,
            'phone' => '0788 123 456',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);
    }

    public function test_disposable_domains_and_their_subdomains_are_detected(): void
    {
        $this->assertTrue(NotDisposableEmail::isDisposable('someone@mailinator.com'));
        $this->assertTrue(NotDisposableEmail::isDisposable('someone@YOPMAIL.com'));
        $this->assertTrue(NotDisposableEmail::isDisposable('someone@inbox.mailinator.com'));

        $this->assertFalse(NotDisposableEmail::isDisposable('someone@gmail.com'));
        $this->assertFalse(NotDisposableEmail::isDisposable('someone@outlook.com'));
        $this->assertFalse(NotDisposableEmail::isDisposable('someone@studio.rw'));
        $this->assertFalse(NotDisposableEmail::isDisposable('not-an-email'));
    }

    public function test_registration_rejects_a_disposable_email(): void
    {
        $this->register('throwaway@yopmail.com')
            ->assertSessionHasErrors(['email' => 'Please use a permanent email address. Temporary inboxes are not accepted.']);

        $this->assertGuest();
        $this->assertFalse(User::where('email', 'throwaway@yopmail.com')->exists());
    }

    public function test_registration_accepts_a_regular_email(): void
    {
        $this->register('owner@gmail.com')->assertRedirect(route('studio.dashboard'));

        $this->assertTrue(User::where('email', 'owner@gmail.com')->exists());
    }
}
