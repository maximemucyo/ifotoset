<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Gallery;
use App\Models\Plan;
use App\Models\User;
use App\Services\PublicUrlService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Ramsey\Uuid\Uuid;
use Tests\TestCase;

class GalleryDraftAndShareTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected User $otherUser;

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

        [$this->user, $this->otherUser] = collect(['diane', 'kevin'])->map(fn ($name) => User::forceCreate([
            'email_verified_at' => now(),
            'uuid' => Uuid::uuid7()->toString(),
            'plan_id' => $plan->id,
            'name' => ucfirst($name) . ' Studio',
            'username' => $name,
            'email' => "{$name}@example.com",
            'password' => Hash::make('secret123'),
        ]))->all();
    }

    private function draft(array $attributes = []): Gallery
    {
        return Gallery::create(array_merge([
            'uuid' => Uuid::uuid7()->toString(),
            'user_id' => $this->user->id,
            'title' => 'Kevin & Aline',
            'slug' => 'kevin-aline',
            'visibility' => 'public',
            'published_at' => null,
        ], $attributes));
    }

    private function url(Gallery $gallery): string
    {
        return app(PublicUrlService::class)->galleryUrl($this->user->username, $gallery->slug);
    }

    public function test_new_gallery_is_a_draft_linked_to_a_new_client(): void
    {
        $this->actingAs($this->user)->post(route('studio.galleries.store'), [
            'title' => 'Kevin & Aline',
            'client_name' => 'Aline Uwase',
        ])->assertRedirect();

        $gallery = Gallery::where('title', 'Kevin & Aline')->firstOrFail();
        $this->assertTrue($gallery->isDraft());
        $this->assertNull($gallery->event_date);
        $this->assertSame('Aline Uwase', $gallery->client->name);
        $this->assertSame($this->user->id, $gallery->client->user_id);
    }

    public function test_picking_an_existing_client_links_it_and_ignores_other_studios_clients(): void
    {
        $mine = Client::create(['uuid' => Uuid::uuid7()->toString(), 'user_id' => $this->user->id, 'name' => 'Diane M.']);
        $theirs = Client::create(['uuid' => Uuid::uuid7()->toString(), 'user_id' => $this->otherUser->id, 'name' => 'Someone Else']);

        $this->actingAs($this->user)->post(route('studio.galleries.store'), ['title' => 'Mine', 'client_uuid' => $mine->uuid]);
        $this->assertSame($mine->id, Gallery::where('title', 'Mine')->firstOrFail()->client_id);

        $this->post(route('studio.galleries.store'), ['title' => 'Not theirs', 'client_uuid' => $theirs->uuid]);
        $this->assertNull(Gallery::where('title', 'Not theirs')->firstOrFail()->client_id);

        $this->getJson(route('studio.clients.lookup', ['q' => 'e']))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Diane M.');
    }

    public function test_clients_cannot_open_a_draft(): void
    {
        $gallery = $this->draft();

        $this->get($this->url($gallery))->assertOk()->assertSee("This gallery isn't ready yet");
        $this->getJson($this->url($gallery) . '/photos')->assertStatus(403)->assertJsonPath('code', 'GALLERY_DRAFT');
        $this->assertFalse($gallery->fresh()->hasPublicLink());
        $this->assertSame(0, Gallery::published()->count());

        // The photographer still sees it through the studio preview
        $this->actingAs($this->user)->get(route('studio.galleries.preview', $gallery->uuid))->assertOk();
    }

    public function test_galleries_created_outside_the_dialog_are_shared(): void
    {
        $gallery = Gallery::create([
            'uuid' => Uuid::uuid7()->toString(),
            'user_id' => $this->user->id,
            'title' => 'Seeded',
            'slug' => 'seeded',
            'visibility' => 'public',
        ]);

        $this->assertFalse($gallery->isDraft());
        $this->assertNotNull($gallery->fresh()->published_at);
    }

    public function test_sharing_opens_the_gallery_and_stopping_closes_it(): void
    {
        $gallery = $this->draft();

        $this->actingAs($this->user)
            ->post(route('studio.galleries.publish', $gallery->uuid), ['visibility' => 'password'])
            ->assertSessionHasErrors('password');
        $this->assertTrue($gallery->fresh()->isDraft());

        $this->post(route('studio.galleries.publish', $gallery->uuid), [
            'visibility' => 'public',
            'expires_on' => now()->addMonth()->format('Y-m-d'),
        ])->assertRedirect(route('studio.galleries.show', ['uuid' => $gallery->uuid, 'share' => 1]));

        $gallery->refresh();
        $this->assertFalse($gallery->isDraft());
        $this->assertNotNull($gallery->expires_at);
        $this->getJson($this->url($gallery) . '/photos')->assertOk();

        $this->post(route('studio.galleries.unpublish', $gallery->uuid))->assertRedirect();
        $this->assertTrue($gallery->fresh()->isDraft());
    }

    public function test_only_the_owner_can_share(): void
    {
        $gallery = $this->draft();

        $this->actingAs($this->otherUser)
            ->post(route('studio.galleries.publish', $gallery->uuid), ['visibility' => 'public'])
            ->assertForbidden();
        $this->assertTrue($gallery->fresh()->isDraft());
    }

    public function test_manager_shows_the_share_panel_for_a_draft(): void
    {
        $gallery = $this->draft();

        $this->actingAs($this->user)->get(route('studio.galleries.show', $gallery->uuid))
            ->assertOk()
            ->assertSee("Clients can't open the link until you share the gallery", false)
            ->assertSee(route('studio.galleries.publish', $gallery->uuid), false);
    }

    public function test_saving_settings_keeps_an_unlinked_client_name_without_creating_a_client(): void
    {
        $gallery = $this->draft(['client_name' => 'Kigali University']);

        $this->actingAs($this->user)->patch(route('studio.galleries.update', $gallery->uuid), [
            'title' => $gallery->title,
            'visibility' => 'public',
            'client_name' => 'Kigali University',
        ])->assertSessionHasNoErrors();

        $this->assertNull($gallery->fresh()->client_id);
        $this->assertSame('Kigali University', $gallery->fresh()->client_name);
        $this->assertSame(0, Client::count());

        // Editing the name links it like any new client
        $this->patch(route('studio.galleries.update', $gallery->uuid), [
            'title' => $gallery->title,
            'visibility' => 'public',
            'client_name' => 'University of Rwanda',
        ]);
        $this->assertSame('University of Rwanda', $gallery->fresh()->client->name);
    }
}
