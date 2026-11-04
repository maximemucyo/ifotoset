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

class GalleryDefaultsTest extends TestCase
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

        $this->gallery = $this->createGallery('Diane & Kevin', ['slug' => 'diane-kevin']);
        $this->createPhoto();
    }

    protected function createGallery(string $title, array $attributes = []): Gallery
    {
        return Gallery::create(array_merge([
            'uuid' => Uuid::uuid7()->toString(),
            'user_id' => $this->user->id,
            'title' => $title,
            'slug' => str($title)->slug()->toString(),
            'visibility' => 'public',
        ], $attributes));
    }

    protected function createPhoto(?Gallery $gallery = null): Photo
    {
        $gallery ??= $this->gallery;
        $gallery->stats()->increment('photo_count');

        return Photo::create([
            'uuid' => Uuid::uuid7()->toString(),
            'gallery_id' => $gallery->id,
            'disk_id' => $this->disk->id,
            'path' => 'galleries/' . $gallery->uuid,
            'filename' => 'photo_' . uniqid() . '.jpg',
            'original_filename' => 'IMG_' . rand(1000, 9999) . '.JPG',
            'mime_type' => 'image/jpeg',
            'size' => 1024 * 1024,
            'width' => 1920,
            'height' => 1080,
            'checksum' => hash('sha256', uniqid()),
            'status' => 'ready',
            'is_hidden' => false,
            'sort_order' => 0,
        ]);
    }

    private function saveDefaults(array $overrides = [])
    {
        return $this->actingAs($this->user)->post('/studio/settings/gallery-defaults', array_merge([
            'visibility' => 'private',
            'download_pin' => '1',
            'expiry_months' => '3',
            'photo_order' => 'filename',
            'template' => 'none',
            'color_mode' => 'dark',
        ], $overrides));
    }

    private function createFromDialog(): Gallery
    {
        $this->actingAs($this->user)->post('/studio/galleries', ['title' => 'School day'])->assertRedirect();

        return Gallery::where('title', 'School day')->firstOrFail();
    }

    public function test_without_defaults_new_galleries_start_as_before(): void
    {
        $gallery = $this->createFromDialog();

        $this->assertSame('public', $gallery->visibility);
        $this->assertNull($gallery->download_pin);
        $this->assertNull($gallery->expires_at);
        $this->assertSame('taken', $gallery->photo_order);
        $this->assertSame(['template' => 'full', 'grid' => 'masonry', 'mode' => 'light'], $gallery->design());
    }

    public function test_new_galleries_start_with_the_studio_defaults(): void
    {
        $this->saveDefaults()->assertRedirect()->assertSessionHasNoErrors();

        $gallery = $this->createFromDialog();

        $this->assertSame('private', $gallery->visibility);
        $this->assertMatchesRegularExpression('/^\d{4}$/', (string) $gallery->download_pin);
        $this->assertTrue($gallery->allow_gallery_downloads);
        $this->assertSame('filename', $gallery->photo_order);
        // The layout brings its usual grid
        $this->assertSame(['template' => 'none', 'grid' => 'square', 'mode' => 'dark'], $gallery->design());
        // Expiry waits for Share, so it counts from that day
        $this->assertNull($gallery->expires_at);
        $this->assertTrue($gallery->isDraft());

        // Older galleries keep their own settings
        $this->assertSame('public', $this->gallery->fresh()->visibility);
    }

    public function test_share_panel_starts_with_the_usual_expiry(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 30)->setTime(12, 0));
        $this->saveDefaults(['expiry_months' => '3']);
        $gallery = $this->createFromDialog();

        $this->actingAs($this->user)->get("/studio/galleries/{$gallery->uuid}")
            ->assertOk()
            ->assertSee('2027-02-28');
    }

    public function test_unknown_values_are_refused(): void
    {
        $this->saveDefaults(['template' => 'collage', 'expiry_months' => '7'])
            ->assertSessionHasErrors(['template', 'expiry_months']);

        $this->assertNull($this->user->fresh()->gallery_defaults);
    }

    public function test_settings_page_shows_the_saved_defaults(): void
    {
        $this->saveDefaults(['expiry_months' => '']);

        $this->assertNull($this->user->fresh()->gallery_defaults['expiry_months']);
        $this->actingAs($this->user)->get('/studio/settings')
            ->assertOk()
            ->assertSee('New galleries')
            ->assertSee('<option value="none" selected>No cover</option>', false);
    }
}
