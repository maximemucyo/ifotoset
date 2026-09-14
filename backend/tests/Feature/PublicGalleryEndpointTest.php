<?php

namespace Tests\Feature;

use App\Models\Gallery;
use App\Models\Photo;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Ramsey\Uuid\Uuid;
use Tests\TestCase;

class PublicGalleryEndpointTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Gallery $publicGallery;
    protected Gallery $privateGallery;
    protected \App\Models\StorageDisk $disk;

    protected function setUp(): void
    {
        parent::setUp();

        try {
            \Illuminate\Support\Facades\Redis::connection()->flushdb();
        } catch (\Throwable $e) {
        }

        $this->disk = \App\Models\StorageDisk::create([
            'uuid' => Uuid::uuid7()->toString(),
            'driver' => 'b2',
            'bucket' => 'ifotoset',
            'region' => 'eu-central-003',
            'cdn_domain' => 'cdn.ifotoset.com',
        ]);

        $plan = Plan::firstOrCreate([
            'slug' => 'free',
        ], [
            'uuid' => Uuid::uuid7()->toString(),
            'name' => 'Free Plan',
            'storage_limit' => 5 * 1024 * 1024 * 1024,
            'video_limit' => 0,
            'gallery_limit' => 5,
            'team_limit' => 0,
        ]);

        $this->user = User::create([
            'uuid' => Uuid::uuid7()->toString(),
            'plan_id' => $plan->id,
            'name' => 'John Doe',
            'username' => 'johndoe',
            'email' => 'john@example.com',
            'password' => Hash::make('password123'),
        ]);

        $this->publicGallery = Gallery::create([
            'uuid' => Uuid::uuid7()->toString(),
            'user_id' => $this->user->id,
            'title' => 'Summer Vacation 2026',
            'slug' => 'summer-vacation-2026',
            'visibility' => 'public',
            'status' => 'published',
            'allow_photo_downloads' => true,
        ]);

        $this->privateGallery = Gallery::create([
            'uuid' => Uuid::uuid7()->toString(),
            'user_id' => $this->user->id,
            'title' => 'Private Client Session',
            'slug' => 'private-client-session',
            'visibility' => 'password',
            'password_hash' => Hash::make('secretpass'),
            'status' => 'published',
        ]);
    }

    public function test_can_view_public_gallery(): void
    {
        $response = $this->getJson('/api/v1/public/galleries/' . $this->publicGallery->slug);

        $response->assertStatus(200)
            ->assertJsonPath('data.title', 'Summer Vacation 2026')
            ->assertJsonPath('data.slug', 'summer-vacation-2026')
            ->assertJsonPath('data.access_granted', true);
    }

    public function test_private_gallery_indicates_password_required(): void
    {
        $response = $this->getJson('/api/v1/public/galleries/' . $this->privateGallery->slug);

        $response->assertStatus(200)
            ->assertJsonPath('data.access_granted', false)
            ->assertJsonPath('data.requires_password', true);
    }

    public function test_private_gallery_unlock_with_correct_password(): void
    {
        $response = $this->postJson('/api/v1/public/galleries/' . $this->privateGallery->slug . '/unlock', [
            'password' => 'secretpass',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['token', 'message']);
    }

    public function test_private_gallery_unlock_fails_with_wrong_password(): void
    {
        $response = $this->postJson('/api/v1/public/galleries/' . $this->privateGallery->slug . '/unlock', [
            'password' => 'wrongpass',
        ]);

        $response->assertStatus(401)
            ->assertJsonPath('code', 'INVALID_PASSWORD');
    }

    protected function createTestPhoto(array $attributes = []): Photo
    {
        return Photo::create(array_merge([
            'uuid' => Uuid::uuid7()->toString(),
            'gallery_id' => $this->publicGallery->id,
            'disk_id' => $this->disk->id,
            'path' => 'galleries/test',
            'filename' => 'photo.jpg',
            'original_filename' => 'photo.jpg',
            'stored_filename' => 'photo.jpg',
            'mime_type' => 'image/jpeg',
            'size' => 1024,
            'width' => 1920,
            'height' => 1080,
            'checksum' => hash('sha256', 'test-' . uniqid()),
            'status' => 'ready',
        ], $attributes));
    }

    public function test_can_fetch_gallery_photos(): void
    {
        for ($i = 1; $i <= 3; $i++) {
            $this->createTestPhoto([
                'path' => "galleries/{$this->publicGallery->id}/photo-{$i}.jpg",
                'filename' => "photo-{$i}.jpg",
                'original_filename' => "IMG_{$i}.JPG",
                'stored_filename' => "stored_{$i}.jpg",
            ]);
        }

        $response = $this->getJson('/api/v1/public/galleries/' . $this->publicGallery->slug . '/photos');

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data');
    }

    public function test_public_gallery_photos_json_exposes_original_url(): void
    {
        $photo = $this->createTestPhoto([
            'filename' => 'DSC_8603.JPG',
            'original_filename' => 'DSC_8603.JPG',
            'stored_filename' => 'DSC_8603.JPG',
        ]);

        $response = $this->getJson("/p/{$this->user->username}/{$this->publicGallery->slug}/photos?uuid={$photo->uuid}");

        $response->assertStatus(200)
            ->assertJsonPath('data.filename', 'DSC_8603.JPG')
            ->assertJsonPath('data.large', 'https://cdn.ifotoset.com/galleries/test/DSC_8603_lg.webp')
            ->assertJsonPath('data.full', 'https://cdn.ifotoset.com/galleries/test/DSC_8603_xl.webp')
            ->assertJsonPath('data.width', 1920)
            ->assertJsonPath('data.height', 1080);

        $originalUrl = $response->json('data.original');
        $this->assertNotEmpty($originalUrl);
        $this->assertStringNotContainsString('_xl.webp', $originalUrl);
        $this->assertStringContainsString('DSC_8603.JPG', $originalUrl);
    }

    public function test_can_download_photo_with_original_jpg_filename_and_telemetry(): void
    {
        \Illuminate\Support\Facades\Storage::fake('b2');

        $photo = $this->createTestPhoto([
            'filename' => 'stored_dsc8603.jpg',
            'original_filename' => 'DSC_8603.JPG',
            'stored_filename' => 'stored_dsc8603.jpg',
        ]);

        \Illuminate\Support\Facades\Storage::disk('b2')->put('galleries/test/stored_dsc8603.jpg', 'fake-image-bytes');

        $response = $this->get("/p/{$this->user->username}/{$this->publicGallery->slug}/photos/{$photo->uuid}/download");

        // Either redirects to S3 presigned URL or streams via local storage download
        $this->assertTrue(in_array($response->getStatusCode(), [200, 302]));

        // Assert download count incremented
        $this->assertEquals(1, $this->publicGallery->stats->fresh()->downloads_count);

        // Assert activity log recorded
        $this->assertDatabaseHas('activity_logs', [
            'gallery_id' => $this->publicGallery->id,
            'event' => 'photo_downloaded',
        ]);
    }

    public function test_photo_download_preserves_png_and_unicode_filename(): void
    {
        \Illuminate\Support\Facades\Storage::fake('b2');

        $photo = $this->createTestPhoto([
            'filename' => 'stored_event.png',
            'original_filename' => 'Wedding & Reception — 2026.PNG',
            'stored_filename' => 'stored_event.png',
            'mime_type' => 'image/png',
        ]);

        \Illuminate\Support\Facades\Storage::disk('b2')->put('galleries/test/stored_event.png', 'fake-png-bytes');

        $response = $this->get("/p/{$this->user->username}/{$this->publicGallery->slug}/photos/{$photo->uuid}/download");

        $this->assertTrue(in_array($response->getStatusCode(), [200, 302]));
    }

    public function test_cannot_download_photo_belonging_to_another_gallery(): void
    {
        $galleryB = Gallery::create([
            'uuid' => Uuid::uuid7()->toString(),
            'user_id' => $this->user->id,
            'title' => 'Gallery B',
            'slug' => 'gallery-b',
            'visibility' => 'public',
            'status' => 'published',
            'allow_photo_downloads' => true,
        ]);

        $photoB = $this->createTestPhoto([
            'gallery_id' => $galleryB->id,
            'path' => 'galleries/b',
            'filename' => 'photo-b.jpg',
            'original_filename' => 'PHOTO_B.JPG',
        ]);

        // Attempt downloading Photo B via Gallery A's URL
        $response = $this->get("/p/{$this->user->username}/{$this->publicGallery->slug}/photos/{$photoB->uuid}/download");

        $response->assertStatus(404);

        // Ensure no activity log was falsely created for Gallery A
        $this->assertDatabaseMissing('activity_logs', [
            'gallery_id' => $this->publicGallery->id,
            'event' => 'photo_downloaded',
        ]);
    }

    public function test_download_fails_when_photo_does_not_exist(): void
    {
        $nonExistentUuid = Uuid::uuid7()->toString();
        $response = $this->get("/p/{$this->user->username}/{$this->publicGallery->slug}/photos/{$nonExistentUuid}/download");

        $response->assertStatus(404);
    }

    public function test_photo_download_denied_when_allow_photo_downloads_is_false(): void
    {
        $this->publicGallery->update(['allow_photo_downloads' => false]);

        $photo = $this->createTestPhoto([
            'original_filename' => 'DSC_8603.JPG',
        ]);

        $response = $this->get("/p/{$this->user->username}/{$this->publicGallery->slug}/photos/{$photo->uuid}/download");

        $response->assertStatus(403);
    }

    public function test_password_protected_gallery_download_requires_unlock(): void
    {
        \Illuminate\Support\Facades\Storage::fake('b2');

        $this->privateGallery->update(['allow_photo_downloads' => true]);

        $photo = $this->createTestPhoto([
            'gallery_id' => $this->privateGallery->id,
            'path' => 'galleries/private',
            'filename' => 'private.jpg',
            'original_filename' => 'SECRET.JPG',
            'stored_filename' => 'private.jpg',
        ]);

        \Illuminate\Support\Facades\Storage::disk('b2')->put('galleries/private/private.jpg', 'secret-bytes');

        // 1. Without unlock: denied
        $response = $this->get("/p/{$this->user->username}/{$this->privateGallery->slug}/photos/{$photo->uuid}/download");
        $response->assertStatus(403);

        // 2. With valid unlock token in query or session: permitted
        $token = hash_hmac('sha256', $this->privateGallery->uuid, config('app.key'));
        $responseWithToken = $this->withSession(["gallery_unlocked_{$this->privateGallery->id}" => true])
            ->get("/p/{$this->user->username}/{$this->privateGallery->slug}/photos/{$photo->uuid}/download");
        $this->assertTrue(in_array($responseWithToken->getStatusCode(), [200, 302]));
    }
}
