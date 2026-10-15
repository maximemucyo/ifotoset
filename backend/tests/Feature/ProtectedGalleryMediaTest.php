<?php

namespace Tests\Feature;

use App\Jobs\RelocateGalleryMediaJob;
use App\Models\Gallery;
use App\Models\Photo;
use App\Models\Plan;
use App\Models\StorageDisk;
use App\Models\User;
use App\Services\StorageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Ramsey\Uuid\Uuid;
use Tests\TestCase;

class ProtectedGalleryMediaTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
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
    }

    protected function makeGallery(array $attributes = []): Gallery
    {
        return Gallery::create(array_merge([
            'uuid' => Uuid::uuid7()->toString(),
            'user_id' => $this->user->id,
            'title' => 'Wedding',
            'slug' => 'wedding',
            'visibility' => 'public',
        ], $attributes));
    }

    protected function makePhoto(Gallery $gallery, string $prefix): Photo
    {
        $photoUuid = Uuid::uuid7()->toString();

        return Photo::create([
            'uuid' => $photoUuid,
            'gallery_id' => $gallery->id,
            'disk_id' => $this->disk->id,
            'original_filename' => 'DSC_1.jpg',
            'stored_filename' => 'DSC_1.jpg',
            'filename' => 'DSC_1.jpg',
            'path' => "{$prefix}/photos/{$photoUuid}",
            'original_path' => "{$prefix}/photos/{$photoUuid}/DSC_1.jpg",
            'size' => 1024,
            'width' => 1920,
            'height' => 1080,
            'mime_type' => 'image/jpeg',
            'checksum' => hash('sha256', $photoUuid),
        ]);
    }

    public function test_protected_media_urls_carry_a_directory_token_and_public_ones_do_not(): void
    {
        $storage = app(StorageService::class);

        $public = $storage->getCdnUrl('galleries/abc/photos/p1', 'md', 'DSC_1.jpg', 'cdn.ifotoset.com');
        $this->assertSame('https://cdn.ifotoset.com/galleries/abc/photos/p1/DSC_1_md.webp', $public);

        $protected = $storage->getCdnUrl('protected-galleries/abc/photos/p1', 'md', 'DSC_1.jpg', 'cdn.ifotoset.com');
        $this->assertMatchesRegularExpression(
            '#^https://cdn\.ifotoset\.com/protected-galleries/abc/photos/p1/_t/(\d+)/([0-9a-f]{64})/DSC_1_md\.webp$#',
            $protected
        );

        // Signature matches what the edge worker verifies: "{dir}/:{exp}"
        preg_match('#/_t/(\d+)/([0-9a-f]{64})/#', $protected, $m);
        $this->assertTrue(app(\App\Services\MediaTokenService::class)->validateToken('protected-galleries/abc/photos/p1/', (int) $m[1], $m[2]));
        $this->assertGreaterThan(now()->addHours(6)->timestamp - 1, (int) $m[1]);

        // Stable within the window, so pages don't defeat the browser cache
        $this->assertSame($protected, $storage->getCdnUrl('protected-galleries/abc/photos/p1', 'md', 'DSC_1.jpg', 'cdn.ifotoset.com'));
    }

    public function test_adding_a_pin_queues_relocation_and_unrelated_edits_do_not(): void
    {
        Queue::fake();
        $gallery = $this->makeGallery();

        $gallery->update(['title' => 'Renamed']);
        Queue::assertNotPushed(RelocateGalleryMediaJob::class);

        $gallery->update(['visibility' => 'password', 'password_hash' => Hash::make('123456')]);
        Queue::assertPushed(RelocateGalleryMediaJob::class, fn ($job) => $job->galleryId === $gallery->id);
    }

    public function test_relocation_moves_objects_and_paths_under_the_protected_prefix(): void
    {
        Queue::fake();
        Storage::fake('b2');

        $gallery = $this->makeGallery();
        $photo = $this->makePhoto($gallery, "galleries/{$gallery->uuid}");
        Storage::disk('b2')->put($photo->original_path, 'original');
        Storage::disk('b2')->put("{$photo->path}/DSC_1_md.webp", 'variant');
        Storage::disk('b2')->put("galleries/{$gallery->uuid}/downloads/photos-x.zip", 'zip');

        $gallery->update(['visibility' => 'password', 'password_hash' => Hash::make('123456')]);
        (new RelocateGalleryMediaJob($gallery->id))->handle();

        $photo->refresh();
        $this->assertStringStartsWith("protected-galleries/{$gallery->uuid}/photos/", $photo->path);
        $this->assertStringStartsWith("protected-galleries/{$gallery->uuid}/photos/", $photo->original_path);
        Storage::disk('b2')->assertExists([$photo->original_path, "{$photo->path}/DSC_1_md.webp"]);
        $this->assertSame([], Storage::disk('b2')->allFiles("galleries/{$gallery->uuid}"));
        $this->assertStringContainsString('/_t/', $photo->getUrl('md'));

        // Removing the PIN moves everything back
        $gallery->update(['visibility' => 'public', 'password_hash' => null]);
        (new RelocateGalleryMediaJob($gallery->id))->handle();

        $photo->refresh();
        $this->assertStringStartsWith("galleries/{$gallery->uuid}/photos/", $photo->path);
        Storage::disk('b2')->assertExists($photo->original_path);
        $this->assertSame([], Storage::disk('b2')->allFiles("protected-galleries/{$gallery->uuid}"));
        $this->assertStringNotContainsString('/_t/', $photo->getUrl('md'));
    }

    public function test_command_finds_pin_galleries_with_media_under_the_public_prefix(): void
    {
        Queue::fake();

        $pinned = $this->makeGallery(['slug' => 'pinned', 'visibility' => 'password', 'password_hash' => Hash::make('123456')]);
        $this->makePhoto($pinned, "galleries/{$pinned->uuid}");

        $fine = $this->makeGallery(['slug' => 'fine']);
        $this->makePhoto($fine, "galleries/{$fine->uuid}");

        $this->artisan('media:relocate-galleries')->assertSuccessful();

        Queue::assertPushed(RelocateGalleryMediaJob::class, 1);
        Queue::assertPushed(RelocateGalleryMediaJob::class, fn ($job) => $job->galleryId === $pinned->id);
    }
}
