<?php

namespace Tests\Feature;

use App\Jobs\WatermarkGalleryJob;
use App\Jobs\WatermarkPhotoJob;
use App\Models\Gallery;
use App\Models\Photo;
use App\Models\Plan;
use App\Models\StorageDisk;
use App\Models\User;
use App\Services\PhotoWatermarkService;
use App\Services\StorageKeyResolver;
use App\Support\Gallery\Watermark;
use App\Support\Gallery\WatermarkRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;
use Ramsey\Uuid\Uuid;
use Tests\TestCase;

class GalleryWatermarkTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Gallery $gallery;
    protected Photo $photo;

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

        $plan->features()->syncWithoutDetaching([\App\Models\Feature::firstOrCreate(['code' => Watermark::FEATURE], ['name' => 'Gallery Watermarks'])->id]);

        $this->user = User::forceCreate([
            'email_verified_at' => now(),
            'uuid' => Uuid::uuid7()->toString(),
            'plan_id' => $plan->id,
            'name' => 'Alice Photographer',
            'username' => 'alice',
            'email' => 'alice@example.com',
            'password' => Hash::make('secret123'),
        ]);

        $this->gallery = Gallery::create([
            'uuid' => Uuid::uuid7()->toString(),
            'user_id' => $this->user->id,
            'title' => 'Diane & Kevin',
            'slug' => 'diane-kevin',
            'visibility' => 'public',
        ]);

        $this->gallery->stats()->increment('photo_count');
        $this->photo = Photo::create([
            'uuid' => Uuid::uuid7()->toString(),
            'gallery_id' => $this->gallery->id,
            'disk_id' => $disk->id,
            'path' => 'galleries/' . $this->gallery->uuid . '/p1',
            'filename' => 'photo_1.jpg',
            'original_filename' => 'IMG_0001.JPG',
            'mime_type' => 'image/jpeg',
            'size' => 1024 * 1024,
            'width' => 1200,
            'height' => 800,
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

    /**
     * A plain mid-grey photo, so any mark shows up as changed pixels.
     */
    private function photoImage(int $width = 1200, int $height = 800): \GdImage
    {
        $image = imagecreatetruecolor($width, $height);
        imagefill($image, 0, 0, imagecolorallocate($image, 120, 120, 120));

        return $image;
    }

    private function changedPixels(\GdImage $image, int $x0, int $y0, int $x1, int $y1): int
    {
        $changed = 0;
        for ($x = $x0; $x < $x1; $x += 4) {
            for ($y = $y0; $y < $y1; $y += 4) {
                $changed += (imagecolorat($image, $x, $y) & 0xFFFFFF) !== 0x787878 ? 1 : 0;
            }
        }

        return $changed;
    }

    public function test_galleries_keep_clean_previews_until_the_watermark_is_turned_on(): void
    {
        $this->photo->update(['watermark_version' => 'abcd1234']);

        $this->assertFalse($this->gallery->fresh()->watermark);
        $this->get($this->publicUrl())->assertOk()
            ->assertSee('photo_1_md.webp', false)
            ->assertDontSee('_w', false);
    }

    public function test_clients_see_the_watermarked_previews_and_the_studio_the_clean_ones(): void
    {
        $this->gallery->update(['watermark' => true]);
        $this->photo->update(['watermark_version' => 'abcd1234']);

        $this->get($this->publicUrl())->assertOk()
            ->assertSee('photo_1_md_wabcd1234.webp', false)
            ->assertSee('photo_1_xl_wabcd1234.webp', false)
            ->assertDontSee('photo_1_md.webp', false);

        $this->actingAs($this->user)->get("/studio/galleries/{$this->gallery->uuid}")->assertOk()
            ->assertDontSee('_wabcd1234', false);

        // The Design tab's preview shows what clients see
        $this->actingAs($this->user)->get("/studio/galleries/{$this->gallery->uuid}/preview")->assertOk()
            ->assertSee('photo_1_md_wabcd1234.webp', false);
    }

    public function test_turning_the_watermark_on_queues_the_gallery(): void
    {
        Queue::fake();

        $this->actingAs($this->user)
            ->patchJson("/studio/galleries/{$this->gallery->uuid}", ['watermark' => '1'])
            ->assertOk()
            ->assertJsonPath('summary.watermark', 'On');

        $this->assertTrue($this->gallery->fresh()->watermark);
        Queue::assertPushed(WatermarkGalleryJob::class);

        (new WatermarkGalleryJob($this->gallery))->handle();
        Queue::assertPushed(WatermarkPhotoJob::class, fn ($job) => $job->photo->is($this->photo));
    }

    public function test_watermark_previews_are_made_for_every_size_and_replace_an_older_design(): void
    {
        Storage::fake('b2');
        $xl = (string) (new ImageManager(new Driver()))->read($this->photoImage())->toWebp(80);
        Storage::disk('b2')->put("{$this->photo->path}/photo_1_xl.webp", $xl);
        $this->gallery->update(['watermark' => true]);

        $service = app(PhotoWatermarkService::class);
        $first = Watermark::for($this->user);
        $service->apply($this->photo, $first);

        $this->assertSame($first->version(), $this->photo->fresh()->watermark_version);
        foreach (PhotoWatermarkService::keys($this->photo, $first->version()) as $key) {
            Storage::disk('b2')->assertExists($key);
        }

        $this->user->update(['watermark' => ['position' => 'corner']]);
        $second = Watermark::for($this->user->fresh());
        $this->assertNotSame($first->version(), $second->version());
        $this->assertTrue($service->needsWatermark($this->photo->fresh(), $second));

        $service->apply($this->photo->fresh(), $second);
        Storage::disk('b2')->assertExists(PhotoWatermarkService::keys($this->photo, $second->version())[0]);
        Storage::disk('b2')->assertMissing(PhotoWatermarkService::keys($this->photo, $first->version())[0]);
        $this->assertFalse($service->needsWatermark($this->photo->fresh(), $second));
    }

    public function test_the_renderer_places_the_mark_where_it_was_asked(): void
    {
        $renderer = new WatermarkRenderer();

        $middle = $this->photoImage();
        $renderer->apply($middle, Watermark::for($this->user, ['position' => 'center']));
        $this->assertGreaterThan(0, $this->changedPixels($middle, 400, 300, 800, 500));
        $this->assertSame(0, $this->changedPixels($middle, 0, 0, 200, 150));

        $corner = $this->photoImage();
        $renderer->apply($corner, Watermark::for($this->user, ['position' => 'corner']));
        $this->assertGreaterThan(0, $this->changedPixels($corner, 850, 680, 1200, 800));
        $this->assertSame(0, $this->changedPixels($corner, 400, 300, 800, 500));

        $tiled = $this->photoImage();
        $renderer->apply($tiled, Watermark::for($this->user, ['position' => 'tiled']));
        $this->assertGreaterThan(0, $this->changedPixels($tiled, 0, 0, 600, 400));
        $this->assertGreaterThan(0, $this->changedPixels($tiled, 600, 400, 1200, 800));
    }

    public function test_saving_a_new_design_refreshes_watermarked_galleries_only(): void
    {
        Queue::fake();
        $other = Gallery::create([
            'uuid' => Uuid::uuid7()->toString(),
            'user_id' => $this->user->id,
            'title' => 'No watermark',
            'slug' => 'no-watermark',
            'visibility' => 'public',
        ]);
        $this->gallery->update(['watermark' => true]);

        $this->actingAs($this->user)->post('/studio/settings/watermark', ['type' => 'text', 'text' => 'Alice Studio', 'position' => 'tiled', 'opacity' => 60])
            ->assertRedirect()->assertSessionHasNoErrors();

        $watermark = Watermark::for($this->user->fresh());
        $this->assertSame(['Alice Studio', 'tiled', 60], [$watermark->text(), $watermark->position(), $watermark->opacity()]);
        Queue::assertPushed(WatermarkGalleryJob::class, 1);
        Queue::assertPushed(WatermarkGalleryJob::class, fn ($job) => $job->gallery->is($this->gallery));

        // Saving the same design again makes nothing new
        $this->actingAs($this->user)->post('/studio/settings/watermark', ['type' => 'text', 'text' => 'Alice Studio', 'position' => 'tiled', 'opacity' => 60]);
        Queue::assertPushed(WatermarkGalleryJob::class, 1);
        $this->assertFalse($other->fresh()->watermark);
    }

    public function test_unknown_watermark_values_are_refused_and_the_logo_needs_a_logo(): void
    {
        $this->actingAs($this->user)->post('/studio/settings/watermark', ['position' => 'diagonal', 'opacity' => 100])
            ->assertSessionHasErrors(['position', 'opacity']);

        // Without a portfolio logo, a logo watermark is drawn as text
        $this->user->update(['watermark' => ['type' => 'logo']]);
        $this->assertSame('text', Watermark::for($this->user->fresh())->type());
    }

    public function test_settings_preview_draws_the_unsaved_design(): void
    {
        Storage::fake('b2');

        $response = $this->actingAs($this->user)->get('/studio/settings/watermark/preview?type=text&text=Proof&position=corner&opacity=50');
        $response->assertOk()->assertHeader('Content-Type', 'image/jpeg');
        $this->assertNotFalse(imagecreatefromstring($response->getContent()));

        $this->actingAs($this->user)->get('/studio/settings')->assertOk()->assertSee('All over');
    }

    public function test_gallery_settings_show_progress_and_the_download_pin_hint(): void
    {
        $this->gallery->update(['watermark' => true]);

        $this->actingAs($this->user)->getJson("/studio/galleries/{$this->gallery->uuid}/watermark")
            ->assertOk()->assertExactJson(['done' => 0, 'total' => 1]);

        $this->photo->update(['watermark_version' => Watermark::for($this->user)->version()]);
        $this->actingAs($this->user)->getJson("/studio/galleries/{$this->gallery->uuid}/watermark")
            ->assertExactJson(['done' => 1, 'total' => 1]);

        $this->actingAs($this->user)->get("/studio/galleries/{$this->gallery->uuid}/edit")->assertOk()
            ->assertSee('On: “Alice Photographer”, middle.', false)
            ->assertSee('Clients can still download the clean photos.');
    }

    public function test_deleting_a_photo_removes_its_watermarked_previews(): void
    {
        $this->photo->update(['watermark_version' => 'abcd1234']);
        $keys = app(StorageKeyResolver::class)->resolvePhotoKeys($this->photo->fresh())['objects'];

        $this->assertContains("{$this->photo->path}/photo_1_xl_wabcd1234.webp", $keys);
        $this->assertContains("{$this->photo->path}/photo_1_xl.webp", $keys);
    }

    private function moveToFreePlan(): void
    {
        $free = Plan::firstOrCreate(['slug' => 'free'], [
            'uuid' => Uuid::uuid7()->toString(),
            'name' => 'Free',
            'storage_limit' => 1024 * 1024 * 1024,
            'video_limit' => 0,
            'gallery_limit' => 3,
            'team_limit' => 1,
        ]);
        $this->user->forceFill(['plan_id' => $free->id])->save();
        $this->user->unsetRelation('plan');
    }

    public function test_free_studios_cannot_turn_the_watermark_on_or_save_a_design(): void
    {
        Queue::fake();
        $this->moveToFreePlan();

        $this->actingAs($this->user)->patchJson("/studio/galleries/{$this->gallery->uuid}", ['watermark' => '1'])
            ->assertUnprocessable()->assertJsonValidationErrors('watermark');
        $this->assertFalse($this->gallery->fresh()->watermark);

        $this->actingAs($this->user)->post('/studio/settings/watermark', ['position' => 'corner'])->assertForbidden();

        // They can still try a design on their photo, and see where to upgrade
        $this->actingAs($this->user)->get('/studio/settings/watermark/preview?position=corner')->assertOk();
        $this->actingAs($this->user)->get('/studio/settings')->assertOk()->assertSee('Watermarks come with every paid plan.');
        Queue::assertNothingPushed();
    }

    public function test_after_a_plan_lapses_existing_watermarks_stay_but_no_new_ones_are_made(): void
    {
        Queue::fake();
        $this->gallery->update(['watermark' => true]);
        $this->photo->update(['watermark_version' => 'abcd1234']);
        $this->moveToFreePlan();

        // Clients don't suddenly see clean proofs
        $this->get($this->publicUrl())->assertOk()->assertSee('photo_1_md_wabcd1234.webp', false);

        Storage::fake('b2');
        (new WatermarkPhotoJob($this->photo))->handle(app(PhotoWatermarkService::class));
        $this->assertSame('abcd1234', $this->photo->fresh()->watermark_version);

        $this->actingAs($this->user)->get("/studio/galleries/{$this->gallery->uuid}/edit")->assertOk()
            ->assertSee("Your plan doesn't include watermarks any more.");

        // Turning it off still works
        $this->actingAs($this->user)->patchJson("/studio/galleries/{$this->gallery->uuid}", ['watermark' => '0'])->assertOk();
        $this->assertFalse($this->gallery->fresh()->watermark);
    }

    public function test_watermark_jobs_run_on_their_own_queue_and_skip_closed_galleries(): void
    {
        Queue::fake();
        $this->assertSame('watermarks', (new WatermarkPhotoJob($this->photo))->queue);

        $this->gallery->update(['watermark' => true, 'expires_at' => now()->subDay()]);
        Watermark::refreshGalleries($this->user);
        Queue::assertNotPushed(WatermarkGalleryJob::class);

        $this->gallery->update(['expires_at' => now()->addMonth()]);
        Watermark::refreshGalleries($this->user);
        Queue::assertPushed(WatermarkGalleryJob::class, 1);
    }

    public function test_new_galleries_can_start_with_the_watermark(): void
    {
        $this->actingAs($this->user)->post('/studio/settings/gallery-defaults', [
            'visibility' => 'public', 'photo_order' => 'taken', 'template' => 'full', 'color_mode' => 'light', 'watermark' => '1',
        ])->assertSessionHasNoErrors();

        $this->actingAs($this->user)->post('/studio/galleries', ['title' => 'School day'])->assertRedirect();
        $this->assertTrue(Gallery::where('title', 'School day')->firstOrFail()->watermark);
    }
}
