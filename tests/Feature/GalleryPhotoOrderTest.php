<?php

namespace Tests\Feature;

use App\Models\Gallery;
use App\Models\Photo;
use App\Models\Plan;
use App\Models\StorageDisk;
use App\Models\User;
use App\Queries\GalleryPhotoQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Ramsey\Uuid\Uuid;
use Tests\TestCase;

class GalleryPhotoOrderTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
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

        $plan = Plan::firstOrCreate(['slug' => 'free'], [
            'uuid' => Uuid::uuid7()->toString(),
            'name' => 'Free Plan',
            'storage_limit' => 5 * 1024 * 1024 * 1024,
            'video_limit' => 0,
            'gallery_limit' => 5,
            'team_limit' => 0,
        ]);

        $this->user = User::forceCreate([
            'email_verified_at' => now(),
            'uuid' => Uuid::uuid7()->toString(),
            'plan_id' => $plan->id,
            'name' => 'Richard',
            'username' => 'richard',
            'email' => 'richard@example.com',
            'password' => Hash::make('password123'),
        ]);

        $this->gallery = Gallery::create([
            'uuid' => Uuid::uuid7()->toString(),
            'user_id' => $this->user->id,
            'title' => 'Isaac & Celine',
            'slug' => 'isaac-celine',
            'visibility' => 'public',
        ]);

        // Uploaded out of order, as parallel uploads finish; 0105 and 0103 share a second (burst)
        $this->photo('1M1A0110.jpg', '2026-08-09 16:30:00');
        $this->photo('1M1A0105.jpg', '2026-08-09 16:27:22');
        $this->photo('1M1A0103.jpg', '2026-08-09 16:27:22');
        $this->photo('1M1A0100.jpg', '2026-08-09 16:20:05');
    }

    private function photo(string $name, ?string $takenAt, array $attributes = []): Photo
    {
        return Photo::create(array_merge([
            'uuid' => Uuid::uuid7()->toString(),
            'gallery_id' => $this->gallery->id,
            'disk_id' => $this->disk->id,
            'path' => 'galleries/test',
            'filename' => $name,
            'original_filename' => $name,
            'stored_filename' => $name,
            'mime_type' => 'image/jpeg',
            'size' => 1024,
            'width' => 1920,
            'height' => 1080,
            'checksum' => hash('sha256', $name),
            'taken_at' => $takenAt,
            'status' => 'ready',
        ], $attributes));
    }

    private function publicNames(): array
    {
        return collect(app(GalleryPhotoQuery::class)->public($this->gallery->refresh())->items())
            ->pluck('original_filename')->all();
    }

    public function test_new_galleries_sort_by_date_taken(): void
    {
        $this->assertSame('taken', $this->gallery->refresh()->photo_order);
        $this->assertSame(['1M1A0100.jpg', '1M1A0103.jpg', '1M1A0105.jpg', '1M1A0110.jpg'], $this->publicNames());
    }

    public function test_filename_order_ignores_capture_time(): void
    {
        $this->photo('1M1A0090.jpg', '2026-08-09 18:00:00');
        $this->gallery->update(['photo_order' => 'filename']);

        $this->assertSame(['1M1A0090.jpg', '1M1A0100.jpg', '1M1A0103.jpg', '1M1A0105.jpg', '1M1A0110.jpg'], $this->publicNames());
    }

    public function test_upload_order(): void
    {
        $this->gallery->update(['photo_order' => 'uploaded']);

        $this->assertSame(['1M1A0110.jpg', '1M1A0105.jpg', '1M1A0103.jpg', '1M1A0100.jpg'], $this->publicNames());
    }

    public function test_studio_matches_client_order_and_includes_hidden_photos(): void
    {
        $this->photo('1M1A0101.jpg', '2026-08-09 16:21:00', ['is_hidden' => true]);

        $studio = collect(app(GalleryPhotoQuery::class)->studio($this->gallery->refresh())->items())
            ->pluck('original_filename')->all();

        $this->assertSame(['1M1A0100.jpg', '1M1A0101.jpg', '1M1A0103.jpg', '1M1A0105.jpg', '1M1A0110.jpg'], $studio);
        $this->assertNotContains('1M1A0101.jpg', $this->publicNames());
    }

    public function test_cursor_pagination_keeps_order_across_pages(): void
    {
        $query = app(GalleryPhotoQuery::class);
        $first = $query->public($this->gallery->refresh(), 2);

        request()->merge(['cursor' => $first->nextCursor()->encode()]);
        $second = $query->public($this->gallery, 2);

        $this->assertSame(
            ['1M1A0100.jpg', '1M1A0103.jpg', '1M1A0105.jpg', '1M1A0110.jpg'],
            collect($first->items())->merge($second->items())->pluck('original_filename')->all()
        );
    }

    public function test_photographer_can_change_photo_order_in_settings(): void
    {
        $this->actingAs($this->user)
            ->get(route('studio.galleries.edit', $this->gallery->uuid))
            ->assertOk()
            ->assertSee('Sort photos by')
            ->assertSee('<option value="taken" selected', false);

        $this->actingAs($this->user)
            ->patch(route('studio.galleries.update', $this->gallery->uuid), [
                'title' => 'Isaac & Celine',
                'visibility' => 'public',
                'photo_order' => 'filename',
            ])
            ->assertRedirect(route('studio.galleries.show', $this->gallery->uuid));

        $this->assertSame('filename', $this->gallery->refresh()->photo_order);
    }

    public function test_unknown_photo_order_is_rejected(): void
    {
        $this->actingAs($this->user)
            ->patch(route('studio.galleries.update', $this->gallery->uuid), [
                'title' => 'Isaac & Celine',
                'visibility' => 'public',
                'photo_order' => 'random',
            ])
            ->assertSessionHasErrors('photo_order');

        $this->assertSame('taken', $this->gallery->refresh()->photo_order);
    }
}
