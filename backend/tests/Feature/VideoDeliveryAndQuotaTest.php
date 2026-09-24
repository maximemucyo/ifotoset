<?php

namespace Tests\Feature;

use App\Enums\UploadStatus;
use App\Jobs\ProcessVideoJob;
use App\Models\Gallery;
use App\Models\Photo;
use App\Models\Plan;
use App\Models\UploadSession;
use App\Models\User;
use App\Services\StorageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Ramsey\Uuid\Uuid;
use Tests\TestCase;

class VideoDeliveryAndQuotaTest extends TestCase
{
    use RefreshDatabase;

    protected User $freeUser;
    protected User $basicUser;
    protected Plan $freePlan;
    protected Plan $basicPlan;
    protected Gallery $basicGallery;
    protected Gallery $freeGallery;

    protected \App\Models\StorageDisk $disk;

    protected function setUp(): void
    {
        parent::setUp();

        $this->disk = \App\Models\StorageDisk::firstOrCreate([
            'driver' => 'b2',
        ], [
            'uuid' => Uuid::uuid7()->toString(),
            'bucket' => 'ifotoset-media',
            'region' => 'us-east-005',
            'cdn_domain' => 'cdn.ifotoset.com',
        ]);

        $this->freePlan = Plan::firstOrCreate([
            'slug' => 'free',
        ], [
            'uuid' => Uuid::uuid7()->toString(),
            'name' => 'Free Plan',
            'storage_limit' => 1024 * 1024 * 1024, // 1 GB
            'video_limit' => 0,
            'video_limit_seconds' => 0,
            'gallery_limit' => 2,
            'team_limit' => 0,
        ]);

        $this->basicPlan = Plan::firstOrCreate([
            'slug' => 'basic',
        ], [
            'uuid' => Uuid::uuid7()->toString(),
            'name' => 'Basic Plan',
            'storage_limit' => 10 * 1024 * 1024 * 1024, // 10 GB
            'video_limit' => 30, // 30 mins
            'video_limit_seconds' => 1800, // 30 mins = 1800s
            'max_video_size_bytes' => 2 * 1024 * 1024 * 1024, // 2 GB
            'max_single_video_duration_seconds' => 900, // 15 mins
            'gallery_limit' => 10,
            'team_limit' => 0,
        ]);

        $this->freeUser = User::create([
            'uuid' => Uuid::uuid7()->toString(),
            'plan_id' => $this->freePlan->id,
            'name' => 'Free User',
            'username' => 'freeuser',
            'email' => 'free@example.com',
            'password' => Hash::make('password123'),
            'role' => 'photographer',
            'email_verified_at' => now(),
            'storage_used_bytes' => 0,
            'storage_reserved_bytes' => 0,
            'video_seconds_used' => 0,
            'video_seconds_reserved' => 0,
        ]);

        $this->freeGallery = Gallery::create([
            'uuid' => Uuid::uuid7()->toString(),
            'user_id' => $this->freeUser->id,
            'title' => 'Free Gallery',
            'slug' => 'free-gallery',
            'visibility' => 'public',
        ]);

        $this->basicUser = User::create([
            'uuid' => Uuid::uuid7()->toString(),
            'plan_id' => $this->basicPlan->id,
            'name' => 'Basic User',
            'username' => 'basicuser',
            'email' => 'basic@example.com',
            'password' => Hash::make('password123'),
            'role' => 'photographer',
            'email_verified_at' => now(),
            'storage_used_bytes' => 0,
            'storage_reserved_bytes' => 0,
            'video_seconds_used' => 0,
            'video_seconds_reserved' => 0,
        ]);

        $this->basicGallery = Gallery::create([
            'uuid' => Uuid::uuid7()->toString(),
            'user_id' => $this->basicUser->id,
            'title' => 'Basic Gallery',
            'slug' => 'basic-gallery',
            'visibility' => 'public',
        ]);

        $mockStorage = Mockery::mock(StorageService::class);
        $mockStorage->shouldReceive('generatePresignedUploadUrl')->andReturn('https://storage.example.com/presigned-put');
        $mockStorage->shouldReceive('generatePresignedDownloadUrl')->andReturn('https://storage.example.com/presigned-download');
        $mockStorage->shouldReceive('exists')->andReturn(true);
        $mockStorage->shouldReceive('size')->andReturn(50 * 1024 * 1024);
        $mockStorage->shouldReceive('delete')->andReturn(true);
        $mockStorage->shouldReceive('getCdnUrl')->andReturnUsing(function ($path) {
            return 'https://cdn.example.com/' . ltrim($path, '/');
        });
        $this->app->instance(StorageService::class, $mockStorage);
    }

    public function test_free_plan_rejects_video_upload(): void
    {
        $response = $this->actingAs($this->freeUser)->postJson('/studio/uploads/request', [
            'gallery_id' => $this->freeGallery->uuid,
            'filename' => 'sample_clip.mp4',
            'file_size' => 50 * 1024 * 1024,
            'mime_type' => 'video/mp4',
            'sha256' => hash('sha256', 'clip-1'),
            'duration_seconds' => 60,
        ]);

        $response->assertStatus(409)
            ->assertJson([
                'code' => 'VIDEO_NOT_SUPPORTED_ON_PLAN',
                'is_video_error' => true,
            ]);
    }

    public function test_basic_plan_allows_video_upload_and_reserves_seconds(): void
    {
        $response = $this->actingAs($this->basicUser)->postJson('/studio/uploads/request', [
            'gallery_id' => $this->basicGallery->uuid,
            'filename' => 'wedding_highlights.mp4',
            'file_size' => 100 * 1024 * 1024,
            'mime_type' => 'video/mp4',
            'sha256' => hash('sha256', 'clip-2'),
            'duration_seconds' => 120, // 2 minutes
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'upload_url',
                'object_key',
                'upload_session_id',
            ]);

        $user = $this->basicUser->fresh();
        $this->assertEquals(120, $user->video_seconds_reserved);
        $this->assertEquals(100 * 1024 * 1024, $user->storage_reserved_bytes);

        $session = UploadSession::where('uuid', $response->json('upload_session_id'))->first();
        $this->assertNotNull($session);
        $this->assertEquals(120, $session->reserved_duration_seconds);
    }

    public function test_video_upload_rejects_when_quota_exceeded(): void
    {
        // Basic plan has 1800s. Set used to 1750s.
        $this->basicUser->update(['video_seconds_used' => 1750]);

        $response = $this->actingAs($this->basicUser)->postJson('/studio/uploads/request', [
            'gallery_id' => $this->basicGallery->uuid,
            'filename' => 'long_video.mp4',
            'file_size' => 100 * 1024 * 1024,
            'mime_type' => 'video/mp4',
            'sha256' => hash('sha256', 'clip-3'),
            'duration_seconds' => 60, // Needs 60s, but only 50s available
        ]);

        $response->assertStatus(409)
            ->assertJson([
                'code' => 'VIDEO_QUOTA_EXCEEDED',
                'is_video_error' => true,
                'required_seconds' => 60,
                'available_seconds' => 50,
            ]);
    }

    public function test_abort_releases_video_reservation(): void
    {
        $response = $this->actingAs($this->basicUser)->postJson('/studio/uploads/request', [
            'gallery_id' => $this->basicGallery->uuid,
            'filename' => 'clip_to_abort.mp4',
            'file_size' => 50 * 1024 * 1024,
            'mime_type' => 'video/mp4',
            'sha256' => hash('sha256', 'clip-abort'),
            'duration_seconds' => 90,
        ]);

        $response->assertStatus(201);
        $sessionUuid = $response->json('upload_session_id');

        $this->assertEquals(90, $this->basicUser->fresh()->video_seconds_reserved);

        $abortResponse = $this->actingAs($this->basicUser)->postJson('/studio/uploads/abort', [
            'upload_session_id' => $sessionUuid,
        ]);

        $abortResponse->assertStatus(200);

        $user = $this->basicUser->fresh();
        $this->assertEquals(0, $user->video_seconds_reserved);
        $this->assertEquals(0, $user->storage_reserved_bytes);
    }

    public function test_reconcile_command_releases_stale_video_reservations(): void
    {
        UploadSession::create([
            'uuid' => Uuid::uuid7()->toString(),
            'user_id' => $this->basicUser->id,
            'gallery_id' => $this->basicGallery->id,
            'idempotency_key' => 'stale-video-session',
            'object_key' => 'galleries/test/stale.mp4',
            'original_filename' => 'stale.mp4',
            'expected_size' => 50 * 1024 * 1024,
            'expected_sha256' => hash('sha256', 'stale-v'),
            'reserved_duration_seconds' => 300,
            'status' => UploadStatus::Requested->value,
            'expires_at' => now()->subMinutes(15),
        ]);

        $this->basicUser->update(['video_seconds_reserved' => 300]);

        Artisan::call('uploads:reconcile-reservations', ['--user' => $this->basicUser->id]);

        $this->assertEquals(0, $this->basicUser->fresh()->video_seconds_reserved);
    }

    public function test_processing_status_endpoint_returns_video_status(): void
    {
        $photo = Photo::create([
            'uuid' => Uuid::uuid7()->toString(),
            'gallery_id' => $this->basicGallery->id,
            'disk_id' => $this->disk->id,
            'path' => 'galleries/test/video.mp4',
            'filename' => 'teaser.mp4',
            'original_filename' => 'teaser.mp4',
            'stored_filename' => 'teaser.mp4',
            'original_path' => 'galleries/test/video.mp4',
            'media_type' => 'video',
            'duration_seconds' => 45,
            'size' => 20 * 1024 * 1024,
            'width' => 1920,
            'height' => 1080,
            'mime_type' => 'video/mp4',
            'checksum' => hash('sha256', 'teaser'),
            'status' => 'processing',
            'processed_at' => null, // Still processing
        ]);

        $response = $this->actingAs($this->basicUser)->getJson(
            "/studio/galleries/{$this->basicGallery->uuid}/photos/{$photo->uuid}/status"
        );

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'processing',
                'media_type' => 'video',
                'duration_seconds' => 45,
            ]);

        // Now mark as processed
        $photo->update([
            'status' => 'ready',
            'processed_at' => now(),
            'delivery_path' => 'galleries/test/video_delivery.mp4',
            'poster_path' => 'galleries/test/video_poster.jpg',
        ]);

        $readyResponse = $this->actingAs($this->basicUser)->getJson(
            "/studio/galleries/{$this->basicGallery->uuid}/photos/{$photo->uuid}/status"
        );

        $readyResponse->assertStatus(200)
            ->assertJson([
                'status' => 'ready',
                'media_type' => 'video',
                'duration_seconds' => 45,
            ]);
    }

    public function test_public_gallery_serializes_video_attributes(): void
    {
        $photo = Photo::create([
            'uuid' => Uuid::uuid7()->toString(),
            'gallery_id' => $this->basicGallery->id,
            'disk_id' => $this->disk->id,
            'path' => 'galleries/test/video.mp4',
            'filename' => 'clip.mp4',
            'original_filename' => 'clip.mp4',
            'stored_filename' => 'clip.mp4',
            'original_path' => 'galleries/test/video.mp4',
            'delivery_path' => 'galleries/test/video_delivery.mp4',
            'poster_path' => 'galleries/test/video_poster.jpg',
            'media_type' => 'video',
            'duration_seconds' => 125, // 02:05
            'size' => 25 * 1024 * 1024,
            'width' => 1920,
            'height' => 1080,
            'mime_type' => 'video/mp4',
            'checksum' => hash('sha256', 'clip-pub'),
            'status' => \App\Enums\PhotoStatus::Ready->value,
            'processed_at' => now(),
        ]);

        $response = $this->getJson("/p/{$this->basicUser->username}/{$this->basicGallery->slug}/photos");

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertNotEmpty($data);

        $videoItem = collect($data)->firstWhere('uuid', $photo->uuid);
        $this->assertNotNull($videoItem);
        $this->assertTrue($videoItem['is_video']);
        $this->assertEquals('02:05', $videoItem['duration']);
        $this->assertNotNull($videoItem['delivery_url']);
    }

    public function test_trashing_and_restoring_video_adjusts_quota_atomically(): void
    {
        $photo = Photo::create([
            'uuid' => Uuid::uuid7()->toString(),
            'gallery_id' => $this->basicGallery->id,
            'disk_id' => $this->disk->id,
            'path' => 'galleries/test/video.mp4',
            'filename' => 'clip.mp4',
            'original_filename' => 'clip.mp4',
            'stored_filename' => 'clip.mp4',
            'original_path' => 'galleries/test/video.mp4',
            'media_type' => 'video',
            'duration_seconds' => 100,
            'size' => 20 * 1024 * 1024,
            'width' => 1920,
            'height' => 1080,
            'mime_type' => 'video/mp4',
            'checksum' => hash('sha256', 'trash-test'),
            'status' => \App\Enums\PhotoStatus::Ready->value,
            'processed_at' => now(),
        ]);

        // Manually record used quota for this video
        $this->basicUser->update([
            'storage_used_bytes' => 20 * 1024 * 1024,
            'video_seconds_used' => 100,
        ]);

        // Soft delete photo
        $photo->delete();

        $userAfterDelete = $this->basicUser->fresh();
        $this->assertEquals(0, $userAfterDelete->video_seconds_used);
        $this->assertEquals(0, $userAfterDelete->storage_used_bytes);

        // Restore photo
        $photo->restore();

        $userAfterRestore = $this->basicUser->fresh();
        $this->assertEquals(100, $userAfterRestore->video_seconds_used);
        $this->assertEquals(20 * 1024 * 1024, $userAfterRestore->storage_used_bytes);
    }

    public function test_process_video_job_processes_video_and_commits_quota(): void
    {
        \Illuminate\Support\Facades\Storage::fake('b2');

        $tempMp4 = '/tmp/test_process_sample.mp4';
        if (!file_exists($tempMp4)) {
            shell_exec("ffmpeg -f lavfi -i testsrc=duration=2:size=320x240:rate=30 -f lavfi -i anullsrc=channel_layout=stereo:sample_rate=44100 -c:v libx264 -c:a aac -shortest -y {$tempMp4} 2>&1");
        }

        $relPath = "galleries/{$this->basicGallery->uuid}/videos/job_sample.mp4";
        \Illuminate\Support\Facades\Storage::disk('b2')->put($relPath, file_get_contents($tempMp4));

        $this->basicUser->update([
            'video_seconds_reserved' => 5,
            'video_seconds_used' => 0,
        ]);

        $photo = Photo::create([
            'uuid' => Uuid::uuid7()->toString(),
            'gallery_id' => $this->basicGallery->id,
            'disk_id' => $this->disk->id,
            'path' => dirname($relPath),
            'filename' => basename($relPath),
            'original_filename' => 'job_sample.mp4',
            'stored_filename' => basename($relPath),
            'original_path' => $relPath,
            'media_type' => 'video',
            'duration_seconds' => 5,
            'size' => filesize($tempMp4),
            'width' => 0,
            'height' => 0,
            'mime_type' => 'video/mp4',
            'checksum' => hash_file('sha256', $tempMp4),
            'status' => \App\Enums\PhotoStatus::Processing->value,
            'processed_at' => null,
        ]);

        $job = new ProcessVideoJob($photo, 5);
        $job->handle();

        $freshPhoto = $photo->fresh();
        $this->assertEquals(\App\Enums\PhotoStatus::Ready->value, $freshPhoto->status);
        $this->assertNotNull($freshPhoto->delivery_path);
        $this->assertNotNull($freshPhoto->poster_path);
        $this->assertEquals(2, $freshPhoto->duration_seconds);
        $this->assertEquals(320, $freshPhoto->width);
        $this->assertEquals(240, $freshPhoto->height);
        $this->assertNotNull($freshPhoto->processed_at);

        $freshUser = $this->basicUser->fresh();
        $this->assertEquals(2, $freshUser->video_seconds_used);
        $this->assertEquals(0, $freshUser->video_seconds_reserved);
    }
}
