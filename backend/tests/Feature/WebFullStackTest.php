<?php

namespace Tests\Feature;

use App\Models\Gallery;
use App\Models\Package;
use App\Models\Photo;
use App\Models\Plan;
use App\Models\StorageDisk;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Ramsey\Uuid\Uuid;
use Tests\TestCase;

class WebFullStackTest extends TestCase
{
    use RefreshDatabase;

    protected User $photographer;
    protected User $otherPhotographer;
    protected User $admin;
    protected Gallery $gallery;
    protected Package $package;
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

        $this->admin = User::create([
            'uuid' => Uuid::uuid7()->toString(),
            'plan_id' => $plan->id,
            'name' => 'Super Admin',
            'username' => 'superadmin',
            'email' => 'admin@example.com',
            'password' => Hash::make('password123'),
        ]);
        $this->admin->role = 'admin';
        $this->admin->save();

        $this->disk = StorageDisk::create([
            'uuid' => Uuid::uuid7()->toString(),
            'driver' => 'b2',
            'bucket' => 'ifotoset',
            'region' => 'eu-central-003',
            'cdn_domain' => 'cdn.ifotoset.com',
        ]);

        $this->gallery = Gallery::create([
            'uuid' => Uuid::uuid7()->toString(),
            'user_id' => $this->photographer->id,
            'title' => 'Serengeti Safari Collection',
            'slug' => 'serengeti-safari-collection',
            'visibility' => 'public',
            'show_on_profile' => true,
            'allow_photo_downloads' => true,
            'allow_gallery_downloads' => true,
        ]);

        $this->package = Package::create([
            'uuid' => Uuid::uuid7()->toString(),
            'user_id' => $this->photographer->id,
            'name' => 'Portrait Gold',
            'price' => 150000,
            'currency' => 'RWF',
            'duration_minutes' => 60,
            'deliverables' => ['50 Edited Photos', 'Online Gallery'],
            'is_active' => true,
        ]);

        Photo::create([
            'uuid' => Uuid::uuid7()->toString(),
            'gallery_id' => $this->gallery->id,
            'disk_id' => $this->disk->id,
            'path' => "galleries/{$this->gallery->id}/lion.jpg",
            'filename' => 'lion.jpg',
            'original_filename' => 'Lion_Portrait.JPG',
            'stored_filename' => 'stored_lion.jpg',
            'mime_type' => 'image/jpeg',
            'size' => 1024 * 600,
            'width' => 2000,
            'height' => 1333,
            'checksum' => hash('sha256', 'lion'),
            'blurhash' => 'L6PZfSi_.AyE_3t7t7R**0o#DgR4',
            'status' => 'ready',
        ]);

        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);
    }

    public function test_home_page_renders_successfully(): void
    {
        $response = $this->get('/');
        $response->assertStatus(200);
        $response->assertSee('ifotoset');
        $response->assertSee('The Complete Photography Platform');
    }

    public function test_login_flow(): void
    {
        $response = $this->get('/login');
        $response->assertStatus(200);

        $loginResponse = $this->post('/login', [
            'email' => 'alex@example.com',
            'password' => 'password123',
        ]);

        $loginResponse->assertRedirect('/studio/dashboard');
        $this->assertAuthenticatedAs($this->photographer);

        $logoutResponse = $this->post('/logout');
        $logoutResponse->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_registration_flow(): void
    {
        $response = $this->get('/register');
        $response->assertStatus(200);

        $registerResponse = $this->post('/register', [
            'name' => 'New Photographer',
            'username' => 'newphotographer',
            'email' => 'new@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $registerResponse->assertRedirect('/studio/dashboard');
        $this->assertDatabaseHas('users', ['username' => 'newphotographer']);
    }

    public function test_public_photographer_portfolio_view(): void
    {
        $urlService = app(\App\Services\PublicUrlService::class);
        $photographerUrl = $urlService->photographerUrl('alexmorgan');

        // 1. Apex redirect to canonical subdomain
        $apexResponse = $this->get('/p/alexmorgan');
        $apexResponse->assertStatus(301);
        $apexResponse->assertRedirect($photographerUrl);

        // 2. Subdomain renders portfolio
        $response = $this->get($photographerUrl);
        $response->assertStatus(200);
        $response->assertSee('Alex Morgan');
        $response->assertSee('Serengeti Safari Collection');
        $response->assertSee('Portrait Gold');
    }

    public function test_public_gallery_view_and_lean_json_stream(): void
    {
        $urlService = app(\App\Services\PublicUrlService::class);
        $galleryUrl = $urlService->galleryUrl('alexmorgan', 'serengeti-safari-collection');

        // 1. Apex redirect to canonical subdomain
        $apexResponse = $this->get('/p/alexmorgan/serengeti-safari-collection');
        $apexResponse->assertStatus(301);
        $apexResponse->assertRedirect($galleryUrl);

        // 2. Subdomain Blade HTML View
        $htmlResponse = $this->get($galleryUrl);
        $htmlResponse->assertStatus(200);
        $htmlResponse->assertSee('Serengeti Safari Collection');
        $htmlResponse->assertSee('Lion_Portrait.JPG');

        // 3. Lean JSON Stream on subdomain
        $jsonResponse = $this->getJson("{$galleryUrl}/photos");
        $jsonResponse->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.filename', 'Lion_Portrait.JPG');

        // 4. Backward-compatible JSON Stream on apex
        $apexJsonResponse = $this->getJson('/p/alexmorgan/serengeti-safari-collection/photos');
        $apexJsonResponse->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.filename', 'Lion_Portrait.JPG');

        // 5. Shortlink redirect
        $shortlink = $this->get('/g/serengeti-safari-collection');
        $shortlink->assertRedirect($galleryUrl);
    }

    public function test_public_gallery_export_route_and_access_protection(): void
    {
        $urlService = app(\App\Services\PublicUrlService::class);
        $galleryUrl = $urlService->galleryUrl('alexmorgan', 'serengeti-safari-collection');

        // 1. Export UI renders on subdomain for public gallery
        $response = $this->get("{$galleryUrl}/export?type=zip");
        $response->assertStatus(200);
        $response->assertSee('Export Gallery');
        $response->assertSee('Serengeti Safari Collection');
        $response->assertSee('Download Gallery Archive (ZIP)');

        // 2. Private gallery export redirects when locked
        $privateGallery = Gallery::create([
            'uuid' => Uuid::uuid7()->toString(),
            'user_id' => $this->photographer->id,
            'title' => 'Secret Safari',
            'slug' => 'secret-safari',
            'visibility' => 'password',
            'password_hash' => Hash::make('mypassword123'),
        ]);

        $privateGalleryUrl = $urlService->galleryUrl('alexmorgan', 'secret-safari');

        $lockedExportResponse = $this->get("{$privateGalleryUrl}/export");
        $lockedExportResponse->assertRedirect($privateGalleryUrl);

        // 3. Unlocking in session grants access to export page
        $unlockResponse = $this->postJson("{$privateGalleryUrl}/unlock", [
            'password' => 'mypassword123',
        ]);
        $unlockResponse->assertStatus(200)->assertJsonPath('success', true);

        $unlockedExportResponse = $this->get("{$privateGalleryUrl}/export");
        $unlockedExportResponse->assertStatus(200);
        $unlockedExportResponse->assertSee('Export Gallery');
    }

    public function test_password_checksum_session_invalidation_when_password_changes(): void
    {
        $gallery = Gallery::create([
            'uuid' => Uuid::uuid7()->toString(),
            'user_id' => $this->photographer->id,
            'title' => 'Client Wedding 2026',
            'slug' => 'client-wedding-2026',
            'visibility' => 'password',
            'password_hash' => Hash::make('firstpass'),
        ]);

        // Unlock with original password
        $unlockResponse = $this->postJson('/p/alexmorgan/client-wedding-2026/unlock', [
            'password' => 'firstpass',
        ]);
        $unlockResponse->assertStatus(200)->assertJsonPath('success', true);

        // Access is granted
        $photoStreamResponse = $this->getJson('/p/alexmorgan/client-wedding-2026/photos');
        $photoStreamResponse->assertStatus(200);

        // Owner changes password to a new one
        $gallery->password_hash = Hash::make('secondpass');
        $gallery->save();

        // Old session with mismatched password checksum is now rejected
        $rejectedResponse = $this->getJson('/p/alexmorgan/client-wedding-2026/photos');
        $rejectedResponse->assertStatus(403)
            ->assertJsonPath('code', 'PASSWORD_REQUIRED');
    }

    public function test_photos_stream_server_side_favorites_filtering(): void
    {
        $photo1 = Photo::create([
            'uuid' => Uuid::uuid7()->toString(),
            'gallery_id' => $this->gallery->id,
            'disk_id' => $this->disk->id,
            'path' => "galleries/{$this->gallery->id}/p1.jpg",
            'filename' => 'p1.jpg',
            'original_filename' => 'Photo_1.JPG',
            'stored_filename' => 'stored_p1.jpg',
            'mime_type' => 'image/jpeg',
            'size' => 1024 * 300,
            'width' => 1920,
            'height' => 1080,
            'checksum' => hash('sha256', 'p1'),
            'status' => 'ready',
        ]);

        $photo2 = Photo::create([
            'uuid' => Uuid::uuid7()->toString(),
            'gallery_id' => $this->gallery->id,
            'disk_id' => $this->disk->id,
            'path' => "galleries/{$this->gallery->id}/p2.jpg",
            'filename' => 'p2.jpg',
            'original_filename' => 'Photo_2.JPG',
            'stored_filename' => 'stored_p2.jpg',
            'mime_type' => 'image/jpeg',
            'size' => 1024 * 300,
            'width' => 1920,
            'height' => 1080,
            'checksum' => hash('sha256', 'p2'),
            'status' => 'ready',
        ]);

        // Querying with only photo1's UUID in uuids filter returns only photo1
        $filteredResponse = $this->getJson("/p/alexmorgan/serengeti-safari-collection/photos?uuids={$photo1->uuid}");
        $filteredResponse->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.uuid', $photo1->uuid);
    }

    public function test_public_gallery_deep_link_resolution_for_photo(): void
    {
        $photo = Photo::create([
            'uuid' => Uuid::uuid7()->toString(),
            'gallery_id' => $this->gallery->id,
            'disk_id' => $this->disk->id,
            'path' => "galleries/{$this->gallery->id}/deep.jpg",
            'filename' => 'deep.jpg',
            'original_filename' => 'Deep_Linked.JPG',
            'stored_filename' => 'stored_deep.jpg',
            'mime_type' => 'image/jpeg',
            'size' => 1024 * 300,
            'width' => 1920,
            'height' => 1080,
            'checksum' => hash('sha256', 'deep'),
            'status' => 'ready',
        ]);

        // 1. Apex redirect preserves photo query parameter
        $apexResponse = $this->get("/p/alexmorgan/serengeti-safari-collection?photo={$photo->uuid}");
        $apexResponse->assertStatus(301);
        $apexResponse->assertRedirect("http://alexmorgan.localhost:8000/serengeti-safari-collection?photo={$photo->uuid}");

        // 2. Subdomain Blade view embeds deep-linked photo dataset
        $response = $this->get("http://alexmorgan.localhost:8000/serengeti-safari-collection?photo={$photo->uuid}");
        $response->assertStatus(200);
        $response->assertSee('Deep_Linked.JPG');

        // 2. Individual photo resolver API returns metadata
        $resolverResponse = $this->getJson("/p/alexmorgan/serengeti-safari-collection/photos?uuid={$photo->uuid}");
        $resolverResponse->assertStatus(200)
            ->assertJsonPath('data.uuid', $photo->uuid)
            ->assertJsonPath('data.filename', 'Deep_Linked.JPG');
    }

    public function test_studio_dashboard_requires_authentication(): void
    {
        $response = $this->get('/studio/dashboard');
        $response->assertRedirect('/login');

        $authedResponse = $this->actingAs($this->photographer)->get('/studio/dashboard');
        $authedResponse->assertStatus(200);
        $authedResponse->assertSee('Alex Morgan');
    }

    public function test_gallery_ownership_policy_authorization(): void
    {
        // Photographer can view their own gallery in studio
        $ownResponse = $this->actingAs($this->photographer)->get("/studio/galleries/{$this->gallery->uuid}");
        $ownResponse->assertStatus(200);

        // Other photographer cannot view this gallery (403 Forbidden)
        $forbiddenResponse = $this->actingAs($this->otherPhotographer)->get("/studio/galleries/{$this->gallery->uuid}");
        $forbiddenResponse->assertStatus(403);
    }

    public function test_studio_gallery_creation(): void
    {
        $response = $this->actingAs($this->photographer)->post('/studio/galleries', [
            'title' => 'New Studio Wedding 2026',
            'visibility' => 'public',
            'client_name' => 'John & Jane',
            'allow_photo_downloads' => true,
            'allow_gallery_downloads' => true,
        ]);

        $gallery = Gallery::where('title', 'New Studio Wedding 2026')->first();
        $this->assertNotNull($gallery);
        $response->assertRedirect("/studio/galleries/{$gallery->uuid}");
        $this->assertDatabaseHas('gallery_stats', [
            'gallery_id' => $gallery->id,
        ]);
    }

    public function test_admin_dashboard_authorization(): void
    {
        // Normal user is redirected to studio dashboard with flash error
        $userResponse = $this->actingAs($this->photographer)->get('/admin/dashboard');
        $userResponse->assertRedirect(route('studio.dashboard'));
        $userResponse->assertSessionHas('toast');

        // Admin can access admin dashboard, users, and moderation
        $adminResponse = $this->actingAs($this->admin)->get('/admin/dashboard');
        $adminResponse->assertStatus(200);
        $adminResponse->assertSee('Platform Overview');

        $usersResponse = $this->actingAs($this->admin)->get('/admin/users');
        $usersResponse->assertStatus(200);
        $usersResponse->assertSee('User Management');
    }

    public function test_concurrent_booking_deterministic_locking(): void
    {
        $response = $this->post("/p/alexmorgan/book", [
            'client_name' => 'Alice Walker',
            'client_email' => 'alice@example.com',
            'package_id' => $this->package->uuid,
            'starts_at' => now()->addDays(2)->setHour(14)->setMinute(0)->toDateTimeString(),
            'notes' => 'Studio portrait session',
        ]);

        $response->assertSessionHas('success');
        $this->assertDatabaseHas('bookings', [
            'user_id' => $this->photographer->id,
            'title' => 'Booking: Alice Walker',
        ]);
    }
}
