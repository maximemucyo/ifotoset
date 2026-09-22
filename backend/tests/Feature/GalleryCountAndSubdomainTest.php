<?php

namespace Tests\Feature;

use App\Models\Gallery;
use App\Models\GalleryStats;
use App\Models\StorageDisk;
use App\Models\User;
use App\Services\PublicUrlService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class GalleryCountAndSubdomainTest extends TestCase
{
    use RefreshDatabase;

    private User $photographer;
    private StorageDisk $disk;
    private PublicUrlService $urlService;

    protected function setUp(): void
    {
        parent::setUp();

        $plan = \App\Models\Plan::firstOrCreate([
            'slug' => 'free',
        ], [
            'uuid' => \Ramsey\Uuid\Uuid::uuid7()->toString(),
            'name' => 'Free Plan',
            'storage_limit' => 5 * 1024 * 1024 * 1024,
            'video_limit' => 0,
            'gallery_limit' => 5,
            'team_limit' => 0,
        ]);

        $this->disk = StorageDisk::create([
            'uuid' => \Ramsey\Uuid\Uuid::uuid7()->toString(),
            'driver' => 'b2',
            'bucket' => 'ifotoset',
            'region' => 'eu-central-003',
            'cdn_domain' => 'cdn.ifotoset.com',
        ]);

        $this->photographer = User::create([
            'uuid' => \Ramsey\Uuid\Uuid::uuid7()->toString(),
            'plan_id' => $plan->id,
            'name' => 'Maxime Mucyo',
            'username' => 'maximemucyo',
            'email' => 'maxime@example.com',
            'password' => \Illuminate\Support\Facades\Hash::make('password123'),
            'role' => 'user',
        ]);

        $this->urlService = app(PublicUrlService::class);
    }

    public function test_photo_count_authoritative_from_stats_without_count_queries(): void
    {
        // 1. Create gallery with 79 photos in stats
        $gallery79 = Gallery::create([
            'uuid' => \Ramsey\Uuid\Uuid::uuid7()->toString(),
            'user_id' => $this->photographer->id,
            'slug' => 'binance-at-kiyovu',
            'title' => 'Binance at Kiyovu',
            'status' => 'published',
            'visibility' => 'public',
        ]);

        GalleryStats::updateOrCreate(
            ['gallery_id' => $gallery79->id],
            ['photo_count' => 79, 'total_bytes' => 1024 * 1024 * 500]
        );

        // 2. Create gallery with 0 photos
        $gallery0 = Gallery::create([
            'uuid' => \Ramsey\Uuid\Uuid::uuid7()->toString(),
            'user_id' => $this->photographer->id,
            'slug' => 'empty-gallery',
            'title' => 'Empty Gallery',
            'status' => 'published',
            'visibility' => 'public',
        ]);

        GalleryStats::updateOrCreate(
            ['gallery_id' => $gallery0->id],
            ['photo_count' => 0, 'total_bytes' => 0]
        );

        // 3. Create gallery with 24 photos (boundary where pagination bug was)
        $gallery24 = Gallery::create([
            'uuid' => \Ramsey\Uuid\Uuid::uuid7()->toString(),
            'user_id' => $this->photographer->id,
            'slug' => 'batch-gallery',
            'title' => 'Batch Gallery',
            'status' => 'published',
            'visibility' => 'public',
        ]);

        GalleryStats::updateOrCreate(
            ['gallery_id' => $gallery24->id],
            ['photo_count' => 24, 'total_bytes' => 1024 * 1024 * 100]
        );

        // 4. Create gallery with 1 photo
        $gallery1 = Gallery::create([
            'uuid' => \Ramsey\Uuid\Uuid::uuid7()->toString(),
            'user_id' => $this->photographer->id,
            'slug' => 'single-photo-gallery',
            'title' => 'Single Photo Gallery',
            'status' => 'published',
            'visibility' => 'public',
        ]);

        GalleryStats::updateOrCreate(
            ['gallery_id' => $gallery1->id],
            ['photo_count' => 1, 'total_bytes' => 1024 * 1024 * 5]
        );

        // Retrieve with stats eager-loaded
        $eagerGalleries = Gallery::with('stats')->whereIn('id', [
            $gallery79->id,
            $gallery0->id,
            $gallery24->id,
            $gallery1->id,
        ])->get()->keyBy('id');

        $this->assertSame(79, $eagerGalleries[$gallery79->id]->photo_count);
        $this->assertSame(79, $eagerGalleries[$gallery79->id]->photos_count);
        $this->assertSame(0, $eagerGalleries[$gallery0->id]->photo_count);
        $this->assertSame(24, $eagerGalleries[$gallery24->id]->photo_count);
        $this->assertSame(1, $eagerGalleries[$gallery1->id]->photo_count);

        // Verify zero database queries are triggered when reading photo_count on loaded model
        DB::enableQueryLog();
        $initialQueryCount = count(DB::getQueryLog());

        $count = $eagerGalleries[$gallery79->id]->photo_count;
        $countAlias = $eagerGalleries[$gallery79->id]->photos_count;

        $newQueryCount = count(DB::getQueryLog());
        $this->assertSame($initialQueryCount, $newQueryCount, 'Accessing photo_count must not execute any database queries.');
        $this->assertSame(79, $count);
        $this->assertSame(79, $countAlias);
    }

    public function test_studio_and_public_blade_views_display_authoritative_photo_count(): void
    {
        $gallery = Gallery::create([
            'uuid' => \Ramsey\Uuid\Uuid::uuid7()->toString(),
            'user_id' => $this->photographer->id,
            'slug' => 'binance-at-kiyovu',
            'title' => 'Binance at Kiyovu',
            'status' => 'published',
            'visibility' => 'public',
        ]);

        GalleryStats::updateOrCreate(
            ['gallery_id' => $gallery->id],
            ['photo_count' => 79, 'total_bytes' => 1024 * 1024 * 500]
        );

        // Studio Galleries index displays 79 photos
        $studioResponse = $this->actingAs($this->photographer)->get('/studio/galleries');
        $studioResponse->assertStatus(200);
        $studioResponse->assertSee('79 photos');

        // Public Gallery view displays 79 Photos and has data-total-photos="79"
        $publicGalleryUrl = $this->urlService->galleryUrl('maximemucyo', 'binance-at-kiyovu');
        $publicResponse = $this->get($publicGalleryUrl);
        $publicResponse->assertStatus(200);
        $publicResponse->assertSee('79 Photos');
        $publicResponse->assertSee('data-total-photos="79"', false);
        $publicResponse->assertSee('data-gallery-url="' . $publicGalleryUrl . '"', false);
    }

    public function test_subdomain_routing_and_photos_stream(): void
    {
        $gallery = Gallery::create([
            'uuid' => \Ramsey\Uuid\Uuid::uuid7()->toString(),
            'user_id' => $this->photographer->id,
            'slug' => 'binance-at-kiyovu',
            'title' => 'Binance at Kiyovu',
            'status' => 'published',
            'visibility' => 'public',
            'show_on_profile' => true,
        ]);

        GalleryStats::updateOrCreate(
            ['gallery_id' => $gallery->id],
            ['photo_count' => 79, 'total_bytes' => 1024 * 1024 * 500]
        );

        // 1. Subdomain root -> photographer portfolio
        $photographerUrl = $this->urlService->photographerUrl('maximemucyo');
        $portfolioResponse = $this->get($photographerUrl);
        $portfolioResponse->assertStatus(200);
        $portfolioResponse->assertSee('Binance at Kiyovu');

        // 2. Subdomain gallery -> public gallery view
        $galleryUrl = $this->urlService->galleryUrl('maximemucyo', 'binance-at-kiyovu');
        $galleryResponse = $this->get($galleryUrl);
        $galleryResponse->assertStatus(200);
        $galleryResponse->assertSee('Binance at Kiyovu');

        // 3. Subdomain gallery photos endpoint
        $photosResponse = $this->getJson("{$galleryUrl}/photos");
        $photosResponse->assertStatus(200)
            ->assertJsonStructure(['data', 'next_cursor', 'has_more']);
    }

    public function test_subdomain_password_protected_gallery_unlock_and_session(): void
    {
        $gallery = Gallery::create([
            'uuid' => \Ramsey\Uuid\Uuid::uuid7()->toString(),
            'user_id' => $this->photographer->id,
            'slug' => 'private-event',
            'title' => 'Private Event',
            'status' => 'published',
            'visibility' => 'password',
            'password_hash' => bcrypt('secret123'),
        ]);

        GalleryStats::updateOrCreate(
            ['gallery_id' => $gallery->id],
            ['photo_count' => 15, 'total_bytes' => 1024 * 1024 * 50]
        );

        $galleryUrl = $this->urlService->galleryUrl('maximemucyo', 'private-event');

        // Initial view shows PIN prompt
        $response = $this->get($galleryUrl);
        $response->assertStatus(200);
        $response->assertSee('PIN Protected Gallery');

        // Unlock on subdomain
        $unlockResponse = $this->postJson("{$galleryUrl}/unlock", [
            'password' => 'secret123',
        ]);
        $unlockResponse->assertStatus(200)
            ->assertJsonPath('success', true);

        // Follow-up request in same session renders photos
        $unlockedResponse = $this->get($galleryUrl);
        $unlockedResponse->assertStatus(200);
        $unlockedResponse->assertDontSee('Password Protected Gallery');
    }

    public function test_apex_302_redirects_to_canonical_subdomain_preserving_query(): void
    {
        $gallery = Gallery::create([
            'uuid' => \Ramsey\Uuid\Uuid::uuid7()->toString(),
            'user_id' => $this->photographer->id,
            'slug' => 'binance-at-kiyovu',
            'title' => 'Binance at Kiyovu',
            'status' => 'published',
            'visibility' => 'public',
        ]);

        // Apex gallery redirect
        $uuid = (string) Str::uuid();
        $apexUrl = "/p/maximemucyo/binance-at-kiyovu?photo={$uuid}";
        $expectedCanonical = $this->urlService->galleryUrl('maximemucyo', 'binance-at-kiyovu') . "?photo={$uuid}";

        $response = $this->get($apexUrl);
        $response->assertStatus(301);
        $response->assertRedirect($expectedCanonical);

        // Apex photographer redirect
        $apexPhotographerUrl = "/p/maximemucyo";
        $expectedPhotographerCanonical = $this->urlService->photographerUrl('maximemucyo');

        $photographerResponse = $this->get($apexPhotographerUrl);
        $photographerResponse->assertStatus(301);
        $photographerResponse->assertRedirect($expectedPhotographerCanonical);
    }

    public function test_reserved_usernames_are_rejected_from_subdomain_route(): void
    {
        $protocol = config('app.public_protocol', 'http');
        $host = config('app.public_root_host', 'localhost');
        $port = config('app.public_root_port');
        $portSuffix = ($port && !in_array((int) $port, [80, 443], true)) ? ":{$port}" : '';

        // api.localhost:8000/some-slug should not match subdomain photographer route
        $response = $this->get("{$protocol}://api.{$host}{$portSuffix}/some-gallery-slug");
        $response->assertStatus(404);

        // www.localhost:8000/ redirects canonically to apex domain
        $wwwResponse = $this->get("{$protocol}://www.{$host}{$portSuffix}");
        $wwwResponse->assertStatus(301);
        $wwwResponse->assertRedirect("{$protocol}://{$host}{$portSuffix}/");
    }
}
