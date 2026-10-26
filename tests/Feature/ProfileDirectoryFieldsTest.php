<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\User;
use App\Services\PublicUrlService;
use App\Support\Directory;
use Illuminate\Support\Facades\DB;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Ramsey\Uuid\Uuid;
use Tests\TestCase;

class ProfileDirectoryFieldsTest extends TestCase
{
    use RefreshDatabase;

    protected User $photographer;

    protected function setUp(): void
    {
        parent::setUp();

        $plan = Plan::firstOrCreate(['slug' => 'free'], [
            'uuid' => Uuid::uuid7()->toString(),
            'name' => 'Free',
            'storage_limit' => 2 * 1024 * 1024 * 1024,
            'video_limit' => 0,
            'team_limit' => 0,
        ]);

        $this->photographer = User::forceCreate([
            'email_verified_at' => now(),
            'uuid' => Uuid::uuid7()->toString(),
            'plan_id' => $plan->id,
            'name' => 'Huye Light',
            'username' => 'huyelight',
            'email' => 'huye@example.com',
            'password' => Hash::make('secret123'),
            'role' => 'photographer',
            'is_active' => true,
        ]);
    }

    protected function saveProfile(array $data)
    {
        return $this->actingAs($this->photographer)
            ->from(route('studio.settings.index'))
            ->post(route('studio.settings.profile'), ['name' => 'Huye Light'] + $data);
    }

    public function test_locations_match_cities_by_name_alias_and_neighbourhood(): void
    {
        $this->assertSame('kigali', Directory::matchCity('Kigali, Rwanda'));
        $this->assertSame('kigali', Directory::matchCity('Kimihurura'));
        $this->assertSame('kigali', Directory::matchCity('KICUKIRO - KGL'));
        $this->assertSame('rubavu', Directory::matchCity('Gisenyi, Lake Kivu'));
        $this->assertSame('dar-es-salaam', Directory::matchCity('Dar es Salaam, Tanzania'));
        $this->assertSame('musanze', Directory::matchCity('Musanze, near Kigali'), 'the first city written wins');
        $this->assertSame('huye', Directory::matchCity('Hüye'));
        $this->assertNull(Directory::matchCity('Rwanda'));
        $this->assertNull(Directory::matchCity('Gomaland'), 'only whole words match');
        $this->assertNull(Directory::matchCity(null));
    }

    public function test_daily_command_rematches_saved_locations_without_touching_updated_at(): void
    {
        // As if saved before the alias existed
        DB::table('users')->where('id', $this->photographer->id)->update(['location' => 'Ruhengeri', 'city' => null, 'updated_at' => '2026-01-01 00:00:00']);

        $this->artisan('directory:match-cities')->expectsOutput('Updated 1 profile.')->assertSuccessful();

        $fresh = $this->photographer->fresh();
        $this->assertSame('musanze', $fresh->city);
        $this->assertSame('2026-01-01 00:00:00', $fresh->updated_at->format('Y-m-d H:i:s'));

        $this->artisan('directory:match-cities')->expectsOutput('Updated 0 profiles.')->assertSuccessful();
    }

    public function test_location_is_matched_to_a_directory_city_on_save(): void
    {
        $this->saveProfile(['location' => 'Butare, Rwanda', 'specialties' => ['weddings', 'portraits']])
            ->assertSessionHasNoErrors();

        $this->photographer->refresh();
        $this->assertSame('Butare, Rwanda', $this->photographer->location);
        $this->assertSame('huye', $this->photographer->city);
        $this->assertSame('Huye', $this->photographer->cityName());
        $this->assertSame(['weddings', 'portraits'], $this->photographer->specialties);
        $this->assertSame(['Weddings', 'Portraits'], $this->photographer->specialtyLabels());
    }

    public function test_unmatched_or_cleared_locations_have_no_city(): void
    {
        $this->saveProfile(['location' => 'Kigali'])->assertSessionHasNoErrors();
        $this->assertSame('kigali', $this->photographer->fresh()->city);

        $this->saveProfile(['location' => 'Somewhere Else'])->assertSessionHasNoErrors();
        $this->assertSame('Somewhere Else', $this->photographer->fresh()->location);
        $this->assertNull($this->photographer->fresh()->city);

        $this->saveProfile(['location' => 'Kigali'])->assertSessionHasNoErrors();
        $this->saveProfile(['location' => ''])->assertSessionHasNoErrors();
        $this->assertNull($this->photographer->fresh()->city);
    }

    public function test_a_posted_city_is_ignored(): void
    {
        $this->saveProfile(['location' => 'Musanze', 'city' => 'nairobi'])->assertSessionHasNoErrors();

        $this->assertSame('musanze', $this->photographer->fresh()->city);
    }

    public function test_unknown_or_too_many_specialties_are_rejected(): void
    {
        $this->saveProfile(['specialties' => ['weddings', 'not-a-specialty']])->assertSessionHasErrors('specialties.1');
        $this->saveProfile(['specialties' => ['weddings', 'portraits', 'events', 'family', 'fashion', 'sports']])
            ->assertSessionHasErrors('specialties');

        $this->assertNull($this->photographer->fresh()->specialties);
    }

    public function test_clearing_specialties_stores_null(): void
    {
        $this->photographer->update(['specialties' => ['weddings']]);

        $this->saveProfile([])->assertSessionHasNoErrors();

        $this->assertNull($this->photographer->fresh()->specialties);
    }

    public function test_their_own_portfolio_address_is_not_saved_as_another_website(): void
    {
        $this->saveProfile(['website' => 'http://HuyeLight.localhost:8000/'])->assertSessionHasNoErrors();
        $this->assertNull($this->photographer->fresh()->website);

        $this->saveProfile(['website' => 'https://huyelight.com'])->assertSessionHasNoErrors();
        $this->assertSame('https://huyelight.com', $this->photographer->fresh()->website);
    }

    public function test_website_field_suggests_their_portfolio_address(): void
    {
        $this->actingAs($this->photographer)->get(route('studio.settings.index'))
            ->assertOk()
            ->assertSee('placeholder="http://huyelight.localhost:8000"', false)
            ->assertSee('Your ifotoset portfolio is already your website.');
    }

    public function test_settings_page_suggests_cities_and_offers_specialties(): void
    {
        $this->actingAs($this->photographer)->get(route('studio.settings.index'))
            ->assertOk()
            ->assertSee('<option value="Rubavu, Rwanda">', false)
            ->assertSee('value="maternity-newborn"', false);
    }

    public function test_settings_page_says_which_city_the_location_matched(): void
    {
        $this->photographer->update(['location' => 'Gisenyi']);
        $this->actingAs($this->photographer)->get(route('studio.settings.index'))
            ->assertSee('Matched to Rubavu');

        $this->photographer->update(['location' => 'Somewhere Else']);
        $this->actingAs($this->photographer)->get(route('studio.settings.index'))
            ->assertSee("We couldn&#039;t match this to a city yet.", false);
    }

    public function test_profile_checklist_step_needs_location_and_specialties(): void
    {
        $this->photographer->forceFill(['bio' => 'Portraits in Huye.', 'avatar_path' => 'avatars/huye.webp'])->save();

        $this->actingAs($this->photographer)->get(route('studio.dashboard'))
            ->assertViewHas('setup', fn (array $setup) => $setup['profile'] === false);

        // A town that is not in the directory still completes the step
        $this->photographer->update(['location' => 'Somewhere Else', 'specialties' => ['portraits']]);

        $this->actingAs($this->photographer)->get(route('studio.dashboard'))
            ->assertViewHas('setup', fn (array $setup) => $setup['profile'] === true);
    }

    public function test_portfolio_structured_data_lists_specialties(): void
    {
        $this->photographer->update(['location' => 'Butare', 'specialties' => ['weddings', 'family']]);

        $this->get(app(PublicUrlService::class)->photographer($this->photographer))
            ->assertOk()
            ->assertSee('"knowsAbout":["Weddings","Family"]', false)
            ->assertSee('"areaServed":"Butare"', false);
    }
}
