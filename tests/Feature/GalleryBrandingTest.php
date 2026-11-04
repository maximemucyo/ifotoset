<?php

namespace Tests\Feature;

use App\Models\Gallery;
use App\Models\Photo;
use App\Models\Plan;
use App\Models\StorageDisk;
use App\Models\User;
use App\Support\Portfolio\LogoTone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Ramsey\Uuid\Uuid;
use Tests\TestCase;

class GalleryBrandingTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Gallery $gallery;

    protected function setUp(): void
    {
        parent::setUp();

        $disk = StorageDisk::create([
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
            'logo_path' => 'portfolio/alice/logo/mark_logo.webp',
            'portfolio_settings' => ['brand_color' => '#1d6b52', 'font' => 'modern', 'logo_tone' => LogoTone::DARK],
        ]);

        $this->gallery = Gallery::create([
            'uuid' => Uuid::uuid7()->toString(),
            'user_id' => $this->user->id,
            'title' => 'Diane & Kevin',
            'slug' => 'diane-kevin',
            'visibility' => 'public',
        ]);

        $this->gallery->stats()->increment('photo_count');
        Photo::create([
            'uuid' => Uuid::uuid7()->toString(),
            'gallery_id' => $this->gallery->id,
            'disk_id' => $disk->id,
            'path' => 'galleries/' . $this->gallery->uuid,
            'filename' => 'photo_1.jpg',
            'original_filename' => 'IMG_0001.JPG',
            'mime_type' => 'image/jpeg',
            'size' => 1024 * 1024,
            'width' => 1920,
            'height' => 1080,
            'checksum' => hash('sha256', 'photo-1'),
            'status' => 'ready',
            'is_hidden' => false,
            'sort_order' => 0,
        ]);
    }

    private function publicUrl(): string
    {
        return app(\App\Services\PublicUrlService::class)->galleryUrl('alice', 'diane-kevin');
    }

    public function test_galleries_keep_the_standard_look_until_the_brand_is_turned_on(): void
    {
        $this->assertFalse($this->gallery->fresh()->use_branding);
        $this->assertNull($this->gallery->fresh()->brand());

        $this->get($this->publicUrl())->assertOk()
            ->assertDontSee('#1d6b52')
            ->assertDontSee('gallery-logo', false)
            ->assertDontSee('--pf-heading', false);
    }

    public function test_the_brand_puts_the_logo_colour_and_font_on_the_client_gallery(): void
    {
        $this->gallery->update(['use_branding' => true]);

        $this->get($this->publicUrl())->assertOk()
            ->assertSee('--primary: #1d6b52', false)
            ->assertSee("--pf-heading: 'Inter Tight Variable'", false)
            ->assertSee('mark_logo.webp', false)
            ->assertSee('data-tone="dark"', false);
    }

    public function test_a_logo_with_its_own_background_stays_off_a_full_bleed_cover(): void
    {
        $this->user->update(['portfolio_settings' => ['brand_color' => '#1d6b52', 'logo_tone' => null]]);
        $this->gallery->update(['use_branding' => true]);
        $html = $this->get($this->publicUrl())->assertOk()->getContent();

        // Shown in the footer, not in the header over the cover
        $this->assertSame(1, substr_count($html, 'mark_logo.webp'));
        $this->assertStringContainsString('<span class="gallery-header-sub', $html);
    }

    public function test_the_brand_switch_saves_from_the_design_tab(): void
    {
        $this->actingAs($this->user)
            ->patchJson("/studio/galleries/{$this->gallery->uuid}", ['use_branding' => '1'])
            ->assertOk();
        $this->assertTrue($this->gallery->fresh()->use_branding);

        // Other sections leave it alone
        $this->actingAs($this->user)->patchJson("/studio/galleries/{$this->gallery->uuid}", ['template' => 'framed'])->assertOk();
        $this->assertTrue($this->gallery->fresh()->use_branding);

        $this->actingAs($this->user)->patchJson("/studio/galleries/{$this->gallery->uuid}", ['use_branding' => '0'])->assertOk();
        $this->assertFalse($this->gallery->fresh()->use_branding);
    }

    public function test_design_tab_shows_what_the_brand_brings(): void
    {
        $this->actingAs($this->user)->get("/studio/galleries/{$this->gallery->uuid}/design")
            ->assertOk()
            ->assertSee('Use my brand')
            ->assertSee('Title font: Modern')
            ->assertSee('Change them in Portfolio');

        $this->user->update(['logo_path' => null, 'portfolio_settings' => []]);
        $this->actingAs($this->user)->get("/studio/galleries/{$this->gallery->uuid}/design")
            ->assertOk()
            ->assertSee('Your portfolio has no logo or brand colour yet.', false);
    }

    public function test_logo_tone_tells_cut_out_logos_from_ones_with_a_background(): void
    {
        $this->assertSame(LogoTone::DARK, LogoTone::detect($this->logo(fill: null, mark: [20, 20, 20])));
        $this->assertSame(LogoTone::LIGHT, LogoTone::detect($this->logo(fill: null, mark: [245, 245, 245])));
        $this->assertNull(LogoTone::detect($this->logo(fill: [255, 255, 255], mark: [20, 20, 20])));
    }

    /**
     * A 200 × 80 wordmark: a block of $mark colour on a transparent or $fill background.
     */
    private function logo(?array $fill, array $mark): \GdImage
    {
        $image = imagecreatetruecolor(200, 80);
        imagealphablending($image, false);
        imagefill($image, 0, 0, $fill ? imagecolorallocate($image, ...$fill) : imagecolorallocatealpha($image, 0, 0, 0, 127));
        imagefilledrectangle($image, 30, 20, 170, 60, imagecolorallocate($image, ...$mark));

        return $image;
    }
}
