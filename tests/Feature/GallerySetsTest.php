<?php

namespace Tests\Feature;

use App\Models\Gallery;
use App\Models\GallerySet;
use App\Models\Photo;
use App\Models\Plan;
use App\Models\StorageDisk;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Ramsey\Uuid\Uuid;
use Tests\TestCase;

class GallerySetsTest extends TestCase
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

        $this->user = User::forceCreate([
            'email_verified_at' => now(),
            'uuid' => Uuid::uuid7()->toString(),
            'plan_id' => $plan->id,
            'name' => 'Alice Photographer',
            'username' => 'alice',
            'email' => 'alice@example.com',
            'password' => Hash::make('secret123'),
        ]);

        $this->otherUser = User::forceCreate([
            'email_verified_at' => now(),
            'uuid' => Uuid::uuid7()->toString(),
            'plan_id' => $plan->id,
            'name' => 'Bob Photographer',
            'username' => 'bob',
            'email' => 'bob@example.com',
            'password' => Hash::make('secret123'),
        ]);

        $this->gallery = Gallery::create([
            'uuid' => Uuid::uuid7()->toString(),
            'user_id' => $this->user->id,
            'title' => 'Diane & Kevin',
            'slug' => 'diane-kevin',
            'visibility' => 'public',
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

    private function url(string $path = ''): string
    {
        return "/studio/galleries/{$this->gallery->uuid}{$path}";
    }

    public function test_photographer_adds_renames_reorders_and_deletes_sets(): void
    {
        $this->actingAs($this->user);

        $ceremony = $this->postJson($this->url('/sets'), ['name' => ' Ceremony '])->assertOk()->json('set');
        $reception = $this->postJson($this->url('/sets'), ['name' => 'Reception'])->assertOk()
            ->assertJsonPath('sets.0.name', 'Ceremony')
            ->assertJsonPath('sets.1.name', 'Reception')
            ->json('set');

        $this->patchJson($this->url("/sets/{$reception}"), ['name' => 'Party'])->assertOk()->assertJsonPath('sets.1.name', 'Party');

        $this->postJson($this->url('/sets/order'), ['sets' => [$reception, $ceremony]])->assertOk()
            ->assertJsonPath('sets.0.name', 'Party')
            ->assertJsonPath('sets.1.name', 'Ceremony');

        $photo = $this->createPhoto($this->gallery);
        $this->postJson($this->url('/photos/set'), ['photos' => [$photo->uuid], 'set' => $ceremony])->assertOk();

        // Deleting a set keeps its photos in the gallery
        $this->deleteJson($this->url("/sets/{$ceremony}"))->assertOk()->assertJsonCount(1, 'sets');
        $this->assertNull($photo->fresh()->set_id);
        $this->assertNotNull($photo->fresh());
    }

    public function test_set_name_is_required(): void
    {
        $this->actingAs($this->user)->postJson($this->url('/sets'), ['name' => '  '])->assertJsonValidationErrors('name');
        $this->assertSame(0, GallerySet::count());
    }

    public function test_moving_photos_only_touches_this_gallery(): void
    {
        $set = $this->gallery->sets()->create(['name' => 'Ceremony']);
        $mine = $this->createPhoto($this->gallery);
        $otherGallery = Gallery::create([
            'uuid' => Uuid::uuid7()->toString(),
            'user_id' => $this->otherUser->id,
            'title' => 'Corporate',
            'slug' => 'corporate',
            'visibility' => 'public',
        ]);
        $theirs = $this->createPhoto($otherGallery);

        $this->actingAs($this->user)
            ->postJson($this->url('/photos/set'), ['photos' => [$mine->uuid, $theirs->uuid], 'set' => $set->uuid])
            ->assertOk()
            ->assertJsonPath('moved', 1)
            ->assertJsonPath('sets.0.count', 1)
            ->assertJsonPath('message', '1 photo moved to Ceremony');

        $this->assertSame($set->id, $mine->fresh()->set_id);
        $this->assertNull($theirs->fresh()->set_id);

        // Out of every set
        $this->postJson($this->url('/photos/set'), ['photos' => [$mine->uuid], 'set' => null])->assertOk();
        $this->assertNull($mine->fresh()->set_id);
    }

    public function test_other_photographers_cannot_touch_sets(): void
    {
        $set = $this->gallery->sets()->create(['name' => 'Ceremony']);

        $this->actingAs($this->otherUser);
        $this->postJson($this->url('/sets'), ['name' => 'Mine now'])->assertForbidden();
        $this->patchJson($this->url("/sets/{$set->uuid}"), ['name' => 'Mine now'])->assertForbidden();
        $this->deleteJson($this->url("/sets/{$set->uuid}"))->assertForbidden();
        $this->assertSame('Ceremony', $set->fresh()->name);
    }

    public function test_studio_grid_filters_by_set(): void
    {
        $set = $this->gallery->sets()->create(['name' => 'Ceremony']);
        $inSet = $this->createPhoto($this->gallery, ['set_id' => $set->id]);
        $this->createPhoto($this->gallery);

        $this->actingAs($this->user)
            ->getJson($this->url('/photos?set=' . $set->uuid))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.uuid', $inSet->uuid)
            ->assertJsonPath('data.0.set', $set->uuid);

        $this->getJson($this->url('/photos'))->assertOk()->assertJsonCount(2, 'data');

        $this->get($this->url())->assertOk()->assertSee('Ceremony');
    }

    public function test_studio_grid_finds_photos_by_file_name(): void
    {
        $set = $this->gallery->sets()->create(['name' => 'Ceremony']);
        $wanted = $this->createPhoto($this->gallery, ['original_filename' => 'IMG_0412.JPG', 'set_id' => $set->id]);
        $this->createPhoto($this->gallery, ['original_filename' => 'IMG_0413.JPG']);
        $this->createPhoto($this->gallery, ['original_filename' => 'DSC_9000.JPG']);

        $this->actingAs($this->user);
        $this->getJson($this->url('/photos?q=img_0412'))->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.uuid', $wanted->uuid);
        $this->getJson($this->url('/photos?q=IMG_04'))->assertOk()->assertJsonCount(2, 'data');
        $this->getJson($this->url('/photos?q=IMG_04&set=' . $set->uuid))->assertOk()->assertJsonCount(1, 'data');

        // Wildcards are matched literally
        $this->getJson($this->url('/photos?q=%25'))->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_client_gallery_shows_sets_with_visible_photos_as_tabs(): void
    {
        $ceremony = $this->gallery->sets()->create(['name' => 'Ceremony', 'sort_order' => 1]);
        $empty = $this->gallery->sets()->create(['name' => 'Hidden only', 'sort_order' => 2]);
        $inSet = $this->createPhoto($this->gallery, ['set_id' => $ceremony->id, 'original_filename' => 'IMG_0100.JPG']);
        $this->createPhoto($this->gallery, ['set_id' => $empty->id, 'is_hidden' => true]);
        $this->createPhoto($this->gallery, ['original_filename' => 'IMG_0200.JPG']);

        $url = app(\App\Services\PublicUrlService::class)->galleryUrl('alice', 'diane-kevin');

        $this->get($url)->assertOk()
            ->assertSee('aria-label="Sets"', false)
            ->assertSee('Ceremony')
            ->assertDontSee('Hidden only')
            ->assertSee('IMG_0200.JPG');

        $this->get($url . '?set=' . $ceremony->uuid)->assertOk()
            ->assertSee('IMG_0100.JPG')
            ->assertDontSee('IMG_0200.JPG');

        $this->getJson($url . '/photos?set=' . $ceremony->uuid)->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.uuid', $inSet->uuid);

        // An unknown set shows the whole gallery
        $this->getJson($url . '/photos?set=nope')->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_gallery_without_sets_shows_no_tabs(): void
    {
        $this->createPhoto($this->gallery);
        $url = app(\App\Services\PublicUrlService::class)->galleryUrl('alice', 'diane-kevin');

        $this->get($url)->assertOk()->assertDontSee('aria-label="Sets"', false);
    }
}
