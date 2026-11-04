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

class GalleryTemplatesTest extends TestCase
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

    private function publicUrl(): string
    {
        return app(\App\Services\PublicUrlService::class)->galleryUrl('alice', 'diane-kevin');
    }

    public function test_existing_galleries_keep_the_full_bleed_look(): void
    {
        $this->assertSame(['template' => 'full', 'grid' => 'masonry', 'mode' => 'light'], $this->gallery->fresh()->design());

        $this->get($this->publicUrl())->assertOk()
            ->assertSee('data-layout="masonry"', false)
            ->assertDontSee('data-variant="framed"', false)
            ->assertDontSee('data-variant="split"', false)
            ->assertDontSee('data-variant="none"', false);
    }

    public function test_design_saves_from_the_design_tab(): void
    {
        $this->actingAs($this->user)
            ->patchJson("/studio/galleries/{$this->gallery->uuid}", ['template' => 'framed', 'grid_style' => 'rows', 'color_mode' => 'dark'])
            ->assertOk();

        $this->assertSame(['template' => 'framed', 'grid' => 'rows', 'mode' => 'dark'], $this->gallery->fresh()->design());

        // Other sections leave the design alone
        $this->actingAs($this->user)->patchJson("/studio/galleries/{$this->gallery->uuid}", ['title' => 'Renamed'])->assertOk();
        $this->assertSame('framed', $this->gallery->fresh()->template);
    }

    public function test_unknown_design_values_are_refused(): void
    {
        $this->actingAs($this->user)
            ->patchJson("/studio/galleries/{$this->gallery->uuid}", ['template' => 'collage', 'grid_style' => 'hex', 'color_mode' => 'sepia'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['template', 'grid_style', 'color_mode']);

        $this->assertSame('full', $this->gallery->fresh()->template);
    }

    public function test_client_gallery_follows_the_chosen_layout_and_grid(): void
    {
        $this->gallery->update(['template' => 'split', 'grid_style' => 'square']);
        $this->get($this->publicUrl())->assertOk()
            ->assertSee('data-variant="split"', false)
            ->assertSee('data-layout="square"', false)
            ->assertSee('Photos by Alice Photographer');

        $this->gallery->update(['template' => 'none']);
        $this->get($this->publicUrl())->assertOk()
            ->assertSee('data-variant="none"', false)
            ->assertDontSee('id="gallery-hero" data-variant="image"', false);
    }

    public function test_colours_set_the_starting_theme_and_the_preview_shows_them(): void
    {
        $this->gallery->update(['color_mode' => 'dark']);

        $this->get($this->publicUrl())->assertOk()->assertSee('var mode = forced || stored || "dark"', false);

        $this->actingAs($this->user)->get("/studio/galleries/{$this->gallery->uuid}/preview")
            ->assertOk()
            ->assertSee('data-force-theme="dark"', false);
    }

    public function test_design_tab_shows_the_layout_choices(): void
    {
        $this->actingAs($this->user)->get("/studio/galleries/{$this->gallery->uuid}/design")
            ->assertOk()
            ->assertSee('Full bleed')
            ->assertSee('Framed')
            ->assertSee('Split')
            ->assertSee('No cover')
            ->assertSee('Same as the phone');
    }
}
