<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Ramsey\Uuid\Uuid;
use Tests\TestCase;

class UsernameAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    protected function studio(string $username): User
    {
        $plan = Plan::firstOrCreate(['slug' => 'free'], [
            'uuid' => Uuid::uuid7()->toString(),
            'name' => 'Free',
            'storage_limit' => 5 * 1024 * 1024 * 1024,
            'video_limit' => 0,
            'team_limit' => 0,
        ]);

        return User::forceCreate([
            'uuid' => Uuid::uuid7()->toString(),
            'plan_id' => $plan->id,
            'name' => ucfirst($username),
            'username' => $username,
            'email' => "{$username}@example.com",
            'password' => Hash::make('secret123'),
            'role' => 'photographer',
            'is_active' => true,
        ]);
    }

    protected function check(string $username)
    {
        return $this->getJson(route('username.availability', ['username' => $username]));
    }

    public function test_a_free_username_is_available(): void
    {
        $this->check('Fresh-Studio ')
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertExactJson(['available' => true, 'message' => null, 'suggestion' => null]);
    }

    public function test_a_taken_username_is_unavailable_with_a_free_suggestion(): void
    {
        $this->studio('lumiere');
        $this->studio('lumiere2');

        $this->check('lumiere')->assertOk()->assertExactJson([
            'available' => false,
            'message' => 'That address is already taken. Please choose another.',
            'suggestion' => 'lumiere3',
        ]);
    }

    public function test_a_soft_deleted_studio_still_holds_its_username(): void
    {
        $this->studio('gone')->delete();

        $this->check('gone')->assertJson(['available' => false, 'suggestion' => 'gone2']);
    }

    public function test_a_reserved_username_is_unavailable(): void
    {
        $reserved = config('reserved_usernames')[0];

        $this->check($reserved)->assertJson([
            'available' => false,
            'message' => 'That address is reserved. Please choose another.',
        ]);
    }

    public function test_a_badly_formed_username_gets_the_format_message_and_no_suggestion(): void
    {
        $this->check('-studio')->assertExactJson([
            'available' => false,
            'message' => 'Use 3–50 lowercase letters, numbers or hyphens (no hyphen at the start or end).',
            'suggestion' => null,
        ]);
    }

    public function test_registration_uses_the_same_taken_message(): void
    {
        $this->studio('lumiere');

        $this->post('/register', [
            'name' => 'New Studio',
            'username' => 'lumiere',
            'email' => 'new@example.com',
            'phone' => '0788 123 456',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertSessionHasErrors(['username' => 'That address is already taken. Please choose another.']);
    }
}
