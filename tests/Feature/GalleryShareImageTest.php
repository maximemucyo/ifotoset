<?php

namespace Tests\Feature;

use App\Models\Gallery;
use App\Models\Photo;
use App\Models\Plan;
use App\Models\StorageDisk;
use App\Models\User;
use App\Services\PublicUrlService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Ramsey\Uuid\Uuid;
use Tests\TestCase;

class GalleryShareImageTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Gallery $gallery;
    protected Photo $cover;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('b2');

        $disk = StorageDisk::create([
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
            'name' => 'Richard Karegeya',
            'username' => 'richard',
            'email' => 'richard@example.com',
            'password' => Hash::make('password123'),
        ]);

        // Public link, but not listed on the portfolio (the common client-delivery setup)
        $this->gallery = Gallery::create([
            'uuid' => Uuid::uuid7()->toString(),
            'user_id' => $this->user->id,
            'title' => 'Isaac + Celine',
            'slug' => 'isaac-celine',
            'visibility' => 'public',
            'show_on_profile' => false,
        ]);

        $this->cover = Photo::create([
            'uuid' => Uuid::uuid7()->toString(),
            'gallery_id' => $this->gallery->id,
            'disk_id' => $disk->id,
            'path' => "galleries/{$this->gallery->id}/photos/cover",
            'filename' => '1M1A0079.jpg',
            'original_filename' => '1M1A0079.jpg',
            'stored_filename' => '1M1A0079.jpg',
            'mime_type' => 'image/jpeg',
            'size' => 1024,
            'width' => 1067,
            'height' => 1600,
            'checksum' => hash('sha256', 'cover'),
            'status' => 'ready',
        ]);
        $this->gallery->update(['cover_photo_id' => $this->cover->id]);

        $portrait = imagecreatetruecolor(1067, 1600);
        ob_start();
        imagewebp($portrait);
        Storage::disk('b2')->put("galleries/{$this->gallery->id}/photos/cover/1M1A0079_lg.webp", ob_get_clean());
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(storage_path("app/share-images/{$this->gallery->id}"));

        parent::tearDown();
    }

    private function galleryUrl(): string
    {
        return app(PublicUrlService::class)->galleryUrl('richard', 'isaac-celine');
    }

    public function test_unlisted_public_gallery_previews_its_cover(): void
    {
        $html = $this->get($this->galleryUrl())->assertOk()->getContent();

        $this->assertStringContainsString('<meta property="og:image" content="' . $this->galleryUrl() . '/share.jpg?v=' . $this->cover->id . '-', $html);
        $this->assertStringContainsString('<meta property="og:image:width" content="1200">', $html);
        $this->assertStringNotContainsString('og-default.jpg', $html);
        // Still kept out of search
        $this->assertStringContainsString('noindex, nofollow', $html);
    }

    public function test_share_image_is_a_1200_by_630_jpeg(): void
    {
        $response = $this->get($this->galleryUrl() . '/share.jpg')
            ->assertOk()
            ->assertHeader('Content-Type', 'image/jpeg');

        [$width, $height] = getimagesize($response->baseResponse->getFile()->getPathname());
        $this->assertSame([1200, 630], [$width, $height]);
    }

    public function test_pin_protected_gallery_keeps_the_generic_preview(): void
    {
        $this->gallery->update(['visibility' => 'password', 'password_hash' => Hash::make('secret1')]);

        $this->get($this->galleryUrl())->assertOk()->assertSee(asset('og-default.jpg'), false);
        $this->get($this->galleryUrl() . '/share.jpg')->assertNotFound();
    }

    public function test_private_gallery_keeps_the_generic_preview(): void
    {
        $this->gallery->update(['visibility' => 'private']);

        $this->get($this->galleryUrl())->assertOk()->assertSee(asset('og-default.jpg'), false);
        $this->get($this->galleryUrl() . '/share.jpg')->assertNotFound();
    }

    public function test_hidden_cover_falls_back_to_the_first_visible_photo(): void
    {
        $this->cover->update(['is_hidden' => true]);

        $this->get($this->galleryUrl())->assertOk()->assertSee(asset('og-default.jpg'), false);
        $this->get($this->galleryUrl() . '/share.jpg')->assertNotFound();
    }
}
