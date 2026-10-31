<?php

namespace Tests\Feature;

use App\Jobs\PurgeEdgeCacheJob;
use App\Models\Gallery;
use App\Models\Plan;
use App\Models\User;
use App\Services\PublicUrlService;
use App\Support\EdgeCache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Ramsey\Uuid\Uuid;
use Tests\TestCase;

class EdgeCacheTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Gallery $gallery;

    protected function setUp(): void
    {
        parent::setUp();

        config(['edge_cache.ttl' => 300]);
        // Privacy changes move gallery media between storage prefixes
        Storage::fake('b2');

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

        $this->gallery = Gallery::create([
            'uuid' => Uuid::uuid7()->toString(),
            'user_id' => $this->user->id,
            'title' => 'Isaac + Celine',
            'slug' => 'isaac-celine',
            'visibility' => 'public',
        ]);
    }

    private function galleryUrl(): string
    {
        return app(PublicUrlService::class)->galleryUrl('richard', 'isaac-celine');
    }

    private function assertEdgeCached($response): void
    {
        $response->assertOk();
        $this->assertSame('max-age=0, public, s-maxage=300', $response->headers->get('Cache-Control'));
        $this->assertSame(EdgeCache::PAGE_TAG, $response->headers->get('Cache-Tag'));
        $this->assertSame([], $response->headers->getCookies(), 'A shared page must not set cookies');
    }

    private function assertNotEdgeCached($response): void
    {
        $this->assertStringNotContainsString('s-maxage', (string) $response->headers->get('Cache-Control'));
        $this->assertNull($response->headers->get('Cache-Tag'));
    }

    public function test_home_page_is_cacheable_for_anonymous_visitors(): void
    {
        $this->assertEdgeCached($this->get('/'));
        $this->assertEdgeCached($this->get('/pricing'));
    }

    public function test_visitors_with_a_session_get_a_private_page(): void
    {
        $response = $this->withUnencryptedCookie(config('session.cookie'), 'existing-session')->get('/');

        $this->assertNotEdgeCached($response);
        $this->assertNotEmpty($response->headers->getCookies());
    }

    public function test_signed_in_users_get_a_private_page(): void
    {
        $this->assertNotEdgeCached($this->actingAs($this->user)->get('/'));
    }

    public function test_studio_pages_are_never_cached(): void
    {
        $this->assertNotEdgeCached($this->get('/login'));
    }

    public function test_public_gallery_is_cached_and_counts_views_from_the_browser(): void
    {
        $response = $this->get($this->galleryUrl());

        $this->assertEdgeCached($response);
        $response->assertSee('/viewed', false);
        $this->assertSame(0, DB::table('activity_logs')->where('event', 'gallery_viewed')->count());
    }

    public function test_gallery_counts_views_on_the_server_when_not_cached(): void
    {
        $response = $this->withUnencryptedCookie(config('session.cookie'), 'existing-session')->get($this->galleryUrl());

        $this->assertNotEdgeCached($response);
        $response->assertDontSee('/viewed', false);
        $this->assertSame(1, DB::table('activity_logs')->where('event', 'gallery_viewed')->count());
    }

    public function test_pin_and_private_galleries_are_never_cached(): void
    {
        $this->gallery->update(['visibility' => 'password', 'password_hash' => Hash::make('secret1')]);
        $this->assertNotEdgeCached($this->get($this->galleryUrl()));

        $this->gallery->update(['visibility' => 'private', 'password_hash' => null]);
        $this->assertNotEdgeCached($this->get($this->galleryUrl()));
    }

    public function test_invitation_links_are_never_cached(): void
    {
        $this->assertNotEdgeCached($this->get($this->galleryUrl() . '?invite=abc'));
    }

    public function test_photographer_portfolio_is_cached(): void
    {
        $this->user->forceFill(['profile_public' => true])->save();

        $this->assertEdgeCached($this->get(app(PublicUrlService::class)->photographerUrl('richard')));
    }

    public function test_view_beacon_records_a_view_without_csrf_or_cookies(): void
    {
        $response = $this->postJson($this->galleryUrl() . '/viewed', [
            'visitor_id' => '0f8fad5b-d9cb-469f-a165-70867728950e',
            'source' => 'whatsapp',
            'referrer' => 'https://wa.me/',
        ]);

        $response->assertNoContent();
        $this->assertSame([], $response->headers->getCookies());
        $this->assertDatabaseHas('activity_logs', [
            'gallery_id' => $this->gallery->id,
            'event' => 'gallery_viewed',
            'visitor_session_id' => '0f8fad5b-d9cb-469f-a165-70867728950e',
            'source' => 'whatsapp',
        ]);
    }

    public function test_view_beacon_ignores_protected_galleries(): void
    {
        $this->gallery->update(['visibility' => 'private']);

        $this->postJson($this->galleryUrl() . '/viewed', [])->assertNotFound();
    }

    public function test_csrf_token_endpoint_starts_a_session(): void
    {
        $response = $this->getJson('/csrf-token')->assertOk();

        $this->assertNotEmpty($response->json('token'));
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertNotEmpty($response->headers->getCookies());
    }

    public function test_gallery_changes_queue_a_purge_of_the_photographer_site_when_configured(): void
    {
        config(['services.cloudflare.zone_id' => 'zone', 'services.cloudflare.api_token' => 'token']);
        Queue::fake();

        $this->gallery->update(['title' => 'Isaac & Celine']);

        Queue::assertPushed(PurgeEdgeCacheJob::class, fn ($job) => $job->prefix === 'richard.' . config('app.public_root_host') . '/');
    }

    public function test_nothing_is_queued_without_cloudflare_credentials(): void
    {
        config(['services.cloudflare.zone_id' => null, 'services.cloudflare.api_token' => null]);
        Queue::fake();

        $this->gallery->update(['title' => 'Isaac & Celine']);

        Queue::assertNotPushed(PurgeEdgeCacheJob::class);
    }

    public function test_purge_job_and_deploy_command_call_cloudflare(): void
    {
        config(['services.cloudflare.zone_id' => 'zone', 'services.cloudflare.api_token' => 'token']);
        Http::fake(['api.cloudflare.com/*' => Http::response(['success' => true])]);

        (new PurgeEdgeCacheJob('richard.ifotoset.com/'))->handle(app(\App\Services\GalleryCachePurgeService::class));
        $this->artisan('edge-cache:purge')->assertSuccessful();

        Http::assertSent(fn ($request) => ($request->data()['prefixes'] ?? null) === ['richard.ifotoset.com/']);
        Http::assertSent(fn ($request) => ($request->data()['tags'] ?? null) === [EdgeCache::PAGE_TAG]);
    }
}
