<?php

namespace Tests\Feature;

use App\Models\Gallery;
use App\Models\Package;
use App\Models\Photo;
use App\Models\Plan;
use App\Models\StorageDisk;
use App\Models\User;
use App\Services\PublicUrlService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Ramsey\Uuid\Uuid;
use Tests\TestCase;

class SeoArchitectureTest extends TestCase
{
    use RefreshDatabase;

    protected User $photographer;
    protected Gallery $publicGallery;
    protected Gallery $pinGallery;
    protected PublicUrlService $urlService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->urlService = app(PublicUrlService::class);

        $plan = Plan::firstOrCreate([
            'slug' => 'pro',
        ], [
            'uuid' => Uuid::uuid7()->toString(),
            'name' => 'Pro Plan',
            'storage_limit' => 100 * 1024 * 1024 * 1024,
            'video_limit' => 300,
            'gallery_limit' => 100,
            'team_limit' => 5,
        ]);

        $this->photographer = User::create([
            'uuid' => Uuid::uuid7()->toString(),
            'plan_id' => $plan->id,
            'name' => 'Kigali Visuals',
            'username' => 'kigalivisuals',
            'email' => 'kigali@example.com',
            'password' => Hash::make('secret123'),
            'role' => 'photographer',
            'is_active' => true,
            'location' => 'Kigali, Rwanda',
            'bio' => 'Award winning documentary and wedding photographer based in Kigali.',
        ]);

        $this->publicGallery = Gallery::create([
            'uuid' => Uuid::uuid7()->toString(),
            'user_id' => $this->photographer->id,
            'title' => 'Rwanda Mountain Wedding',
            'slug' => 'rwanda-mountain-wedding',
            'visibility' => 'public',
            'show_on_profile' => true,
        ]);

        $this->pinGallery = Gallery::create([
            'uuid' => Uuid::uuid7()->toString(),
            'user_id' => $this->photographer->id,
            'title' => 'Private VIP Session',
            'slug' => 'private-vip-session',
            'visibility' => 'public',
            'password_hash' => Hash::make('1234'),
            'show_on_profile' => false,
        ]);
    }

    public function test_homepage_renders_authoritative_seo_metadata(): void
    {
        $response = $this->get('/');
        $response->assertStatus(200);

        $response->assertSee('<link rel="canonical" href="http://localhost:8000/">', false);
        $response->assertSee('<meta name="robots" content="index, follow, max-image-preview:large">', false);
        $response->assertSee('<meta property="og:site_name" content="ifotoset">', false);
        $response->assertSee('<meta name="twitter:card" content="summary_large_image">', false);
        $response->assertSee('"@type":"Organization"', false);
        $response->assertSee('"@type":"SoftwareApplication"', false);
    }

    public function test_auth_pages_emit_noindex_follow(): void
    {
        $loginResponse = $this->get('/login');
        $loginResponse->assertStatus(200);
        $loginResponse->assertSee('<meta name="robots" content="noindex, follow">', false);

        $registerResponse = $this->get('/register');
        $registerResponse->assertStatus(200);
        $registerResponse->assertSee('<meta name="robots" content="noindex, follow">', false);
    }

    public function test_photographer_profile_renders_canonical_and_profile_page_schema(): void
    {
        $url = $this->urlService->photographer($this->photographer);
        $response = $this->get($url);
        $response->assertStatus(200);

        $response->assertSee('<link rel="canonical" href="' . $url . '">', false);
        $response->assertSee('<meta name="robots" content="index, follow, max-image-preview:large">', false);
        $response->assertSee('<meta property="og:type" content="profile">', false);
        $response->assertSee('"@type":"ProfilePage"', false);
        $response->assertSee('Kigali, Rwanda');
    }

    public function test_public_gallery_renders_canonical_and_index_robots(): void
    {
        $url = $this->urlService->gallery($this->publicGallery);
        $response = $this->get($url);
        $response->assertStatus(200);

        $response->assertSee('<link rel="canonical" href="' . $url . '">', false);
        $response->assertSee('<meta name="robots" content="index, follow, max-image-preview:large">', false);
        $response->assertSee('Rwanda Mountain Wedding');
    }

    public function test_pin_protected_gallery_strictly_emits_noindex_nofollow(): void
    {
        $url = $this->urlService->gallery($this->pinGallery);
        $response = $this->get($url);
        $response->assertStatus(200);

        $response->assertSee('<meta name="robots" content="noindex, nofollow">', false);
        $response->assertSee('PIN Protected Gallery');
    }

    public function test_apex_fallback_routes_301_redirect_to_canonical_subdomains(): void
    {
        $photographerResponse = $this->get('/p/kigalivisuals');
        $photographerResponse->assertStatus(301);
        $photographerResponse->assertRedirect($this->urlService->photographer($this->photographer));

        $galleryResponse = $this->get('/p/kigalivisuals/rwanda-mountain-wedding');
        $galleryResponse->assertStatus(301);
        $galleryResponse->assertRedirect($this->urlService->gallery($this->publicGallery));
    }

    public function test_robots_txt_exists_and_declares_sitemap_index(): void
    {
        $this->assertFileExists(public_path('robots.txt'));
        $content = file_get_contents(public_path('robots.txt'));
        $this->assertStringContainsString('User-agent: *', $content);
        $this->assertStringContainsString('Sitemap: https://ifotoset.com/sitemap.xml', $content);
        $this->assertStringNotContainsString('Disallow: /login', $content); // Crawlable for noindex
    }

    public function test_sitemap_index_and_modular_sections(): void
    {
        // 1. Root index
        $indexResponse = $this->get('/sitemap.xml');
        $indexResponse->assertStatus(200);
        $indexResponse->assertHeader('Content-Type', 'application/xml; charset=utf-8');
        $indexResponse->assertSee('<sitemapindex', false);
        $indexResponse->assertSee('/sitemaps/static.xml', false);
        $indexResponse->assertSee('/sitemaps/photographers.xml', false);
        $indexResponse->assertSee('/sitemaps/galleries.xml', false);

        // 2. Static sitemap
        $staticResponse = $this->get('/sitemaps/static.xml');
        $staticResponse->assertStatus(200);
        $staticResponse->assertSee('<urlset', false);

        // 3. Photographers sitemap
        $photographersResponse = $this->get('/sitemaps/photographers.xml');
        $photographersResponse->assertStatus(200);
        $photographersResponse->assertSee('http://kigalivisuals.localhost:8000', false);

        // 4. Galleries sitemap contains public gallery and excludes PIN gallery
        $galleriesResponse = $this->get('/sitemaps/galleries.xml');
        $galleriesResponse->assertStatus(200);
        $galleriesResponse->assertSee('http://kigalivisuals.localhost:8000/rwanda-mountain-wedding', false);
        $galleriesResponse->assertDontSee('private-vip-session');

        // 5. Unknown section returns 404
        $invalidSectionResponse = $this->get('/sitemaps/malicious-query.xml');
        $invalidSectionResponse->assertStatus(404);
    }
}
