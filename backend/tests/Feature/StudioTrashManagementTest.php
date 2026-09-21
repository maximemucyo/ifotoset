<?php

namespace Tests\Feature;

use App\Models\Gallery;
use App\Models\Photo;
use App\Models\Plan;
use App\Models\StorageDisk;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Ramsey\Uuid\Uuid;
use Tests\TestCase;

class StudioTrashManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected User $otherUser;
    protected StorageDisk $disk;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('b2');

        $plan = Plan::firstOrCreate([
            'slug' => 'pro',
        ], [
            'uuid' => Uuid::uuid7()->toString(),
            'name' => 'Pro Plan',
            'storage_limit' => 100 * 1024 * 1024 * 1024,
            'video_limit' => 0,
            'gallery_limit' => 50,
            'team_limit' => 0,
        ]);

        $this->user = User::create([
            'uuid' => Uuid::uuid7()->toString(),
            'plan_id' => $plan->id,
            'name' => 'Primary Photographer',
            'username' => 'photographer1',
            'email' => 'photo1@example.com',
            'password' => Hash::make('secret123'),
        ]);

        $this->otherUser = User::create([
            'uuid' => Uuid::uuid7()->toString(),
            'plan_id' => $plan->id,
            'name' => 'Other Photographer',
            'username' => 'photographer2',
            'email' => 'photo2@example.com',
            'password' => Hash::make('secret123'),
        ]);

        $this->disk = StorageDisk::firstOrCreate([
            'driver' => 'b2',
        ], [
            'uuid' => Uuid::uuid7()->toString(),
            'bucket' => 'ifotoset-media',
            'region' => 'us-east-005',
            'cdn_domain' => 'cdn.ifotoset.com',
        ]);
    }

    protected function createGallery(User $owner, array $attributes = []): Gallery
    {
        return Gallery::create(array_merge([
            'uuid' => Uuid::uuid7()->toString(),
            'user_id' => $owner->id,
            'title' => 'Sample Gallery',
            'slug' => 'sample-gallery-' . uniqid(),
            'visibility' => 'private',
        ], $attributes));
    }

    protected function createPhoto(Gallery $gallery, array $attributes = []): Photo
    {
        $photoUuid = Uuid::uuid7()->toString();
        $galleryUuid = (string) $gallery->uuid;
        $dirPath = "galleries/{$galleryUuid}/photos/{$photoUuid}";
        $filename = $attributes['filename'] ?? 'image.jpg';
        $baseName = pathinfo($filename, PATHINFO_FILENAME);

        $photo = Photo::create(array_merge([
            'uuid' => $photoUuid,
            'gallery_id' => $gallery->id,
            'disk_id' => $this->disk->id,
            'path' => $dirPath,
            'filename' => $filename,
            'original_filename' => $filename,
            'stored_filename' => $filename,
            'mime_type' => 'image/jpeg',
            'size' => 1024 * 500,
            'checksum' => hash('sha256', 'dummy'),
            'status' => 'ready',
        ], $attributes));

        // Place files on fake B2 storage disk
        Storage::disk('b2')->put("{$dirPath}/{$filename}", 'original-bytes');
        foreach (['xs', 'sm', 'md', 'lg', 'xl'] as $size) {
            Storage::disk('b2')->put("{$dirPath}/{$baseName}_{$size}.webp", "variant-{$size}-bytes");
        }

        return $photo;
    }

    public function test_get_to_purge_endpoint_returns_405_method_not_allowed(): void
    {
        $response = $this->actingAs($this->user)->get('/studio/trash/purge');
        $response->assertStatus(405);
    }

    public function test_post_gallery_purge_succeeds_and_deletes_b2_storage_and_db_records(): void
    {
        $gallery = $this->createGallery($this->user);
        $photo = $this->createPhoto($gallery);
        $galleryUuid = (string) $gallery->uuid;
        $photoUuid = (string) $photo->uuid;

        // Trashing gallery
        $gallery->delete();

        // Put a download archive
        Storage::disk('b2')->put("galleries/{$galleryUuid}/downloads/archive.zip", 'zip-bytes');

        $response = $this->actingAs($this->user)->post(route('studio.trash.purge'), [
            'type' => 'gallery',
            'uuid' => $galleryUuid,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        // Assert database records are permanently deleted
        $this->assertDatabaseMissing('galleries', ['id' => $gallery->id]);
        $this->assertDatabaseMissing('photos', ['id' => $photo->id]);

        // Assert all B2 storage items and variants are removed
        Storage::disk('b2')->assertMissing("galleries/{$galleryUuid}/photos/{$photoUuid}/image.jpg");
        Storage::disk('b2')->assertMissing("galleries/{$galleryUuid}/photos/{$photoUuid}/image_xs.webp");
        Storage::disk('b2')->assertMissing("galleries/{$galleryUuid}/photos/{$photoUuid}/image_xl.webp");
        Storage::disk('b2')->assertMissing("galleries/{$galleryUuid}/downloads/archive.zip");
    }

    public function test_delete_gallery_purge_compatibility_route_succeeds(): void
    {
        $gallery = $this->createGallery($this->user);
        $this->createPhoto($gallery);
        $gallery->delete();

        $response = $this->actingAs($this->user)->delete(route('studio.trash.purge'), [
            'type' => 'gallery',
            'uuid' => (string) $gallery->uuid,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertDatabaseMissing('galleries', ['id' => $gallery->id]);
    }

    public function test_post_photo_purge_succeeds_and_deletes_photo_variants_only(): void
    {
        $gallery = $this->createGallery($this->user);
        $photo1 = $this->createPhoto($gallery, ['filename' => 'p1.jpg']);
        $photo2 = $this->createPhoto($gallery, ['filename' => 'p2.jpg']);

        // Soft delete photo1 only
        $photo1->delete();

        $photo1Path = (string) $photo1->path;
        $photo2Path = (string) $photo2->path;

        $response = $this->actingAs($this->user)->post(route('studio.trash.purge'), [
            'type' => 'photo',
            'uuid' => (string) $photo1->uuid,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        // Photo 1 is gone from DB and storage
        $this->assertDatabaseMissing('photos', ['id' => $photo1->id]);
        Storage::disk('b2')->assertMissing("{$photo1Path}/p1.jpg");
        Storage::disk('b2')->assertMissing("{$photo1Path}/p1_xs.webp");
        Storage::disk('b2')->assertMissing("{$photo1Path}/p1_xl.webp");

        // Photo 2 and gallery remain completely intact
        $this->assertDatabaseHas('galleries', ['id' => $gallery->id, 'deleted_at' => null]);
        $this->assertDatabaseHas('photos', ['id' => $photo2->id, 'deleted_at' => null]);
        Storage::disk('b2')->assertExists("{$photo2Path}/p2.jpg");
        Storage::disk('b2')->assertExists("{$photo2Path}/p2_xs.webp");
    }

    public function test_delete_photo_purge_compatibility_route_succeeds(): void
    {
        $gallery = $this->createGallery($this->user);
        $photo = $this->createPhoto($gallery);
        $photo->delete();

        $response = $this->actingAs($this->user)->delete(route('studio.trash.purge'), [
            'type' => 'photo',
            'uuid' => (string) $photo->uuid,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseMissing('photos', ['id' => $photo->id]);
    }

    public function test_restore_gallery_clears_deleted_at(): void
    {
        $gallery = $this->createGallery($this->user);
        $gallery->delete();
        $this->assertNotNull($gallery->fresh()->deleted_at);

        $response = $this->actingAs($this->user)->post(route('studio.trash.restore'), [
            'type' => 'gallery',
            'uuid' => (string) $gallery->uuid,
        ]);

        $response->assertRedirect();
        $this->assertNull($gallery->fresh()->deleted_at);
    }

    public function test_restore_photo_clears_deleted_at(): void
    {
        $gallery = $this->createGallery($this->user);
        $photo = $this->createPhoto($gallery);
        $photo->delete();
        $this->assertNotNull($photo->fresh()->deleted_at);

        $response = $this->actingAs($this->user)->post(route('studio.trash.restore'), [
            'type' => 'photo',
            'uuid' => (string) $photo->uuid,
        ]);

        $response->assertRedirect();
        $this->assertNull($photo->fresh()->deleted_at);
    }

    public function test_empty_trash_purges_all_eligible_galleries_and_individual_photos(): void
    {
        // 1. Trashed gallery with photos
        $trashedGallery = $this->createGallery($this->user, ['title' => 'Trashed Gallery']);
        $p1 = $this->createPhoto($trashedGallery);
        $trashedGallery->delete();

        // 2. Active gallery with an individually trashed photo and an active photo
        $activeGallery = $this->createGallery($this->user, ['title' => 'Active Gallery']);
        $trashedPhoto = $this->createPhoto($activeGallery, ['filename' => 'trashed.jpg']);
        $activePhoto = $this->createPhoto($activeGallery, ['filename' => 'active.jpg']);
        $trashedPhoto->delete();

        $response = $this->actingAs($this->user)->post(route('studio.trash.empty'));
        $response->assertRedirect();
        $response->assertSessionHas('success');

        // Trashed gallery and its photos are purged
        $this->assertDatabaseMissing('galleries', ['id' => $trashedGallery->id]);
        $this->assertDatabaseMissing('photos', ['id' => $p1->id]);
        Storage::disk('b2')->assertMissing((string) $p1->path . '/image.jpg');

        // Individually trashed photo is purged
        $this->assertDatabaseMissing('photos', ['id' => $trashedPhoto->id]);
        Storage::disk('b2')->assertMissing((string) $trashedPhoto->path . '/trashed.jpg');

        // Active gallery and active photo are UNTOUCHED
        $this->assertDatabaseHas('galleries', ['id' => $activeGallery->id, 'deleted_at' => null]);
        $this->assertDatabaseHas('photos', ['id' => $activePhoto->id, 'deleted_at' => null]);
        Storage::disk('b2')->assertExists((string) $activePhoto->path . '/active.jpg');
    }

    public function test_cross_tenant_purge_is_forbidden(): void
    {
        $otherGallery = $this->createGallery($this->otherUser);
        $otherGallery->delete();

        $response = $this->actingAs($this->user)->post(route('studio.trash.purge'), [
            'type' => 'gallery',
            'uuid' => (string) $otherGallery->uuid,
        ]);

        $response->assertStatus(404);
        $this->assertDatabaseHas('galleries', ['id' => $otherGallery->id]);
    }

    public function test_scheduled_purge_command_respects_168_hour_retention_window(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 9, 21, 12, 0, 0));

        // 1. Exactly 6 days old (144 hours) - MUST BE RETAINED
        $gallery6Days = $this->createGallery($this->user, ['title' => '6 Days Old']);
        $photo6Days = $this->createPhoto($gallery6Days);
        $gallery6Days->delete();
        \Illuminate\Support\Facades\DB::table('galleries')->where('id', $gallery6Days->id)->update(['deleted_at' => Carbon::now()->subDays(6)]);

        // 2. Exactly 7 days and 1 hour old (169 hours) - MUST BE PURGED
        $gallery7Days = $this->createGallery($this->user, ['title' => '7 Days Old']);
        $photo7Days = $this->createPhoto($gallery7Days);
        $gallery7Days->delete();
        \Illuminate\Support\Facades\DB::table('galleries')->where('id', $gallery7Days->id)->update(['deleted_at' => Carbon::now()->subHours(169)]);

        // 3. 8 days old individually trashed photo in active gallery - MUST BE PURGED
        $activeGallery = $this->createGallery($this->user, ['title' => 'Active Gallery']);
        $photo8Days = $this->createPhoto($activeGallery, ['filename' => 'photo8d.jpg']);
        $photo8Days->delete();
        \Illuminate\Support\Facades\DB::table('photos')->where('id', $photo8Days->id)->update(['deleted_at' => Carbon::now()->subDays(8)]);

        // 4. Active photo in active gallery - MUST NEVER BE TOUCHED
        $activePhoto = $this->createPhoto($activeGallery, ['filename' => 'safe.jpg']);

        // Run scheduled command via Artisan facade
        $exitCode = \Illuminate\Support\Facades\Artisan::call('ifotoset:purge-expired-trash');
        $this->assertSame(0, $exitCode);

        // 6-day old item is preserved
        $this->assertDatabaseHas('galleries', ['id' => $gallery6Days->id]);
        $this->assertDatabaseHas('photos', ['id' => $photo6Days->id]);
        Storage::disk('b2')->assertExists((string) $photo6Days->path . '/image.jpg');

        // >= 7-day old items are permanently purged from DB and B2 storage
        $this->assertDatabaseMissing('galleries', ['id' => $gallery7Days->id]);
        $this->assertDatabaseMissing('photos', ['id' => $photo7Days->id]);
        Storage::disk('b2')->assertMissing((string) $photo7Days->path . '/image.jpg');

        $this->assertDatabaseMissing('photos', ['id' => $photo8Days->id]);
        Storage::disk('b2')->assertMissing((string) $photo8Days->path . '/photo8d.jpg');

        // Active records remain untouched
        $this->assertDatabaseHas('galleries', ['id' => $activeGallery->id, 'deleted_at' => null]);
        $this->assertDatabaseHas('photos', ['id' => $activePhoto->id, 'deleted_at' => null]);
        Storage::disk('b2')->assertExists((string) $activePhoto->path . '/safe.jpg');

        Carbon::setTestNow();
    }
}
