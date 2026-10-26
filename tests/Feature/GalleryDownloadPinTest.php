<?php

namespace Tests\Feature;

use App\Models\Gallery;
use App\Models\GalleryDownload;
use App\Models\Photo;
use App\Models\Plan;
use App\Models\StorageDisk;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Ramsey\Uuid\Uuid;
use Tests\TestCase;

class GalleryDownloadPinTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Gallery $gallery;
    protected StorageDisk $disk;

    protected function setUp(): void
    {
        parent::setUp();

        try {
            \Illuminate\Support\Facades\Redis::connection()->flushdb();
        } catch (\Throwable $e) {
        }

        $this->disk = StorageDisk::create([
            'uuid' => Uuid::uuid7()->toString(),
            'driver' => 'b2',
            'bucket' => 'ifotoset',
            'region' => 'eu-central-003',
            'cdn_domain' => 'cdn.ifotoset.com',
        ]);

        $plan = Plan::firstOrCreate([
            'slug' => 'pro',
        ], [
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
            'name' => 'John Doe',
            'username' => 'johndoe',
            'email' => 'john@example.com',
            'password' => Hash::make('password123'),
        ]);

        $this->gallery = Gallery::create([
            'uuid' => Uuid::uuid7()->toString(),
            'user_id' => $this->user->id,
            'title' => 'Wedding Day',
            'slug' => 'wedding-day',
            'visibility' => 'public',
            'allow_photo_downloads' => true,
            'allow_gallery_downloads' => true,
            'download_pin' => '4821',
        ]);
    }

    private function galleryUrl(): string
    {
        return app(\App\Services\PublicUrlService::class)->galleryUrl($this->user->username, $this->gallery->slug);
    }

    private function createPhoto(): Photo
    {
        return Photo::create([
            'uuid' => Uuid::uuid7()->toString(),
            'gallery_id' => $this->gallery->id,
            'disk_id' => $this->disk->id,
            'path' => 'galleries/test',
            'filename' => 'photo.jpg',
            'original_filename' => 'DSC_0001.JPG',
            'stored_filename' => 'photo.jpg',
            'mime_type' => 'image/jpeg',
            'size' => 1024,
            'width' => 1920,
            'height' => 1080,
            'checksum' => hash('sha256', 'test-' . uniqid()),
            'status' => 'ready',
        ]);
    }

    private function downloadUrl(Photo $photo): string
    {
        return "/p/{$this->user->username}/{$this->gallery->slug}/photos/{$photo->uuid}/download";
    }

    public function test_gallery_stays_viewable_while_downloads_are_locked(): void
    {
        $this->createPhoto();

        $this->get($this->galleryUrl())->assertOk()->assertSee('modal-download-pin', false);
        $this->getJson($this->galleryUrl() . '/photos')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_photo_download_requires_pin(): void
    {
        $photo = $this->createPhoto();

        $this->getJson($this->downloadUrl($photo))
            ->assertStatus(403)
            ->assertJsonPath('code', 'DOWNLOAD_PIN_REQUIRED');
    }

    public function test_wrong_pin_is_rejected(): void
    {
        $this->postJson($this->galleryUrl() . '/download-unlock', ['pin' => '0000'])
            ->assertStatus(401)
            ->assertJsonPath('success', false);
    }

    public function test_correct_pin_unlocks_photo_downloads(): void
    {
        Storage::fake('b2');
        $photo = $this->createPhoto();
        Storage::disk('b2')->put('galleries/test/photo.jpg', 'bytes');

        $this->postJson($this->galleryUrl() . '/download-unlock', ['pin' => '4821'])
            ->assertOk()
            ->assertJsonPath('success', true);

        $status = $this->get($this->downloadUrl($photo))->getStatusCode();
        $this->assertContains($status, [200, 302]);
    }

    public function test_changing_the_pin_locks_downloads_again(): void
    {
        $photo = $this->createPhoto();

        $this->postJson($this->galleryUrl() . '/download-unlock', ['pin' => '4821'])->assertOk();
        $this->gallery->update(['download_pin' => '1357']);

        $this->getJson($this->downloadUrl($photo))->assertJsonPath('code', 'DOWNLOAD_PIN_REQUIRED');
    }

    public function test_unlock_is_limited_per_gallery_across_ips(): void
    {
        $url = $this->galleryUrl() . '/download-unlock';

        for ($i = 0; $i < 30; $i++) {
            $this->withServerVariables(['REMOTE_ADDR' => "10.0.0.{$i}"])->postJson($url, ['pin' => '0000']);
        }

        $this->withServerVariables(['REMOTE_ADDR' => '10.0.1.1'])
            ->postJson($url, ['pin' => '4821'])
            ->assertStatus(429);
    }

    public function test_photo_payload_hides_original_links_until_unlocked(): void
    {
        $photo = $this->createPhoto();
        $url = $this->galleryUrl() . "/photos?uuid={$photo->uuid}";

        $this->getJson($url)
            ->assertOk()
            ->assertJsonPath('data.original', null)
            ->assertJsonPath('data.delivery_download_url', null);

        $this->postJson($this->galleryUrl() . '/download-unlock', ['pin' => '4821'])->assertOk();

        $this->assertNotEmpty($this->getJson($url)->json('data.original'));
    }

    public function test_photo_payload_hides_original_links_when_downloads_are_off(): void
    {
        $this->gallery->update(['download_pin' => null, 'allow_photo_downloads' => false]);
        $photo = $this->createPhoto();

        $this->getJson($this->galleryUrl() . "/photos?uuid={$photo->uuid}")
            ->assertOk()
            ->assertJsonPath('data.original', null);
    }

    public function test_zip_download_requires_pin(): void
    {
        $this->createPhoto();

        $this->postJson("/api/v1/public/galleries/{$this->user->username}/{$this->gallery->slug}/download-zip", ['email' => 'client@example.com'])
            ->assertStatus(403)
            ->assertJsonPath('code', 'DOWNLOAD_PIN_REQUIRED');
    }

    public function test_existing_zip_cannot_be_fetched_by_id_while_locked(): void
    {
        $download = GalleryDownload::create([
            'gallery_id' => $this->gallery->id,
            'status' => 'ready',
            'storage_path' => 'galleries/test/archive.zip',
            'photo_snapshot_hash' => md5('snapshot'),
            'email' => 'someone@example.com',
        ]);

        $this->get("/api/v1/public/galleries/{$this->user->username}/{$this->gallery->slug}/download-zip/{$download->id}/download")
            ->assertRedirectContains('error=unauthorized');
    }

    public function test_export_page_redirects_back_to_gallery_while_locked(): void
    {
        $this->get("/p/{$this->user->username}/{$this->gallery->slug}/export")
            ->assertRedirect($this->gallery->public_url);
    }

    public function test_photographer_can_set_and_clear_a_download_pin(): void
    {
        $this->actingAs($this->user)->post(route('studio.galleries.store'), [
            'title' => 'Engagement',
            'visibility' => 'public',
            'download_pin_enabled' => '1',
            'download_pin' => '2468',
        ])->assertRedirect();

        $gallery = Gallery::where('title', 'Engagement')->firstOrFail();
        $this->assertSame('2468', $gallery->download_pin);

        $this->actingAs($this->user)->patch(route('studio.galleries.update', $gallery->uuid), [
            'title' => 'Engagement',
            'visibility' => 'public',
            'download_pin_enabled' => '0',
            'download_pin' => 'ab',
        ])->assertSessionHasNoErrors();

        $this->assertNull($gallery->fresh()->download_pin);
    }

    public function test_download_pin_must_be_four_digits(): void
    {
        $this->actingAs($this->user)->post(route('studio.galleries.store'), [
            'title' => 'Engagement',
            'visibility' => 'public',
            'download_pin_enabled' => '1',
            'download_pin' => '12a',
        ])->assertSessionHasErrors('download_pin');
    }

    public function test_new_gallery_form_defaults_to_public(): void
    {
        $this->actingAs($this->user)->get(route('studio.galleries.create'))
            ->assertOk()
            ->assertSee("visibility: 'public'", false);
    }
}
