<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\User;
use App\Support\Phone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Ramsey\Uuid\Uuid;
use Tests\TestCase;

class StudioPhoneNumberTest extends TestCase
{
    use RefreshDatabase;

    protected Plan $plan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->plan = Plan::firstOrCreate(['slug' => 'free'], [
            'uuid' => Uuid::uuid7()->toString(),
            'name' => 'Free',
            'storage_limit' => 2 * 1024 * 1024 * 1024,
            'video_limit' => 0,
            'team_limit' => 0,
        ]);
    }

    protected function studio(string $username, ?string $phone = null, array $attributes = []): User
    {
        return User::forceCreate($attributes + [
            'email_verified_at' => now(),
            'uuid' => Uuid::uuid7()->toString(),
            'plan_id' => $this->plan->id,
            'name' => ucfirst($username),
            'username' => $username,
            'email' => "{$username}@example.com",
            'phone' => $phone,
            'password' => Hash::make('secret123'),
            'role' => 'photographer',
            'is_active' => true,
        ]);
    }

    protected function register(array $data = [])
    {
        return $this->post('/register', $data + [
            'name' => 'New Studio',
            'username' => 'newstudio',
            'email' => 'new@example.com',
            'phone' => '0788 123 456',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);
    }

    public function test_numbers_are_normalized_to_e164(): void
    {
        $this->assertSame('+250788123456', Phone::normalize('0788 123 456'));
        $this->assertSame('+250788123456', Phone::normalize('+250 788-123-456'));
        $this->assertSame('+256772123456', Phone::normalize('0772 123456', 'UG'));
        $this->assertSame('+447911123456', Phone::normalize('+44 7911 123456', 'RW'), 'an international prefix wins over the chosen country');
        $this->assertNull(Phone::normalize('12345'));
        $this->assertNull(Phone::normalize('not a phone'));
    }

    public function test_registration_requires_a_valid_phone_and_stores_it_normalized(): void
    {
        $this->register(['phone' => ''])->assertSessionHasErrors('phone');
        $this->register(['phone' => '123'])->assertSessionHasErrors(['phone' => 'Enter a valid phone number.']);
        $this->assertGuest();

        $this->register()->assertRedirect(route('studio.dashboard'));
        $this->assertSame('+250788123456', User::where('username', 'newstudio')->value('phone'));
    }

    public function test_registration_accepts_another_country(): void
    {
        $this->register(['phone' => '0712 345678', 'phone_country' => 'KE'])->assertRedirect(route('studio.dashboard'));

        $this->assertSame('+254712345678', User::where('username', 'newstudio')->value('phone'));
    }

    public function test_a_phone_number_is_limited_to_five_studios(): void
    {
        foreach (range(1, 4) as $i) {
            $this->studio("studio{$i}", '+250788123456');
        }
        // Invited team members and deleted studios do not use up the number
        $this->studio('member', '+250788123456', ['username' => null, 'email' => 'member@example.com']);
        $this->studio('gone', '+250788123456')->delete();

        $this->register()->assertRedirect(route('studio.dashboard'));
        auth()->logout();

        $this->register(['username' => 'sixthstudio', 'email' => 'sixth@example.com', 'phone' => '+250 788 123 456'])
            ->assertSessionHasErrors(['phone' => 'This phone number is already linked to 5 accounts. Please use a different number.']);
        $this->assertDatabaseMissing('users', ['username' => 'sixthstudio']);
    }

    public function test_a_studio_at_the_limit_can_keep_its_own_number_in_settings(): void
    {
        foreach (range(1, 5) as $i) {
            $studio = $this->studio("studio{$i}", '+250788123456');
        }

        $this->actingAs($studio)
            ->post(route('studio.settings.profile'), ['name' => 'Studio5', 'phone' => '0788123456', 'phone_country' => 'RW'])
            ->assertSessionHasNoErrors();

        $this->assertSame('+250788123456', $studio->fresh()->phone);
    }

    public function test_dashboard_prompts_for_a_missing_phone_until_dismissed_for_a_week(): void
    {
        $studio = $this->studio('nophone');

        $this->actingAs($studio)->get(route('studio.dashboard'))->assertSee('Add a phone number to your account');

        $this->post(route('studio.settings.phone.dismiss'))->assertRedirect();
        $this->get(route('studio.dashboard'))->assertDontSee('Add a phone number to your account');

        $this->travel(8)->days();
        $this->get(route('studio.dashboard'))->assertSee('Add a phone number to your account');

        $this->post(route('studio.settings.phone'), ['phone' => '0788123456', 'phone_country' => 'RW'])->assertSessionHasNoErrors();
        $this->assertSame('+250788123456', $studio->fresh()->phone);
        $this->get(route('studio.dashboard'))->assertDontSee('Add a phone number to your account');
    }

    public function test_saved_numbers_prefill_the_country_and_national_number(): void
    {
        $this->assertSame(['country' => 'KE', 'national' => '0712 345678'], Phone::split('+254712345678'));
        $this->assertSame(['country' => 'RW', 'national' => 'not a phone'], Phone::split('not a phone'));
    }
}
