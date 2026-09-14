<?php

namespace Tests\Feature;

use App\Enums\UploadStatus;
use App\Models\Gallery;
use App\Models\Photo;
use App\Models\Plan;
use App\Models\StorageDisk;
use App\Models\UploadSession;
use App\Models\User;
use App\Services\StorageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Mockery;
use Ramsey\Uuid\Uuid;
use Tests\TestCase;

class StudioUploadTest extends TestCase
{
    use RefreshDatabase;

    protected User $photographer;
    protected User $otherPhotographer;
    protected Gallery $gallery;
    protected StorageDisk $disk;

    protected function setUp(): void
    {
        parent::setUp();

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

        $this->photographer = User::create([
            'uuid' => Uuid::uuid7()->toString(),
            'plan_id' => $plan->id,
            'name' => 'Alex Morgan',
            'username' => 'alexmorgan',
            'email' => 'alex@example.com',
            'password' => Hash::make('password123'),
            'role' => 'user',
        ]);

        $this->otherPhotographer = User::create([
            'uuid' => Uuid::uuid7()->toString(),
            'plan_id' => $plan->id,
            'name' => 'Sara Lee',
            'username' => 'saralee',
            'email' => 'sara@example.com',
            'password' => Hash::make('password123'),
            'role' => 'user',
        ]);

        $this->gallery = Gallery::create([
            'uuid' => Uuid::uuid7()->toString(),
            'user_id' => $this->photographer->id,
            'title' => 'Wedding Collection',
            'slug' => 'wedding-collection',
            'visibility' => 'public',
            'version' => 1,
        ]);

        $this->disk = StorageDisk::firstOrCreate([
            'driver' => 'b2',
        ], [
            'uuid' => Uuid::uuid7()->toString(),
            'bucket' => 'ifotoset-media',
            'region' => 'us-east-005',
            'cdn_domain' => 'cdn.ifotoset.com',
        ]);

        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);
    }

    public function test_invalid_csrf_token_is_rejected(): void
    {
        $middleware = new class($this->app, $this->app['encrypter']) extends \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken {
            protected function runningUnitTests()
            {
                return false;
            }
        };

        $this->app->instance(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class, $middleware);

        $this->actingAs($this->photographer)
            ->withSession(['_token' => 'real-token'])
            ->post('/studio/uploads/request', [
                '_token'     => 'wrong-token',
                'gallery_id' => $this->gallery->uuid,
                'filename'   => 'csrf_test.jpg',
                'file_size'  => 1024,
                'mime_type'  => 'image/jpeg',
            ])
            ->assertStatus(419);
    }

    public function test_guest_cannot_access_studio_upload_endpoints(): void
    {
        $this->post('/studio/uploads/request', [])
            ->assertRedirect('/login');

        $this->post('/studio/uploads/confirm', [])
            ->assertRedirect('/login');

        $this->post('/studio/uploads/abort', [])
            ->assertRedirect('/login');
    }

    public function test_authenticated_photographer_can_request_upload_session(): void
    {
        $storageMock = Mockery::mock(StorageService::class);
        $storageMock->shouldReceive('generatePresignedUploadUrl')
            ->once()
            ->andReturn('https://s3.eu-central-003.backblazeb2.com/presigned-put-url');
        $this->app->instance(StorageService::class, $storageMock);

        $sha256 = hash('sha256', 'test-image-content');

        $response = $this->actingAs($this->photographer)
            ->postJson('/studio/uploads/request', [
                'gallery_id' => $this->gallery->uuid,
                'filename' => 'photo_01.jpg',
                'file_size' => 1024 * 500,
                'mime_type' => 'image/jpeg',
                'sha256' => $sha256,
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'upload_session_id',
                'session_id',
                'object_key',
                'presigned_url',
                'upload_url',
                'headers' => ['x-amz-checksum-sha256'],
                'expires_at',
            ]);

        $this->assertDatabaseHas('upload_sessions', [
            'user_id' => $this->photographer->id,
            'gallery_id' => $this->gallery->id,
            'original_filename' => 'photo_01.jpg',
            'status' => UploadStatus::Requested->value,
        ]);
    }

    public function test_authenticated_photographer_cannot_upload_to_another_photographers_gallery(): void
    {
        $sha256 = hash('sha256', 'test-image-content');

        $this->actingAs($this->otherPhotographer)
            ->postJson('/studio/uploads/request', [
                'gallery_id' => $this->gallery->uuid,
                'filename' => 'intruder.jpg',
                'file_size' => 1024 * 100,
                'mime_type' => 'image/jpeg',
                'sha256' => $sha256,
            ])
            ->assertStatus(404);
    }

    public function test_authenticated_photographer_can_confirm_upload_session(): void
    {
        \Illuminate\Support\Facades\Queue::fake();

        $sha256 = hash('sha256', 'test-image-content');
        $photoUuid = Uuid::uuid7()->toString();
        $fileSize = 1024 * 500;

        $session = UploadSession::create([
            'uuid' => $photoUuid,
            'user_id' => $this->photographer->id,
            'gallery_id' => $this->gallery->id,
            'idempotency_key' => 'idem-test-123',
            'object_key' => "galleries/{$this->gallery->uuid}/photos/{$photoUuid}/photo.jpg",
            'original_filename' => 'photo.jpg',
            'expected_size' => $fileSize,
            'expected_sha256' => $sha256,
            'status' => UploadStatus::Requested->value,
            'expires_at' => now()->addHours(2),
        ]);

        $storageMock = Mockery::mock(StorageService::class);
        $storageMock->shouldReceive('exists')
            ->with($session->object_key)
            ->andReturn(true);
        $storageMock->shouldReceive('size')
            ->with($session->object_key)
            ->andReturn($fileSize);
        $storageMock->shouldReceive('getCdnUrl')
            ->andReturn('https://cdn.ifotoset.com/photo.jpg');
        $this->app->instance(StorageService::class, $storageMock);

        $response = $this->actingAs($this->photographer)
            ->postJson('/studio/uploads/confirm', [
                'upload_session_id' => $session->uuid,
            ]);

        $response->assertStatus(201);

        $photo = Photo::where('checksum', $sha256)->first();
        $this->assertNotNull($photo);
        $this->assertEquals($session->uuid, $photo->uuid);
        $this->assertEquals($this->gallery->id, $photo->gallery_id);

        $this->assertEquals(UploadStatus::Completed->value, $session->fresh()->status);
    }

    public function test_invalid_upload_session_cannot_be_confirmed(): void
    {
        $this->actingAs($this->photographer)
            ->postJson('/studio/uploads/confirm', [
                'upload_session_id' => Uuid::uuid7()->toString(),
            ])
            ->assertStatus(422)
            ->assertJson([
                'code' => 'UPLOAD_CONFIRMATION_FAILED',
            ]);
    }

    public function test_already_confirmed_upload_session_is_idempotent(): void
    {
        $sha256 = hash('sha256', 'test-image-content-idempotent');
        $photoUuid = Uuid::uuid7()->toString();
        $fileSize = 1024 * 500;

        $session = UploadSession::create([
            'uuid' => $photoUuid,
            'user_id' => $this->photographer->id,
            'gallery_id' => $this->gallery->id,
            'idempotency_key' => 'idem-test-idempotent',
            'object_key' => "galleries/{$this->gallery->uuid}/photos/{$photoUuid}/photo.jpg",
            'original_filename' => 'photo.jpg',
            'expected_size' => $fileSize,
            'expected_sha256' => $sha256,
            'status' => UploadStatus::Completed->value,
            'expires_at' => now()->addHours(2),
        ]);

        Photo::create([
            'uuid' => $session->uuid,
            'gallery_id' => $this->gallery->id,
            'disk_id' => $this->disk->id,
            'path' => dirname($session->object_key),
            'filename' => basename($session->object_key),
            'original_filename' => 'photo.jpg',
            'stored_filename' => basename($session->object_key),
            'mime_type' => 'image/jpeg',
            'size' => $fileSize,
            'checksum' => $sha256,
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->photographer)
            ->postJson('/studio/uploads/confirm', [
                'upload_session_id' => $session->uuid,
            ]);

        $response->assertStatus(200);
    }

    public function test_aborting_upload_session_marks_it_expired(): void
    {
        $sha256 = hash('sha256', 'test-image-content-abort');
        $photoUuid = Uuid::uuid7()->toString();

        $session = UploadSession::create([
            'uuid' => $photoUuid,
            'user_id' => $this->photographer->id,
            'gallery_id' => $this->gallery->id,
            'idempotency_key' => 'idem-test-abort',
            'object_key' => "galleries/{$this->gallery->uuid}/photos/{$photoUuid}/photo.jpg",
            'original_filename' => 'photo.jpg',
            'expected_size' => 1024 * 100,
            'expected_sha256' => $sha256,
            'status' => UploadStatus::Requested->value,
            'expires_at' => now()->addHours(2),
        ]);

        $storageMock = Mockery::mock(StorageService::class);
        $storageMock->shouldReceive('delete')
            ->once()
            ->with($session->object_key);
        $this->app->instance(StorageService::class, $storageMock);

        $response = $this->actingAs($this->photographer)
            ->postJson('/studio/uploads/abort', [
                'upload_session_id' => $session->uuid,
                'reason' => 'User aborted upload',
            ]);

        $response->assertStatus(200);

        $this->assertEquals(UploadStatus::Expired->value, $session->fresh()->status);
    }

    public function test_quota_exceeded_returns_useful_error(): void
    {
        // Set user's storage used close to limit
        $this->photographer->update([
            'storage_used_bytes' => 5 * 1024 * 1024 * 1024,
        ]);

        $sha256 = hash('sha256', 'quota-test');

        $response = $this->actingAs($this->photographer)
            ->postJson('/studio/uploads/request', [
                'gallery_id' => $this->gallery->uuid,
                'filename' => 'over_quota.jpg',
                'file_size' => 1024 * 1024 * 10,
                'mime_type' => 'image/jpeg',
                'sha256' => $sha256,
            ]);

        $response->assertStatus(400)
            ->assertJson([
                'code' => 'UPLOAD_REQUEST_FAILED',
            ]);
    }
}
