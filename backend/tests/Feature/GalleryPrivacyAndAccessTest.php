<?php

namespace Tests\Feature;

use App\Models\Gallery;
use App\Models\GalleryInvitation;
use App\Models\Photo;
use App\Models\Plan;
use App\Models\StorageDisk;
use App\Models\User;
use App\Services\GalleryInvitationSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Ramsey\Uuid\Uuid;
use Tests\TestCase;

class GalleryPrivacyAndAccessTest extends TestCase
{
    use RefreshDatabase;

    protected User $photographerA;
    protected User $photographerB;
    protected StorageDisk $disk;
    protected \App\Services\PublicUrlService $urlService;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        $this->urlService = app(\App\Services\PublicUrlService::class);

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

        $this->photographerA = User::create([
            'uuid' => Uuid::uuid7()->toString(),
            'plan_id' => $plan->id,
            'name' => 'Alice Photographer',
            'username' => 'alice',
            'email' => 'alice@example.com',
            'password' => Hash::make('secret123'),
        ]);

        $this->photographerB = User::create([
            'uuid' => Uuid::uuid7()->toString(),
            'plan_id' => $plan->id,
            'name' => 'Bob Photographer',
            'username' => 'bob',
            'email' => 'bob@example.com',
            'password' => Hash::make('secret123'),
        ]);
    }

    protected function createPhoto(Gallery $gallery, string $filename = 'photo.jpg'): Photo
    {
        $photo = Photo::create([
            'uuid' => Uuid::uuid7()->toString(),
            'gallery_id' => $gallery->id,
            'disk_id' => $this->disk->id,
            'original_filename' => $filename,
            'stored_filename' => $filename,
            'filename' => $filename,
            'path' => "galleries/{$gallery->uuid}/{$filename}",
            'size' => 1024 * 500,
            'width' => 1920,
            'height' => 1080,
            'mime_type' => 'image/jpeg',
            'checksum' => hash('sha256', $filename),
        ]);

        if (!$gallery->cover_photo_id) {
            $gallery->update(['cover_photo_id' => $photo->id]);
        }

        return $photo;
    }

    public function test_public_gallery_accessible_via_direct_link_but_not_shown_on_profile_by_default(): void
    {
        $gallery = Gallery::create([
            'uuid' => Uuid::uuid7()->toString(),
            'user_id' => $this->photographerA->id,
            'title' => 'Secret Public Gallery',
            'slug' => 'secret-public-gallery',
            'visibility' => 'public',
            'show_on_profile' => false,
        ]);
        $this->createPhoto($gallery, 'hero1.jpg');

        $galleryUrl = $this->urlService->galleryUrl('alice', 'secret-public-gallery');
        $photographerUrl = $this->urlService->photographerUrl('alice');

        // 1. Direct link access is granted
        $directResponse = $this->get($galleryUrl);
        $directResponse->assertStatus(200);
        $directResponse->assertSee('Secret Public Gallery');
        $directResponse->assertSee('hero1.jpg');

        // 2. Profile page does NOT showcase this gallery
        $profileResponse = $this->get($photographerUrl);
        $profileResponse->assertStatus(200);
        $profileResponse->assertDontSee('Secret Public Gallery');
    }

    public function test_public_gallery_with_show_on_profile_appears_on_profile(): void
    {
        $gallery = Gallery::create([
            'uuid' => Uuid::uuid7()->toString(),
            'user_id' => $this->photographerA->id,
            'title' => 'Showcased Wedding',
            'slug' => 'showcased-wedding',
            'visibility' => 'public',
            'show_on_profile' => true,
        ]);
        $this->createPhoto($gallery, 'wedding.jpg');

        $photographerUrl = $this->urlService->photographerUrl('alice');

        $profileResponse = $this->get($photographerUrl);
        $profileResponse->assertStatus(200);
        $profileResponse->assertSee('Showcased Wedding');
    }

    public function test_expired_public_gallery_does_not_appear_on_profile(): void
    {
        $gallery = Gallery::create([
            'uuid' => Uuid::uuid7()->toString(),
            'user_id' => $this->photographerA->id,
            'title' => 'Old Expired Gallery',
            'slug' => 'old-expired-gallery',
            'visibility' => 'public',
            'show_on_profile' => true,
            'expires_at' => now()->subDay(),
        ]);
        $this->createPhoto($gallery, 'old.jpg');

        $photographerUrl = $this->urlService->photographerUrl('alice');

        $profileResponse = $this->get($photographerUrl);
        $profileResponse->assertStatus(200);
        $profileResponse->assertDontSee('Old Expired Gallery');
    }

    public function test_pin_protected_gallery_never_appears_on_profile_even_if_show_on_profile_is_true(): void
    {
        $gallery = Gallery::create([
            'uuid' => Uuid::uuid7()->toString(),
            'user_id' => $this->photographerA->id,
            'title' => 'PIN Protected Collection',
            'slug' => 'pin-protected-collection',
            'visibility' => 'password',
            'password_hash' => Hash::make('1234'),
            'show_on_profile' => true, // should be ignored
        ]);
        $this->createPhoto($gallery, 'pin.jpg');

        $photographerUrl = $this->urlService->photographerUrl('alice');

        $profileResponse = $this->get($photographerUrl);
        $profileResponse->assertStatus(200);
        $profileResponse->assertDontSee('PIN Protected Collection');
    }

    public function test_pin_protected_gallery_zero_leak_before_unlock(): void
    {
        $gallery = Gallery::create([
            'uuid' => Uuid::uuid7()->toString(),
            'user_id' => $this->photographerA->id,
            'title' => 'VIP VIP Event',
            'slug' => 'vip-event',
            'visibility' => 'password',
            'password_hash' => Hash::make('5678'),
            'password_hint' => 'Birth year',
        ]);
        $photo = $this->createPhoto($gallery, 'secret_vip.jpg');

        $galleryUrl = $this->urlService->galleryUrl('alice', 'vip-event');

        $response = $this->get($galleryUrl);
        $response->assertStatus(200);
        $response->assertSee('PIN Protected Gallery');
        $response->assertSee('Birth year');

        // ZERO LEAK ASSERTIONS:
        // No cover photo path, no B2 CDN URL, no photo UUID, no hero image tag
        $content = $response->getContent();
        $this->assertStringNotContainsString('secret_vip.jpg', $content);
        $this->assertStringNotContainsString($photo->uuid, $content);
        $this->assertStringNotContainsString('cdn.ifotoset.com/galleries', $content);
        $this->assertStringNotContainsString('<section id="gallery-hero"', $content);
    }

    public function test_pin_unlock_success_reveals_photos(): void
    {
        $gallery = Gallery::create([
            'uuid' => Uuid::uuid7()->toString(),
            'user_id' => $this->photographerA->id,
            'title' => 'VIP VIP Event',
            'slug' => 'vip-event-2',
            'visibility' => 'password',
            'password_hash' => Hash::make('5678'),
        ]);
        $photo = $this->createPhoto($gallery, 'secret_vip_2.jpg');

        $galleryUrl = $this->urlService->galleryUrl('alice', 'vip-event-2');

        // Attempt with wrong PIN
        $wrongUnlock = $this->postJson("{$galleryUrl}/unlock", ['password' => '0000']);
        $wrongUnlock->assertStatus(401);
        $wrongUnlock->assertJsonPath('message', 'The PIN you entered is incorrect.');

        // Attempt with correct PIN
        $correctUnlock = $this->postJson("{$galleryUrl}/unlock", ['password' => '5678']);
        $correctUnlock->assertStatus(200);
        $correctUnlock->assertJsonPath('success', true);

        // Subsequent view with session now unlocks gallery content
        $viewResponse = $this->get($galleryUrl);
        $viewResponse->assertStatus(200);
        $viewResponse->assertSee('secret_vip_2.jpg');
    }

    public function test_private_gallery_requires_invitation_without_token(): void
    {
        $gallery = Gallery::create([
            'uuid' => Uuid::uuid7()->toString(),
            'user_id' => $this->photographerA->id,
            'title' => 'Family Only',
            'slug' => 'family-only',
            'visibility' => 'private',
        ]);
        $photo = $this->createPhoto($gallery, 'family.jpg');

        $galleryUrl = $this->urlService->galleryUrl('alice', 'family-only');

        $response = $this->get($galleryUrl);
        $response->assertStatus(200);
        $response->assertSee('Invitation Required');

        // Zero-leak check:
        $content = $response->getContent();
        $this->assertStringNotContainsString('family.jpg', $content);
        $this->assertStringNotContainsString($photo->uuid, $content);
        $this->assertStringNotContainsString('<section id="gallery-hero"', $content);
    }

    public function test_private_gallery_canonical_redirect_and_session_flow(): void
    {
        $gallery = Gallery::create([
            'uuid' => Uuid::uuid7()->toString(),
            'user_id' => $this->photographerA->id,
            'title' => 'Family Only 2',
            'slug' => 'family-only-2',
            'visibility' => 'private',
        ]);
        $this->createPhoto($gallery, 'family2.jpg');

        $rawToken = bin2hex(random_bytes(32));
        $invitation = GalleryInvitation::create([
            'gallery_id' => $gallery->id,
            'email' => 'client@example.com',
            'token' => hash('sha256', $rawToken),
            'invited_by' => $this->photographerA->id,
        ]);

        $galleryUrl = $this->urlService->galleryUrl('alice', 'family-only-2');

        // 1. Visit with ?invite=RAW_TOKEN
        $redirectResponse = $this->get("{$galleryUrl}?invite={$rawToken}");

        // Asserts 302 canonical redirect to URL without query string
        $redirectResponse->assertStatus(302);
        $redirectResponse->assertRedirect($galleryUrl);

        // Asserts session was populated
        $sessionKey = GalleryInvitationSession::key($gallery);
        $this->assertTrue(session()->has($sessionKey));
        $this->assertEquals($invitation->id, session()->get($sessionKey)['invitation_id']);

        // 2. Follow redirect to canonical URL with active session
        $galleryResponse = $this->get($galleryUrl);
        $galleryResponse->assertStatus(200);
        $galleryResponse->assertSee('family2.jpg');

        // 3. Now photographer revokes the invitation
        $invitation->update(['revoked_at' => now()]);

        // 4. Subsequent visit with the SAME session must be denied immediately!
        $revokedResponse = $this->get($galleryUrl);
        $revokedResponse->assertStatus(200);
        $revokedResponse->assertSee('Invitation Unavailable');
        $this->assertStringNotContainsString('family2.jpg', $revokedResponse->getContent());
    }

    public function test_invitation_management_authorization_and_token_rotation(): void
    {
        $galleryA = Gallery::create([
            'uuid' => Uuid::uuid7()->toString(),
            'user_id' => $this->photographerA->id,
            'title' => 'Alice Private Shoot',
            'slug' => 'alice-private-shoot',
            'visibility' => 'private',
        ]);

        $rawTokenA = bin2hex(random_bytes(32));
        $invitationA = GalleryInvitation::create([
            'gallery_id' => $galleryA->id,
            'email' => 'guest@example.com',
            'token' => hash('sha256', $rawTokenA),
            'invited_by' => $this->photographerA->id,
        ]);

        // Photographer B tries to revoke Photographer A's invitation -> Forbidden 403
        $this->actingAs($this->photographerB);
        $unauthorizedRevoke = $this->post("/studio/galleries/{$galleryA->uuid}/invitations/{$invitationA->id}/revoke");
        $unauthorizedRevoke->assertStatus(403);

        // Photographer A revokes their own invitation
        $this->actingAs($this->photographerA);
        $authorizedRevoke = $this->post("/studio/galleries/{$galleryA->uuid}/invitations/{$invitationA->id}/revoke");
        $authorizedRevoke->assertStatus(302);
        $this->assertNotNull($invitationA->fresh()->revoked_at);

        // Photographer A resends invitation -> Token rotation creates new token
        $oldTokenHash = $invitationA->fresh()->token;
        $resend = $this->post("/studio/galleries/{$galleryA->uuid}/invitations/{$invitationA->id}/resend");
        $resend->assertStatus(302);

        $newTokenHash = $invitationA->fresh()->token;
        $this->assertNotEquals($oldTokenHash, $newTokenHash);
        $this->assertNull($invitationA->fresh()->revoked_at);
    }

    public function test_studio_gallery_create_and_update_normalizes_show_on_profile(): void
    {
        $this->actingAs($this->photographerA);

        // Create public gallery with show_on_profile
        $createResponse = $this->post('/studio/galleries', [
            'title' => 'Summer Fest',
            'visibility' => 'public',
            'show_on_profile' => '1',
        ]);
        $createResponse->assertStatus(302);

        $gallery = Gallery::where('title', 'Summer Fest')->first();
        $this->assertNotNull($gallery);
        $this->assertTrue($gallery->show_on_profile);

        // Update with show_on_profile unchecked (absent from request) -> normalizes to false!
        $updateResponse = $this->patch("/studio/galleries/{$gallery->uuid}", [
            'title' => 'Summer Fest Updated',
            'visibility' => 'public',
            // show_on_profile omitted
        ]);
        $updateResponse->assertStatus(302);

        $this->assertFalse($gallery->fresh()->show_on_profile);
    }

    public function test_csv_template_download(): void
    {
        $this->actingAs($this->photographerA);
        $response = $this->get('/studio/galleries/invitations/template');
        $response->assertStatus(200);
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('Content-Type'));
        $this->assertStringContainsString('email', $response->getContent());
        $this->assertStringContainsString('client1@example.com', $response->getContent());
    }
}
