<?php

namespace Tests\Feature;

use App\Models\Gallery;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Ramsey\Uuid\Uuid;
use Tests\TestCase;

/**
 * The photographer opens their own PIN or private gallery from the studio without being asked for the PIN.
 */
class GalleryOwnerAccessTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;
    protected Gallery $gallery;

    protected function setUp(): void
    {
        parent::setUp();

        $plan = Plan::firstOrCreate(['slug' => 'pro'], [
            'uuid' => Uuid::uuid7()->toString(),
            'name' => 'Pro Plan',
            'storage_limit' => 50 * 1024 * 1024 * 1024,
            'video_limit' => 10,
            'gallery_limit' => 50,
            'team_limit' => 5,
        ]);

        $this->owner = $this->makeUser('johndoe', $plan);

        $this->gallery = Gallery::create([
            'uuid' => Uuid::uuid7()->toString(),
            'user_id' => $this->owner->id,
            'title' => 'Wedding Day',
            'slug' => 'wedding-day',
            'visibility' => 'password',
            'password_hash' => Hash::make('1234'),
            'download_pin' => '4821',
            'allow_photo_downloads' => true,
        ]);
    }

    private function makeUser(string $username, Plan $plan): User
    {
        return User::forceCreate([
            'email_verified_at' => now(),
            'uuid' => Uuid::uuid7()->toString(),
            'plan_id' => $plan->id,
            'name' => ucfirst($username),
            'username' => $username,
            'email' => "{$username}@example.com",
            'password' => Hash::make('password123'),
        ]);
    }

    private function galleryUrl(): string
    {
        return $this->gallery->public_url;
    }

    public function test_studio_link_hands_the_owner_over_to_their_pin_gallery(): void
    {
        $handOff = $this->actingAs($this->owner)->get(route('studio.galleries.open', $this->gallery->uuid));
        $handOff->assertRedirect();
        $signedUrl = $handOff->headers->get('Location');
        $this->assertStringStartsWith(app(\App\Services\PublicUrlService::class)->photographerUrl('johndoe') . '/wedding-day/owner?', $signedUrl);

        // The gallery host has its own session: being logged in to the studio does not carry over
        auth()->logout();
        $this->get($this->galleryUrl())->assertOk()->assertDontSee('Viewing as owner');

        $this->get($signedUrl)->assertRedirect($this->galleryUrl());

        $this->get($this->galleryUrl())
            ->assertOk()
            ->assertSee('Viewing as owner')
            ->assertViewHas('accessDecision', fn ($decision) => $decision->isGranted())
            ->assertViewHas('downloadsUnlocked', true);

        $this->getJson($this->galleryUrl() . '/photos')->assertOk();
    }

    public function test_owner_visit_is_not_counted_as_a_client_view(): void
    {
        $signedUrl = $this->actingAs($this->owner)->get(route('studio.galleries.open', $this->gallery->uuid))->headers->get('Location');
        $this->get($signedUrl);
        $this->get($this->galleryUrl())->assertOk();

        $this->assertDatabaseMissing('activity_logs', ['gallery_id' => $this->gallery->id, 'event' => 'gallery_viewed']);
    }

    public function test_tampered_or_expired_links_open_the_gallery_as_a_client_would(): void
    {
        $this->get($this->galleryUrl() . '/owner?account=' . $this->owner->id . '&expires=9999999999&signature=forged')
            ->assertRedirect($this->galleryUrl());
        $this->get($this->galleryUrl())->assertOk()->assertDontSee('Viewing as owner')
            ->assertViewHas('accessDecision', fn ($decision) => $decision->requiresPassword());

        $signedUrl = $this->actingAs($this->owner)->get(route('studio.galleries.open', $this->gallery->uuid))->headers->get('Location');
        auth()->logout();
        $this->travel(3)->minutes();
        $this->get($signedUrl);
        $this->get($this->galleryUrl())->assertDontSee('Viewing as owner');
    }

    public function test_other_photographers_cannot_get_an_owner_link(): void
    {
        $stranger = $this->makeUser('stranger', Plan::where('slug', 'pro')->first());

        $this->actingAs($stranger)->get(route('studio.galleries.open', $this->gallery->uuid))->assertForbidden();
    }
}
