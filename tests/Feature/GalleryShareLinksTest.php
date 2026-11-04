<?php

namespace Tests\Feature;

use App\Models\Gallery;
use App\Models\GalleryShareLink;
use App\Models\Photo;
use App\Models\Plan;
use App\Models\StorageDisk;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Ramsey\Uuid\Uuid;
use Tests\TestCase;

class GalleryShareLinksTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected User $otherUser;
    protected Gallery $gallery;
    protected StorageDisk $disk;

    protected function setUp(): void
    {
        parent::setUp();

        $this->disk = StorageDisk::create([
            'uuid' => Uuid::uuid7()->toString(),
            'driver' => 'b2',
            'bucket' => 'ifotoset',
            'region' => 'eu-central-003',
            'cdn_domain' => 'cdn.ifotoset.com',
        ]);

        $plan = Plan::firstOrCreate(['slug' => 'pro'], [
            'uuid' => Uuid::uuid7()->toString(),
            'name' => 'Pro Plan',
            'storage_limit' => 50 * 1024 * 1024 * 1024,
            'video_limit' => 10,
            'gallery_limit' => 50,
            'team_limit' => 5,
        ]);

        foreach (['user' => 'alice', 'otherUser' => 'bob'] as $prop => $username) {
            $this->{$prop} = User::forceCreate([
                'email_verified_at' => now(),
                'uuid' => Uuid::uuid7()->toString(),
                'plan_id' => $plan->id,
                'name' => ucfirst($username) . ' Photographer',
                'username' => $username,
                'email' => "{$username}@example.com",
                'password' => Hash::make('secret123'),
            ]);
        }

        // Private and still a draft: a quick link opens anyway
        $this->gallery = Gallery::create([
            'uuid' => Uuid::uuid7()->toString(),
            'user_id' => $this->user->id,
            'title' => 'Diane & Kevin',
            'slug' => 'diane-kevin',
            'visibility' => 'private',
            'published_at' => null,
            'allow_photo_downloads' => true,
        ]);
    }

    protected function createPhoto(Gallery $gallery, array $attributes = []): Photo
    {
        return Photo::create(array_merge([
            'uuid' => Uuid::uuid7()->toString(),
            'gallery_id' => $gallery->id,
            'disk_id' => $this->disk->id,
            'path' => 'galleries/' . $gallery->uuid,
            'filename' => 'photo_' . uniqid() . '.jpg',
            'original_filename' => 'IMG_' . rand(1000, 9999) . '.JPG',
            'mime_type' => 'image/jpeg',
            'size' => 1024 * 1024,
            'width' => 1920,
            'height' => 1080,
            'checksum' => hash('sha256', uniqid()),
            'status' => 'ready',
            'is_hidden' => false,
            'sort_order' => 0,
        ], $attributes));
    }

    private function makeLink(array $photos): GalleryShareLink
    {
        $this->actingAs($this->user)
            ->postJson("/studio/galleries/{$this->gallery->uuid}/share-links", ['photos' => array_map(fn ($p) => $p->uuid, $photos)])
            ->assertOk()
            ->assertJsonPath('count', count(array_filter($photos, fn ($p) => $p->gallery_id === $this->gallery->id)));
        auth()->logout();

        return GalleryShareLink::latest('id')->first();
    }

    public function test_link_shows_only_the_chosen_photos_of_a_private_draft(): void
    {
        $chosen = $this->createPhoto($this->gallery, ['original_filename' => 'CHOSEN.JPG']);
        $this->createPhoto($this->gallery, ['original_filename' => 'NOT_CHOSEN.JPG']);
        $link = $this->makeLink([$chosen]);

        $this->get("/l/{$link->token}")->assertOk()
            ->assertSee('CHOSEN.JPG')
            ->assertDontSee('NOT_CHOSEN.JPG')
            ->assertSee('1 photo shared with you');

        $this->assertSame(1, $link->fresh()->views_count);
    }

    public function test_photos_from_another_gallery_are_ignored(): void
    {
        $other = Gallery::create([
            'uuid' => Uuid::uuid7()->toString(),
            'user_id' => $this->otherUser->id,
            'title' => 'Corporate',
            'slug' => 'corporate',
            'visibility' => 'public',
        ]);
        $foreign = $this->createPhoto($other);

        $this->actingAs($this->user)
            ->postJson("/studio/galleries/{$this->gallery->uuid}/share-links", ['photos' => [$foreign->uuid]])
            ->assertStatus(422);
        $this->actingAs($this->otherUser)
            ->postJson("/studio/galleries/{$this->gallery->uuid}/share-links", ['photos' => [$foreign->uuid]])
            ->assertForbidden();
    }

    public function test_hidden_photos_leave_the_link(): void
    {
        $photo = $this->createPhoto($this->gallery, ['original_filename' => 'LATER_HIDDEN.JPG']);
        $link = $this->makeLink([$photo]);
        $photo->update(['is_hidden' => true]);

        $this->get("/l/{$link->token}")->assertOk()->assertDontSee('LATER_HIDDEN.JPG');
        $this->get("/l/{$link->token}/photos/{$photo->uuid}/download")->assertNotFound();
    }

    public function test_downloads_follow_the_gallery_and_are_logged(): void
    {
        $photo = $this->createPhoto($this->gallery);
        $link = $this->makeLink([$photo]);

        $this->get("/l/{$link->token}/photos/{$photo->uuid}/download")->assertStatus(302);
        $this->assertDatabaseHas('activity_logs', ['gallery_id' => $this->gallery->id, 'event' => 'photo_downloaded', 'source' => 'share_link']);

        // A Download PIN keeps the link view-only
        $this->gallery->update(Gallery::downloadAttributes('1234'));
        $this->get("/l/{$link->token}")->assertOk()->assertDontSee('/download');
        $this->get("/l/{$link->token}/photos/{$photo->uuid}/download")->assertForbidden();
    }

    public function test_link_stops_when_deleted_or_the_gallery_expires(): void
    {
        $photo = $this->createPhoto($this->gallery);
        $link = $this->makeLink([$photo]);

        $this->gallery->update(['expires_at' => now()->subDay()]);
        $this->get("/l/{$link->token}")->assertNotFound();
        $this->gallery->update(['expires_at' => null]);
        $this->get("/l/{$link->token}")->assertOk();

        $this->actingAs($this->otherUser)->delete("/studio/galleries/{$this->gallery->uuid}/share-links/{$link->id}")->assertForbidden();
        $this->actingAs($this->user)->delete("/studio/galleries/{$this->gallery->uuid}/share-links/{$link->id}")->assertRedirect();
        $this->get("/l/{$link->token}")->assertNotFound();
    }

    public function test_links_are_listed_in_activity_and_share(): void
    {
        $photo = $this->createPhoto($this->gallery);
        $link = $this->makeLink([$photo]);

        $this->actingAs($this->user)->get("/studio/galleries/{$this->gallery->uuid}/activity?view=links")
            ->assertOk()->assertSee($link->url());
        $this->actingAs($this->user)->get("/studio/galleries/{$this->gallery->uuid}")
            ->assertOk()->assertSee($link->token);
    }
}
