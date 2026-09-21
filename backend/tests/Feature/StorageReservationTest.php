<?php

namespace Tests\Feature;

use App\Enums\UploadStatus;
use App\Models\Gallery;
use App\Models\Plan;
use App\Models\StorageDisk;
use App\Models\UploadSession;
use App\Models\User;
use App\Services\StorageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Mockery;
use Ramsey\Uuid\Uuid;
use Tests\TestCase;

class StorageReservationTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Gallery $gallery;
    protected Plan $plan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->plan = Plan::firstOrCreate([
            'slug' => 'test-plan',
        ], [
            'uuid' => Uuid::uuid7()->toString(),
            'name' => 'Test Plan',
            'storage_limit' => 100 * 1024 * 1024, // 100 MB
            'video_limit' => 0,
            'gallery_limit' => 5,
            'team_limit' => 0,
        ]);

        $this->user = User::create([
            'uuid' => Uuid::uuid7()->toString(),
            'plan_id' => $this->plan->id,
            'name' => 'Reservation Tester',
            'username' => 'restester',
            'email' => 'res@example.com',
            'password' => Hash::make('password123'),
            'role' => 'photographer',
            'email_verified_at' => now(),
            'storage_used_bytes' => 0,
            'storage_reserved_bytes' => 0,
        ]);

        $this->gallery = Gallery::create([
            'uuid' => Uuid::uuid7()->toString(),
            'user_id' => $this->user->id,
            'title' => 'Reservation Gallery',
            'slug' => 'reservation-gallery',
            'visibility' => 'public',
        ]);

        $mockStorage = Mockery::mock(StorageService::class);
        $mockStorage->shouldReceive('generatePresignedUploadUrl')->andReturn('https://storage.example.com/presigned-put');
        $mockStorage->shouldReceive('exists')->andReturn(true);
        $mockStorage->shouldReceive('size')->andReturn(60 * 1024 * 1024);
        $mockStorage->shouldReceive('delete')->andReturn(true);
        $this->app->instance(StorageService::class, $mockStorage);
    }

    public function test_upload_reservation_increments_reserved_bytes(): void
    {
        $response = $this->actingAs($this->user)->postJson('/studio/uploads/request', [
            'gallery_id' => $this->gallery->uuid,
            'filename' => 'photo1.jpg',
            'file_size' => 60 * 1024 * 1024, // 60 MB
            'mime_type' => 'image/jpeg',
            'sha256' => hash('sha256', 'res-1'),
        ]);

        $response->assertStatus(201);
        $this->assertEquals(60 * 1024 * 1024, $this->user->fresh()->storage_reserved_bytes);
    }

    public function test_concurrent_or_subsequent_request_cannot_exceed_storage_quota(): void
    {
        // First upload requests 60 MB out of 100 MB -> Allowed
        $res1 = $this->actingAs($this->user)->postJson('/studio/uploads/request', [
            'gallery_id' => $this->gallery->uuid,
            'filename' => 'photo1.jpg',
            'file_size' => 60 * 1024 * 1024,
            'mime_type' => 'image/jpeg',
            'sha256' => hash('sha256', 'photo-1'),
        ]);
        $res1->assertStatus(201);

        // Second upload requests 50 MB -> 60 MB reserved + 50 MB requested > 100 MB limit -> Rejected with 409
        $res2 = $this->actingAs($this->user)->postJson('/studio/uploads/request', [
            'gallery_id' => $this->gallery->uuid,
            'filename' => 'photo2.jpg',
            'file_size' => 50 * 1024 * 1024,
            'mime_type' => 'image/jpeg',
            'sha256' => hash('sha256', 'photo-2'),
        ]);

        $res2->assertStatus(409)
            ->assertJson([
                'code' => 'STORAGE_QUOTA_EXCEEDED',
                'is_quota_error' => true,
                'required_bytes' => 50 * 1024 * 1024,
                'available_bytes' => 40 * 1024 * 1024,
            ]);

        // Reserved bytes remains 60 MB
        $this->assertEquals(60 * 1024 * 1024, $this->user->fresh()->storage_reserved_bytes);
    }

    public function test_upload_reservation_is_released_when_upload_aborted(): void
    {
        $res = $this->actingAs($this->user)->postJson('/studio/uploads/request', [
            'gallery_id' => $this->gallery->uuid,
            'filename' => 'abort_me.jpg',
            'file_size' => 40 * 1024 * 1024,
            'mime_type' => 'image/jpeg',
            'sha256' => hash('sha256', 'abort-test'),
        ]);
        $res->assertStatus(201);
        $sessionUuid = $res->json('upload_session_id');

        $this->assertEquals(40 * 1024 * 1024, $this->user->fresh()->storage_reserved_bytes);

        // Abort the upload
        $abortRes = $this->actingAs($this->user)->postJson('/studio/uploads/abort', [
            'upload_session_id' => $sessionUuid,
        ]);
        $abortRes->assertStatus(200);

        // Reserved bytes must be decremented back to 0
        $this->assertEquals(0, $this->user->fresh()->storage_reserved_bytes);
    }

    public function test_reconcile_command_expires_stale_sessions_and_reconciles_reservation(): void
    {
        // Manually create an expired upload session
        UploadSession::create([
            'uuid' => Uuid::uuid7()->toString(),
            'user_id' => $this->user->id,
            'gallery_id' => $this->gallery->id,
            'idempotency_key' => 'stale-key-1',
            'object_key' => 'galleries/test/stale.jpg',
            'original_filename' => 'stale.jpg',
            'expected_size' => 30 * 1024 * 1024,
            'expected_sha256' => hash('sha256', 'stale-1'),
            'status' => UploadStatus::Requested->value,
            'expires_at' => now()->subMinutes(10), // Expired!
        ]);

        // Artificially set user reserved bytes to 30 MB
        $this->user->update(['storage_reserved_bytes' => 30 * 1024 * 1024]);

        // Run reconciliation command
        Artisan::call('uploads:reconcile-reservations', ['--user' => $this->user->id]);

        // The session is marked expired and user reservation reconciled to 0
        $this->assertEquals(0, $this->user->fresh()->storage_reserved_bytes);
    }
}
