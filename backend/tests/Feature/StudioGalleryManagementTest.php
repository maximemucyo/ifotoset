<?php

namespace Tests\Feature;

use App\Models\Gallery;
use App\Models\Photo;
use App\Models\Plan;
use App\Models\StorageDisk;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Ramsey\Uuid\Uuid;
use Tests\TestCase;

class StudioGalleryManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected User $otherUser;
    protected Gallery $gallery;
    protected Gallery $otherGallery;
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

        $plan = Plan::firstOrCreate([
            'slug' => 'pro',
        ], [
            'uuid' => Uuid::uuid7()->toString(),
            'name' => 'Pro Plan',
            'storage_limit' => 50 * 1024 * 1024 * 1024,
            'video_limit' => 10,
            'gallery_limit' => 50,
            'team_limit' => 5,
        ]);

        $this->user = User::create([
            'uuid' => Uuid::uuid7()->toString(),
            'plan_id' => $plan->id,
            'name' => 'Alice Photographer',
            'username' => 'alice',
            'email' => 'alice@example.com',
            'password' => Hash::make('secret123'),
        ]);

        $this->otherUser = User::create([
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
            'title' => 'Wedding of Alice & Bob',
            'slug' => 'wedding-alice-bob',
            'visibility' => 'public',
            'status' => 'published',
            'allow_photo_downloads' => true,
        ]);

        $this->otherGallery = Gallery::create([
            'uuid' => Uuid::uuid7()->toString(),
            'user_id' => $this->otherUser->id,
            'title' => 'Corporate Event',
            'slug' => 'corporate-event',
            'visibility' => 'public',
            'status' => 'published',
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
            'original_filename' => 'DSC_' . rand(1000, 9999) . '.JPG',
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

    public function test_photographer_can_view_studio_gallery_with_cursor_photos(): void
    {
        $photo1 = $this->createPhoto($this->gallery, ['sort_order' => 1]);
        $photo2 = $this->createPhoto($this->gallery, ['sort_order' => 2]);

        $response = $this->actingAs($this->user)->get("/studio/galleries/{$this->gallery->uuid}");

        $response->assertStatus(200);
        $response->assertSee('studioGalleryManager', false);
        $response->assertSee($photo1->original_filename);
        $response->assertSee($photo2->original_filename);
    }

    public function test_studio_photos_endpoint_cursor_pagination(): void
    {
        $photos = [];
        for ($i = 1; $i <= 5; $i++) {
            $photos[] = $this->createPhoto($this->gallery, ['sort_order' => $i]);
        }

        // Fetch first page of 2 items
        $res1 = $this->actingAs($this->user)
            ->getJson("/studio/galleries/{$this->gallery->uuid}/photos?per_page=2");

        $res1->assertStatus(200)
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('has_more', true);

        $nextCursor = $res1->json('next_cursor');
        $this->assertNotEmpty($nextCursor);

        // Fetch second page using cursor
        $res2 = $this->actingAs($this->user)
            ->getJson("/studio/galleries/{$this->gallery->uuid}/photos?per_page=2&cursor={$nextCursor}");

        $res2->assertStatus(200)
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('has_more', true);

        $page1Uuids = collect($res1->json('data'))->pluck('uuid')->all();
        $page2Uuids = collect($res2->json('data'))->pluck('uuid')->all();

        // Ensure zero duplicates between cursor pages
        $this->assertEmpty(array_intersect($page1Uuids, $page2Uuids));
    }

    public function test_photographer_can_hide_and_unhide_photo(): void
    {
        $photo = $this->createPhoto($this->gallery, ['is_hidden' => false]);

        // Hide photo
        $hideResponse = $this->actingAs($this->user)
            ->patchJson("/studio/galleries/{$this->gallery->uuid}/photos/{$photo->uuid}/hide", [
                'hide' => true,
            ]);

        $hideResponse->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('photo.is_hidden', true);

        $this->assertTrue($photo->fresh()->is_hidden);

        // Unhide photo
        $unhideResponse = $this->actingAs($this->user)
            ->patchJson("/studio/galleries/{$this->gallery->uuid}/photos/{$photo->uuid}/hide", [
                'hide' => false,
            ]);

        $unhideResponse->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('photo.is_hidden', false);

        $this->assertFalse($photo->fresh()->is_hidden);
    }

    public function test_hidden_photo_is_excluded_from_public_gallery_surfaces(): void
    {
        $visiblePhoto = $this->createPhoto($this->gallery, ['is_hidden' => false]);
        $hiddenPhoto = $this->createPhoto($this->gallery, ['is_hidden' => true]);

        // 1. Studio listing still sees both
        $studioPhotos = $this->actingAs($this->user)
            ->getJson("/studio/galleries/{$this->gallery->uuid}/photos")
            ->json('data');

        $studioUuids = collect($studioPhotos)->pluck('uuid')->all();
        $this->assertContains($visiblePhoto->uuid, $studioUuids);
        $this->assertContains($hiddenPhoto->uuid, $studioUuids);

        // 2. Public gallery photos query strictly excludes hidden photo
        $publicPhotosRes = $this->getJson("/p/{$this->user->username}/{$this->gallery->slug}/photos");
        $publicPhotosRes->assertStatus(200);

        $publicUuids = collect($publicPhotosRes->json('data'))->pluck('uuid')->all();
        $this->assertContains($visiblePhoto->uuid, $publicUuids);
        $this->assertNotContains($hiddenPhoto->uuid, $publicUuids);

        // 3. Single photo resolver strictly rejects hidden photo
        $singleRes = $this->getJson("/p/{$this->user->username}/{$this->gallery->slug}/photos?uuid={$hiddenPhoto->uuid}");
        $singleRes->assertStatus(404);

        // 4. Direct public download strictly rejects hidden photo
        $downloadRes = $this->get("/p/{$this->user->username}/{$this->gallery->slug}/photos/{$hiddenPhoto->uuid}/download");
        $downloadRes->assertStatus(404);
    }

    public function test_hidden_photo_cannot_be_set_as_cover(): void
    {
        $hiddenPhoto = $this->createPhoto($this->gallery, ['is_hidden' => true]);

        $response = $this->actingAs($this->user)
            ->postJson("/studio/galleries/{$this->gallery->uuid}/cover", [
                'photo_uuid' => $hiddenPhoto->uuid,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['photo']);

        $this->assertNotEquals($hiddenPhoto->id, $this->gallery->fresh()->cover_photo_id);
    }

    public function test_hiding_current_cover_photo_reassigns_to_next_visible_photo(): void
    {
        $photo1 = $this->createPhoto($this->gallery, ['is_hidden' => false, 'sort_order' => 1]);
        $photo2 = $this->createPhoto($this->gallery, ['is_hidden' => false, 'sort_order' => 2]);

        // Explicitly set photo1 as cover
        $this->gallery->update(['cover_photo_id' => $photo1->id]);
        $this->assertEquals($photo1->id, $this->gallery->fresh()->cover_photo_id);

        // Hide photo1 (current cover)
        $this->actingAs($this->user)
            ->patchJson("/studio/galleries/{$this->gallery->uuid}/photos/{$photo1->uuid}/hide", [
                'hide' => true,
            ])
            ->assertStatus(200);

        // Invariant check: cover must be reassigned to photo2
        $this->assertEquals($photo2->id, $this->gallery->fresh()->cover_photo_id);
    }

    public function test_hiding_cover_photo_when_no_other_visible_photos_exist_clears_cover(): void
    {
        $photo = $this->createPhoto($this->gallery, ['is_hidden' => false]);
        $this->gallery->update(['cover_photo_id' => $photo->id]);

        // Hide the only photo
        $this->actingAs($this->user)
            ->patchJson("/studio/galleries/{$this->gallery->uuid}/photos/{$photo->uuid}/hide", [
                'hide' => true,
            ])
            ->assertStatus(200);

        // Invariant check: cover photo should be null
        $this->assertNull($this->gallery->fresh()->cover_photo_id);
    }

    public function test_cross_tenant_photo_manipulation_is_rejected(): void
    {
        $otherPhoto = $this->createPhoto($this->otherGallery);

        // User attempts to hide OtherUser's photo
        $this->actingAs($this->user)
            ->patchJson("/studio/galleries/{$this->otherGallery->uuid}/photos/{$otherPhoto->uuid}/hide", [
                'hide' => true,
            ])
            ->assertStatus(403);

        // User attempts to delete OtherUser's photo
        $this->actingAs($this->user)
            ->deleteJson("/studio/galleries/{$this->otherGallery->uuid}/photos/{$otherPhoto->uuid}")
            ->assertStatus(403);

        // User attempts to set OtherUser's photo as cover on own gallery
        $this->actingAs($this->user)
            ->postJson("/studio/galleries/{$this->gallery->uuid}/cover", [
                'photo_uuid' => $otherPhoto->uuid,
            ])
            ->assertStatus(404);
    }

    public function test_photo_soft_delete_removes_from_studio_and_reassigns_cover(): void
    {
        $photo1 = $this->createPhoto($this->gallery, ['sort_order' => 1]);
        $photo2 = $this->createPhoto($this->gallery, ['sort_order' => 2]);
        $this->gallery->update(['cover_photo_id' => $photo1->id]);

        $res = $this->actingAs($this->user)
            ->deleteJson("/studio/galleries/{$this->gallery->uuid}/photos/{$photo1->uuid}");

        $res->assertStatus(200)
            ->assertJsonPath('success', true);

        // Photo is soft deleted
        $this->assertSoftDeleted('photos', ['id' => $photo1->id]);

        // Cover automatically reassigned to photo2
        $this->assertEquals($photo2->id, $this->gallery->fresh()->cover_photo_id);
    }

    public function test_public_photo_count_differs_from_studio_count_when_photos_are_hidden(): void
    {
        $this->createPhoto($this->gallery, ['is_hidden' => false]);
        $this->createPhoto($this->gallery, ['is_hidden' => false]);
        $this->createPhoto($this->gallery, ['is_hidden' => true]);

        // Total Studio count should be 3
        $this->assertEquals(3, $this->gallery->photos()->count());

        // Public visible count should be 2
        $this->assertEquals(2, $this->gallery->public_photo_count);
    }
}
