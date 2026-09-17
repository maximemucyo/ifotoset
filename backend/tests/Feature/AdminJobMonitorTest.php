<?php

namespace Tests\Feature;

use App\Models\Gallery;
use App\Models\GalleryDownload;
use App\Models\GooglePhotoSync;
use App\Models\MediaJob;
use App\Models\Photo;
use App\Models\Plan;
use App\Models\StorageDisk;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Ramsey\Uuid\Uuid;
use Tests\TestCase;

class AdminJobMonitorTest extends TestCase
{
    use RefreshDatabase;

    protected User $photographer;
    protected User $admin;
    protected Gallery $gallery;
    protected StorageDisk $disk;

    protected function setUp(): void
    {
        parent::setUp();

        $plan = Plan::firstOrCreate(['slug' => 'free'], [
            'uuid' => Uuid::uuid7()->toString(),
            'name' => 'Free Plan',
            'storage_limit' => 5 * 1024 * 1024 * 1024,
            'video_limit' => 0,
            'gallery_limit' => 5,
            'team_limit' => 0,
        ]);

        $this->photographer = User::create([
            'uuid' => Uuid::uuid7()->toString(),
            'plan_id' => $plan->id,
            'name' => 'Jean Photographer',
            'username' => 'jeanphoto',
            'email' => 'jean@example.com',
            'password' => Hash::make('secret123'),
            'is_active' => true,
        ]);

        $this->admin = User::create([
            'uuid' => Uuid::uuid7()->toString(),
            'plan_id' => $plan->id,
            'name' => 'Super Admin',
            'username' => 'superadmin',
            'email' => 'admin@example.com',
            'password' => Hash::make('secret123'),
            'is_active' => true,
        ]);
        $this->admin->role = 'admin';
        $this->admin->save();

        $this->disk = StorageDisk::firstOrCreate([
            'driver' => 'b2',
        ], [
            'uuid' => Uuid::uuid7()->toString(),
            'bucket' => 'ifotoset-media',
            'region' => 'us-east-005',
            'cdn_domain' => 'cdn.ifotoset.com',
        ]);

        $this->gallery = Gallery::create([
            'uuid' => Uuid::uuid7()->toString(),
            'user_id' => $this->photographer->id,
            'title' => 'Wedding Gala 2026',
            'slug' => 'wedding-gala-2026',
            'visibility' => 'public',
            'allow_gallery_downloads' => true,
        ]);
    }

    protected function createPhoto(array $attributes = []): Photo
    {
        return Photo::create(array_merge([
            'uuid' => Uuid::uuid7()->toString(),
            'gallery_id' => $this->gallery->id,
            'disk_id' => $this->disk->id,
            'path' => 'photos/test',
            'filename' => 'photo_' . uniqid() . '.jpg',
            'original_filename' => 'sample.jpg',
            'mime_type' => 'image/jpeg',
            'size' => 1024 * 500,
            'checksum' => hash('sha256', uniqid()),
            'status' => 'ready',
            'is_hidden' => false,
            'sort_order' => 0,
        ], $attributes));
    }

    public function test_guest_is_redirected_to_login_on_admin_queue_and_status(): void
    {
        $response = $this->get('/admin/queue');
        $response->assertRedirect('/login');

        $responseStatus = $this->getJson('/admin/queue/status');
        $responseStatus->assertStatus(401);
    }

    public function test_regular_photographer_is_forbidden(): void
    {
        $response = $this->actingAs($this->photographer)->get('/admin/queue');
        $response->assertRedirect(route('studio.dashboard'));

        $responseStatus = $this->actingAs($this->photographer)->getJson('/admin/queue/status');
        $responseStatus->assertStatus(403);
    }

    public function test_admin_can_access_queue_blade_page(): void
    {
        $photo = $this->createPhoto([
            'filename' => 'sample.jpg',
            'original_filename' => 'original_sample.jpg',
        ]);

        MediaJob::create([
            'photo_id' => $photo->id,
            'job_name' => 'ProcessPhotoJob',
            'status' => 'processing',
            'progress' => 'Generating & Uploading WebP',
            'attempts' => 1,
            'started_at' => now(),
        ]);

        $response = $this->actingAs($this->admin)->get('/admin/queue');

        $response->assertStatus(200);
        $response->assertViewIs('admin.jobs');
        $response->assertSee('Processing Queue &amp; Exports', false);
        $response->assertSee('Uploading WebP');
        $response->assertSee('original_sample.jpg');
    }

    public function test_admin_can_poll_queue_status_json(): void
    {
        $photo = $this->createPhoto([
            'filename' => 'sample.jpg',
            'original_filename' => 'sample_file.jpg',
        ]);

        MediaJob::create([
            'photo_id' => $photo->id,
            'job_name' => 'ProcessPhotoJob',
            'status' => 'queued',
            'attempts' => 0,
        ]);

        MediaJob::create([
            'photo_id' => $photo->id,
            'job_name' => 'ProcessPhotoJob',
            'status' => 'failed',
            'failed_at' => now(),
            'error_message' => 'Invalid image dimensions',
            'attempts' => 3,
        ]);

        GalleryDownload::create([
            'gallery_id' => $this->gallery->id,
            'status' => 'processing',
            'email' => 'client@private.com',
            'total_photos' => 10,
            'processed_photos' => 5,
            'failed_photos' => 0,
            'started_at' => now(),
        ]);

        $response = $this->actingAs($this->admin)->getJson('/admin/queue/status');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'metrics' => [
                'queued',
                'processing',
                'failed_today',
                'completed_today',
                'active_exports',
                'has_active_jobs',
            ],
            'has_active_jobs',
            'media_jobs',
            'exports',
        ]);

        $metrics = $response->json('metrics');
        $this->assertEquals(1, $metrics['queued']);
        $this->assertEquals(1, $metrics['failed_today']);
        $this->assertEquals(1, $metrics['active_exports']);
        $this->assertTrue($response->json('has_active_jobs'));
    }

    public function test_media_jobs_filtering_and_search(): void
    {
        $photoA = $this->createPhoto([
            'filename' => 'alpha.jpg',
            'original_filename' => 'alpha_original.jpg',
        ]);

        $photoB = $this->createPhoto([
            'filename' => 'beta.jpg',
            'original_filename' => 'beta_original.jpg',
        ]);

        MediaJob::create([
            'photo_id' => $photoA->id,
            'job_name' => 'ProcessPhotoJob',
            'status' => 'queued',
        ]);

        MediaJob::create([
            'photo_id' => $photoB->id,
            'job_name' => 'ProcessPhotoJob',
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        // Filter by status=queued
        $resQueued = $this->actingAs($this->admin)->get('/admin/queue?status=queued');
        $resQueued->assertStatus(200);
        $resQueued->assertSee('alpha_original.jpg');
        $resQueued->assertDontSee('beta_original.jpg');

        // Search by filename
        $resSearch = $this->actingAs($this->admin)->get('/admin/queue?search=beta');
        $resSearch->assertStatus(200);
        $resSearch->assertSee('beta_original.jpg');
        $resSearch->assertDontSee('alpha_original.jpg');
    }

    public function test_queue_status_masks_recipient_emails_and_avoids_leaking_secrets(): void
    {
        GalleryDownload::create([
            'gallery_id' => $this->gallery->id,
            'status' => 'processing',
            'email' => 'confidential_client@luxuryweddings.com',
            'total_photos' => 50,
            'processed_photos' => 20,
            'failed_photos' => 0,
            'started_at' => now(),
        ]);

        $response = $this->actingAs($this->admin)->getJson('/admin/queue/status');
        $response->assertStatus(200);

        $exports = $response->json('exports');
        $this->assertNotEmpty($exports);

        $firstExport = $exports[0];
        // Full unmasked email should NOT be returned
        $this->assertNotEquals('confidential_client@luxuryweddings.com', $firstExport['email']);
        $this->assertStringContainsString('*', $firstExport['email']);
        $this->assertStringContainsString('@luxuryweddings.com', $firstExport['email']);
    }

    public function test_google_photo_sync_indeterminate_progress(): void
    {
        GooglePhotoSync::create([
            'uuid' => Uuid::uuid7()->getBytes(),
            'gallery_id' => $this->gallery->id,
            'status' => 'processing',
            'total_photos' => 0, // Still discovering albums/photos
            'processed_photos' => 0,
            'failed_photos' => 0,
            'started_at' => now(),
        ]);

        $response = $this->actingAs($this->admin)->getJson('/admin/queue/status');
        $response->assertStatus(200);

        $exports = $response->json('exports');
        $googleSync = collect($exports)->firstWhere('type', 'google-photos');

        $this->assertNotNull($googleSync);
        $this->assertTrue($googleSync['indeterminate']);
        $this->assertEquals(0, $googleSync['percentage']);
    }
}
