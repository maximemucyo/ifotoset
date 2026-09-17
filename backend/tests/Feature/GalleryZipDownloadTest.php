<?php

namespace Tests\Feature;

use App\Models\Gallery;
use App\Models\GalleryDownload;
use App\Models\GalleryStats;
use App\Models\Photo;
use App\Models\StorageDisk;
use App\Models\User;
use App\Jobs\GenerateGalleryZipJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class GalleryZipDownloadTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Gallery $gallery;
    protected StorageDisk $disk;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('app.frontend_url', 'https://test-frontend.ifotoset.com');
        Config::set('app.key', 'base64:zZ6kS/fXvF3OpeX+oI4/W+D7K4N+4V8i3L9O+8U0E3A='); // fixed app key for testing expected token

        // Setup user/plan
        $plan = \App\Models\Plan::firstOrCreate([
            'slug' => 'free',
        ], [
            'uuid' => \Ramsey\Uuid\Uuid::uuid7()->toString(),
            'name' => 'Free Plan',
            'storage_limit' => 5 * 1024 * 1024 * 1024,
            'video_limit' => 0,
            'gallery_limit' => 3,
            'team_limit' => 0,
        ]);

        $this->user = User::create([
            'uuid' => \Ramsey\Uuid\Uuid::uuid7()->toString(),
            'plan_id' => $plan->id,
            'name' => 'Test Photographer',
            'username' => 'testphoto',
            'email' => 'photo@example.com',
            'password' => Hash::make('password123'),
        ]);

        $this->disk = StorageDisk::firstOrCreate([
            'driver' => 'b2',
        ], [
            'uuid' => \Ramsey\Uuid\Uuid::uuid7()->toString(),
            'bucket' => 'ifotoset-media',
            'region' => 'us-east-005',
            'cdn_domain' => 'cdn.ifotoset.com',
        ]);

        $this->gallery = Gallery::create([
            'uuid' => \Ramsey\Uuid\Uuid::uuid7()->toString(),
            'user_id' => $this->user->id,
            'title' => 'Test Gallery Unicode / Special Characters — Wedding & Reception 2026!',
            'slug' => 'test-gallery',
            'visibility' => 'public',
            'allow_gallery_downloads' => true,
        ]);

        $mockStorageService = $this->createMock(\App\Services\StorageService::class);
        $mockStorageService->method('generatePresignedDownloadUrl')
            ->will($this->returnCallback(function ($objectKey, $filename, $expiresAt) {
                return 'https://backblazeb2.com/' . $objectKey . '?ResponseContentDisposition=attachment%3B%20filename%3D%22' . rawurlencode($filename) . '%22';
            }));
        $this->app->instance(\App\Services\StorageService::class, $mockStorageService);
    }

    protected function createPhoto(array $attributes = []): Photo
    {
        return Photo::create(array_merge([
            'uuid' => \Ramsey\Uuid\Uuid::uuid7()->toString(),
            'gallery_id' => $this->gallery->id,
            'disk_id' => $this->disk->id,
            'path' => 'photos/test',
            'filename' => 'photo_' . uniqid() . '.jpg',
            'original_filename' => 'photo1.jpg',
            'mime_type' => 'image/jpeg',
            'size' => 1024 * 500,
            'checksum' => hash('sha256', uniqid()),
            'status' => \App\Enums\PhotoStatus::Ready->value,
            'is_hidden' => false,
            'sort_order' => 0,
        ], $attributes));
    }

    public function test_valid_token_downloads_successfully(): void
    {
        Storage::fake('b2');

        $download = GalleryDownload::create([
            'gallery_id' => $this->gallery->id,
            'status' => 'ready',
            'completed_at' => now(),
            'storage_path' => 'galleries/test/photos.zip',
            'size' => 1024,
            'download_token_hash' => hash('sha256', 'valid-token'),
        ]);

        // Place mock zip file in storage
        Storage::disk('b2')->put('galleries/test/photos.zip', 'zip content');

        $response = $this->get("/api/v1/public/galleries/{$this->gallery->slug}/download-zip/{$download->id}/download?token=valid-token");

        // Should redirect (302) to presigned URL
        $response->assertStatus(302);
        $response->assertHeader('Referrer-Policy', 'no-referrer');
        $this->assertStringContainsString('backblazeb2.com', $response->headers->get('Location'));

        // Check content disposition filename has been sanitized correctly
        $this->assertStringContainsString('ResponseContentDisposition=attachment%3B%20filename%3D%22Test-Gallery-Unicode-Special-Characters-Wedding-Reception-2026.zip%22', $response->headers->get('Location'));

        // Verify tracking logs
        $this->assertDatabaseHas('activity_logs', [
            'gallery_id' => $this->gallery->id,
            'event' => 'gallery_zip_file_downloaded',
        ]);
        
        $this->assertEquals(1, $this->gallery->stats->fresh()->downloads_count);
    }

    public function test_invalid_token_is_rejected_without_fallback(): void
    {
        Storage::fake('b2');

        $download = GalleryDownload::create([
            'gallery_id' => $this->gallery->id,
            'status' => 'ready',
            'completed_at' => now(),
            'storage_path' => 'galleries/test/photos.zip',
            'download_token_hash' => hash('sha256', 'valid-token'),
        ]);

        $response = $this->get("/api/v1/public/galleries/{$this->gallery->slug}/download-zip/{$download->id}/download?token=invalid-token");

        // Should redirect back to export page with unauthorized error and without token in redirect URL
        $response->assertStatus(302);
        $this->assertStringContainsString('error=unauthorized', $response->headers->get('Location'));
        $this->assertStringNotContainsString('token=', $response->headers->get('Location'));

        // Ensure no tracking logs created
        $this->assertDatabaseMissing('activity_logs', [
            'event' => 'gallery_zip_file_downloaded',
        ]);
    }

    public function test_missing_token_can_use_normal_gallery_authorization(): void
    {
        Storage::fake('b2');

        // Make gallery private and password protected
        $this->gallery->update([
            'visibility' => 'private',
            'password_hash' => bcrypt('secret-pass'),
        ]);

        $download = GalleryDownload::create([
            'gallery_id' => $this->gallery->id,
            'status' => 'ready',
            'completed_at' => now(),
            'storage_path' => 'galleries/test/photos.zip',
            'download_token_hash' => hash('sha256', 'valid-token'),
        ]);

        Storage::disk('b2')->put('galleries/test/photos.zip', 'zip content');

        // Access without token query parameter and without password session
        $response = $this->get("/api/v1/public/galleries/{$this->gallery->slug}/download-zip/{$download->id}/download");
        $response->assertStatus(302);
        $response->assertRedirectContains('error=unauthorized');

        // Access with proper X-Gallery-Token in header
        $validToken = hash_hmac('sha256', $this->gallery->uuid, config('app.key'));
        $response = $this->withHeaders(['X-Gallery-Token' => $validToken])
            ->get("/api/v1/public/galleries/{$this->gallery->slug}/download-zip/{$download->id}/download");

        $response->assertStatus(302);
        $this->assertStringContainsString('backblazeb2.com', $response->headers->get('Location'));
    }

    public function test_token_from_zip_a_cannot_download_zip_b(): void
    {
        Storage::fake('b2');

        $downloadA = GalleryDownload::create([
            'gallery_id' => $this->gallery->id,
            'status' => 'ready',
            'completed_at' => now(),
            'storage_path' => 'galleries/test/photosA.zip',
            'download_token_hash' => hash('sha256', 'token-a'),
        ]);

        $downloadB = GalleryDownload::create([
            'gallery_id' => $this->gallery->id,
            'status' => 'ready',
            'completed_at' => now(),
            'storage_path' => 'galleries/test/photosB.zip',
            'download_token_hash' => hash('sha256', 'token-b'),
        ]);

        Storage::disk('b2')->put('galleries/test/photosB.zip', 'zip content');

        // Access ZIP B using ZIP A's token
        $response = $this->get("/api/v1/public/galleries/{$this->gallery->slug}/download-zip/{$downloadB->id}/download?token=token-a");
        $response->assertStatus(302);
        $response->assertRedirectContains('error=unauthorized');
    }

    public function test_expired_zip_is_rejected_and_updated_in_database(): void
    {
        Storage::fake('b2');

        $download = GalleryDownload::create([
            'gallery_id' => $this->gallery->id,
            'status' => 'ready',
            'completed_at' => now()->subHours(25), // older than 24 hours
            'storage_path' => 'galleries/test/photos.zip',
            'download_token_hash' => hash('sha256', 'token'),
        ]);

        Storage::disk('b2')->put('galleries/test/photos.zip', 'zip content');

        $response = $this->get("/api/v1/public/galleries/{$this->gallery->slug}/download-zip/{$download->id}/download?token=token");
        $response->assertStatus(302);
        $response->assertRedirectContains('error=expired');

        // Verify status changed to expired and storage file deleted
        $download->refresh();
        $this->assertEquals('expired', $download->status);
        $this->assertNull($download->storage_path);
        $this->assertNotNull($download->expired_at);
        Storage::disk('b2')->assertMissing('galleries/test/photos.zip');
    }

    public function test_cleanup_expired_zips_command_removes_them(): void
    {
        Storage::fake('b2');

        $download = GalleryDownload::create([
            'gallery_id' => $this->gallery->id,
            'status' => 'ready',
            'completed_at' => now()->subHours(25), // older than 24 hours
            'storage_path' => 'galleries/test/expired.zip',
            'download_token_hash' => hash('sha256', 'token'),
        ]);

        Storage::disk('b2')->put('galleries/test/expired.zip', 'zip content');

        $this->artisan('gallery:cleanup-expired-zips')
            ->expectsOutput('Checking for expired ZIP downloads (older than 24 hours)...')
            ->expectsOutput('Cleanup process complete! Expired and updated 1 ZIP downloads.')
            ->assertExitCode(0);

        $download->refresh();
        $this->assertEquals('expired', $download->status);
        Storage::disk('b2')->assertMissing('galleries/test/expired.zip');
    }

    public function test_gallery_analytics_endpoint_returns_correct_data(): void
    {
        // Add activity logs
        \Illuminate\Support\Facades\DB::table('activity_logs')->insert([
            [
                'gallery_id' => $this->gallery->id,
                'event' => 'gallery_viewed',
                'visitor_session_id' => 'session-1',
                'properties' => json_encode([]),
                'created_at' => now(),
            ],
            [
                'gallery_id' => $this->gallery->id,
                'event' => 'photo_downloaded',
                'visitor_session_id' => 'session-1',
                'properties' => json_encode(['email' => 'visitor@example.com']),
                'created_at' => now(),
            ],
            [
                'gallery_id' => $this->gallery->id,
                'event' => 'photo_favorited',
                'visitor_session_id' => 'session-2',
                'properties' => json_encode(['email' => 'visitor@example.com']),
                'created_at' => now(),
            ],
        ]);

        // Unauthorized request should fail
        $response = $this->getJson("/api/v1/galleries/{$this->gallery->uuid}/analytics");
        $response->assertStatus(401);

        // Authorized request
        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/galleries/{$this->gallery->uuid}/analytics");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'overview' => ['views', 'downloads', 'favorites', 'visitors'],
            'visitors',
            'recent_activity',
        ]);

        $data = $response->json();
        $this->assertEquals(1, $data['overview']['views']);
        $this->assertEquals(1, $data['overview']['downloads']);
        $this->assertEquals(1, $data['overview']['favorites']);
        $this->assertEquals(2, $data['overview']['visitors']);

        // Check visitor collection has grouped email with activity count
        $this->assertCount(2, $data['visitors']); // 'visitor@example.com' and null/anonymous
        
        $visitorItem = collect($data['visitors'])->firstWhere('email', 'visitor@example.com');
        $this->assertNotNull($visitorItem);
        $this->assertEquals(1, $visitorItem['downloads']);
        $this->assertEquals(1, $visitorItem['favorites']);
    }

    public function test_missing_email_is_rejected_with_422_even_if_zip_is_ready(): void
    {
        Storage::fake('b2');

        $this->createPhoto();

        $response = $this->postJson("/api/v1/public/galleries/{$this->gallery->slug}/download-zip", []);

        $response->assertStatus(422);
        $response->assertJsonFragment(['code' => 'EMAIL_REQUIRED']);
        $this->assertArrayNotHasKey('download_url', $response->json());
    }

    public function test_invalid_email_format_is_rejected_with_422(): void
    {
        Storage::fake('b2');

        $this->createPhoto();

        $response = $this->postJson("/api/v1/public/galleries/{$this->gallery->slug}/download-zip", [
            'email' => 'not-an-email',
        ]);

        $response->assertStatus(422);
        $response->assertJsonFragment(['code' => 'EMAIL_REQUIRED']);
    }

    public function test_valid_email_is_normalized_and_persisted(): void
    {
        Storage::fake('b2');
        Queue::fake([GenerateGalleryZipJob::class]);

        $this->createPhoto();

        $response = $this->postJson("/api/v1/public/galleries/{$this->gallery->slug}/download-zip", [
            'email' => '  VISITOR@Example.COM  ',
            'notify_when_ready' => true,
        ]);

        $response->assertStatus(200);
        $response->assertJsonFragment(['status' => 'pending']);

        $this->assertDatabaseHas('gallery_downloads', [
            'gallery_id' => $this->gallery->id,
            'email' => 'visitor@example.com',
            'notify_when_ready' => true,
        ]);

        Queue::assertPushed(GenerateGalleryZipJob::class);
    }

    public function test_existing_ready_zip_with_email_returns_download_url_idempotently(): void
    {
        Storage::fake('b2');

        $photo = $this->createPhoto();

        $snapshotHash = md5($photo->id . '-' . $photo->updated_at->timestamp);
        $storagePath = "galleries/{$this->gallery->uuid}/downloads/photos-{$snapshotHash}.zip";

        // Place ready zip in storage
        Storage::disk('b2')->put($storagePath, 'dummy-zip-content');

        $download = GalleryDownload::create([
            'gallery_id' => $this->gallery->id,
            'status' => 'ready',
            'photo_snapshot_hash' => $snapshotHash,
            'storage_path' => $storagePath,
            'size' => 2048,
            'completed_at' => now(),
            'email' => null, // created before visitor entered email
        ]);

        $response = $this->postJson("/api/v1/public/galleries/{$this->gallery->slug}/download-zip", [
            'email' => 'visitor@example.com',
        ]);

        $response->assertStatus(200);
        $response->assertJsonFragment(['status' => 'ready']);
        $this->assertNotEmpty($response->json('download_url'));

        // Idempotent: record count remains 1, email updated
        $this->assertEquals(1, GalleryDownload::where('gallery_id', $this->gallery->id)->count());
        $download->refresh();
        $this->assertEquals('visitor@example.com', $download->email);
        $this->assertNotNull($download->download_token_hash);
    }

    public function test_notify_and_notify_when_ready_precedence_and_canonical_persistence(): void
    {
        Storage::fake('b2');
        Queue::fake([GenerateGalleryZipJob::class]);

        $this->createPhoto();

        // Case A: notify=true fallback when notify_when_ready is absent
        $responseA = $this->postJson("/api/v1/public/galleries/{$this->gallery->slug}/download-zip", [
            'email' => 'case_a@example.com',
            'notify' => true,
        ]);
        $responseA->assertStatus(200);
        $this->assertDatabaseHas('gallery_downloads', [
            'email' => 'case_a@example.com',
            'notify_when_ready' => true,
        ]);

        // Case B: notify_when_ready=false explicitly supplied while notify=true; explicit false wins
        GalleryDownload::where('gallery_id', $this->gallery->id)->delete();

        $responseB = $this->postJson("/api/v1/public/galleries/{$this->gallery->slug}/download-zip", [
            'email' => 'case_b@example.com',
            'notify_when_ready' => false,
            'notify' => true,
        ]);
        $responseB->assertStatus(200);
        $this->assertDatabaseHas('gallery_downloads', [
            'email' => 'case_b@example.com',
            'notify_when_ready' => false,
        ]);
    }

    public function test_download_url_cannot_be_obtained_from_client_storage_alone(): void
    {
        Storage::fake('b2');

        $photo = $this->createPhoto();

        $snapshotHash = md5($photo->id . '-' . $photo->updated_at->timestamp);
        $storagePath = "galleries/{$this->gallery->uuid}/downloads/photos-{$snapshotHash}.zip";
        Storage::disk('b2')->put($storagePath, 'dummy-zip-content');

        GalleryDownload::create([
            'gallery_id' => $this->gallery->id,
            'status' => 'ready',
            'photo_snapshot_hash' => $snapshotHash,
            'storage_path' => $storagePath,
            'size' => 2048,
            'completed_at' => now(),
            'email' => null,
        ]);

        // Client has stored email in localStorage, but sends request with no email parameter
        $response = $this->postJson("/api/v1/public/galleries/{$this->gallery->slug}/download-zip", []);

        // Server must not trust client-side state alone; requires email to be submitted and validated
        $response->assertStatus(422);
        $response->assertJsonFragment(['code' => 'EMAIL_REQUIRED']);
        $this->assertArrayNotHasKey('download_url', $response->json());
    }

    public function test_gallery_zip_ready_mail_uses_notifications_address_and_renders_cleanly(): void
    {
        $download = GalleryDownload::create([
            'gallery_id' => $this->gallery->id,
            'status' => 'ready',
            'photo_snapshot_hash' => 'dummy-hash',
            'storage_path' => "galleries/{$this->gallery->uuid}/downloads/test.zip",
            'size' => 15 * 1024 * 1024,
            'total_photos' => 12,
            'processed_photos' => 12,
            'failed_photos' => 0,
            'completed_at' => now(),
            'email' => 'client@example.com',
        ]);

        $downloadUrl = "https://cdn.ifotoset.com/galleries/{$this->gallery->uuid}/downloads/test.zip?filename=Test-Gallery.zip";
        $mailable = new \App\Mail\GalleryZipReadyMail($download, $downloadUrl);

        $envelope = $mailable->envelope();
        $this->assertEquals('notifications@ifotoset.com', $envelope->from->address);
        $this->assertStringContainsString('via ifotoset', $envelope->from->name);
        $this->assertEquals($this->user->email, $envelope->replyTo[0]->address);

        $rendered = $mailable->render();
        $this->assertStringContainsString('Your Photos Are Ready', $rendered);
        $this->assertStringContainsString('Test Gallery', $rendered);
        $this->assertStringContainsString('12 photos', $rendered);
        $this->assertStringContainsString('15 MB', $rendered);
        $this->assertStringContainsString($downloadUrl, $rendered);
        // Ensure no old raw #121212 dark styling is present
        $this->assertStringNotContainsString('background-color: #121212', $rendered);
    }

    public function test_storage_service_generates_cdn_download_url_when_configured(): void
    {
        Config::set('filesystems.disks.b2.cdn_domain', 'cdn.ifotoset.com');
        Config::set('filesystems.disks.b2.use_cdn_downloads', true);

        $storageService = new \App\Services\StorageService();
        $url = $storageService->generatePresignedDownloadUrl(
            "galleries/{$this->gallery->uuid}/downloads/photos-123.zip",
            'Wedding-Photos.zip',
            now()->addHours(24)
        );

        $this->assertStringStartsWith('https://cdn.ifotoset.com/galleries/', $url);
        $this->assertStringContainsString('filename=Wedding-Photos.zip', $url);
        $this->assertStringNotContainsString('s3.eu-central-003.backblazeb2.com', $url);
    }
}

