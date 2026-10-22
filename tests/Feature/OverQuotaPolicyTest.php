<?php

namespace Tests\Feature;

use App\Actions\Billing\ExpireSubscriptions;
use App\Enums\OverQuotaStage;
use App\Mail\OverQuotaNoticeMail;
use App\Mail\SubscriptionExpiredMail;
use App\Models\Gallery;
use App\Models\Photo;
use App\Models\Plan;
use App\Models\StorageDisk;
use App\Models\Subscription;
use App\Models\User;
use App\Services\GalleryStatisticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Ramsey\Uuid\Uuid;
use Tests\TestCase;

class OverQuotaPolicyTest extends TestCase
{
    use RefreshDatabase;

    private const PHOTO_BYTES = 500 * 1024;

    protected Plan $freePlan;
    protected Plan $proPlan;
    protected User $user;
    protected StorageDisk $disk;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('b2');
        Mail::fake();

        // 1 MB Free limit: two 500 KB photos fit, three don't
        $this->freePlan = Plan::create([
            'uuid' => Uuid::uuid7()->toString(),
            'slug' => 'free',
            'name' => 'Free',
            'monthly_price' => 0,
            'annual_price' => 0,
            'currency' => 'RWF',
            'storage_limit' => 1024 * 1024,
            'video_limit' => 0,
            'team_limit' => 0,
        ]);

        $this->proPlan = Plan::create([
            'uuid' => Uuid::uuid7()->toString(),
            'slug' => 'pro',
            'name' => 'Professional',
            'monthly_price' => 29999,
            'annual_price' => 299988,
            'currency' => 'RWF',
            'storage_limit' => 100 * 1024 * 1024 * 1024,
            'video_limit' => 0,
            'team_limit' => 0,
        ]);

        $this->user = User::forceCreate([
            'email_verified_at' => now(),
            'uuid' => Uuid::uuid7()->toString(),
            'plan_id' => $this->freePlan->id,
            'name' => 'Lapsed Photographer',
            'username' => 'lapsed',
            'email' => 'lapsed@example.com',
            'password' => Hash::make('secret123'),
            'role' => 'photographer',
        ]);

        $this->disk = StorageDisk::firstOrCreate(['driver' => 'b2'], [
            'uuid' => Uuid::uuid7()->toString(),
            'bucket' => 'ifotoset-media',
            'region' => 'us-east-005',
            'cdn_domain' => 'cdn.ifotoset.com',
        ]);
    }

    protected function createGalleryWithPhoto(string $title, ?\DateTimeInterface $createdAt = null): Gallery
    {
        $gallery = Gallery::create([
            'uuid' => Uuid::uuid7()->toString(),
            'user_id' => $this->user->id,
            'title' => $title,
            'slug' => \Illuminate\Support\Str::slug($title),
            'visibility' => 'public',
            'allow_photo_downloads' => true,
            'allow_gallery_downloads' => true,
        ]);
        if ($createdAt) {
            $gallery->forceFill(['created_at' => $createdAt])->save();
        }

        $photoUuid = Uuid::uuid7()->toString();
        $dirPath = "galleries/{$gallery->uuid}/photos/{$photoUuid}";
        Photo::create([
            'uuid' => $photoUuid,
            'gallery_id' => $gallery->id,
            'disk_id' => $this->disk->id,
            'path' => $dirPath,
            'filename' => 'image.jpg',
            'original_filename' => 'image.jpg',
            'stored_filename' => 'image.jpg',
            'mime_type' => 'image/jpeg',
            'size' => self::PHOTO_BYTES,
            'checksum' => hash('sha256', $title),
            'status' => 'ready',
        ]);
        Storage::disk('b2')->put("{$dirPath}/image.jpg", 'original-bytes');

        app(GalleryStatisticsService::class)->recalculateUserStorage($this->user->id);

        return $gallery;
    }

    /**
     * Three galleries (1.5 MB) on a 1 MB Free plan, over quota since $daysAgo.
     */
    protected function overQuotaFor(int $daysAgo): array
    {
        $galleries = [
            $this->createGalleryWithPhoto('Oldest Wedding', now()->subYears(2)),
            $this->createGalleryWithPhoto('Middle Portraits', now()->subYear()),
            $this->createGalleryWithPhoto('Newest Event', now()->subMonth()),
        ];

        $this->user->forceFill(['over_quota_since' => now()->subDays($daysAgo), 'over_quota_notice' => 'started'])->save();

        return $galleries;
    }

    public function test_expiring_a_subscription_over_the_free_limit_starts_the_timeline(): void
    {
        $this->createGalleryWithPhoto('One');
        $this->createGalleryWithPhoto('Two');
        $this->createGalleryWithPhoto('Three');
        $this->user->forceFill(['plan_id' => $this->proPlan->id])->save();

        Subscription::create([
            'uuid' => Uuid::uuid7()->toString(),
            'user_id' => $this->user->id,
            'plan_id' => $this->proPlan->id,
            'provider' => 'pawapay',
            'status' => 'active',
            'billing_cycle' => 'monthly',
            'starts_at' => now()->subMonth(),
            'ends_at' => now()->subMinute(),
        ]);

        app(ExpireSubscriptions::class)->execute();

        $this->user->refresh();
        $this->assertSame($this->freePlan->id, $this->user->plan_id);
        $this->assertNotNull($this->user->over_quota_since);
        $this->assertSame('started', $this->user->over_quota_notice);
        $this->assertSame(OverQuotaStage::Grace, $this->user->overQuotaStage());
        Mail::assertQueued(SubscriptionExpiredMail::class);

        // The expiry email already explained the timeline, so the daily run doesn't repeat it
        $this->artisan('storage:enforce-free-quota')->assertSuccessful();
        Mail::assertNotQueued(OverQuotaNoticeMail::class);
    }

    public function test_grace_period_keeps_everything_working_except_uploads(): void
    {
        [$gallery] = $this->overQuotaFor(10);
        $photo = $gallery->photos()->first();

        $this->assertSame(OverQuotaStage::Grace, $this->user->refresh()->overQuotaStage());
        $this->get(route('public.gallery.photos', ['username' => 'lapsed', 'slug' => $gallery->slug]))->assertOk();
        $this->get(route('public.gallery.photo.download', ['username' => 'lapsed', 'slug' => $gallery->slug, 'uuid' => $photo->uuid]))
            ->assertStatus(302);
        $this->actingAs($this->user)->get(route('studio.galleries.create'))->assertOk();
    }

    public function test_restricted_stage_pauses_new_galleries_and_client_downloads(): void
    {
        [$gallery] = $this->overQuotaFor(35);
        $photo = $gallery->photos()->first();

        $this->assertSame(OverQuotaStage::Restricted, $this->user->refresh()->overQuotaStage());

        // Clients can still view
        $this->get(route('public.gallery.photos', ['username' => 'lapsed', 'slug' => $gallery->slug]))->assertOk();

        // ...but not download
        $this->get(route('public.gallery.photo.download', ['username' => 'lapsed', 'slug' => $gallery->slug, 'uuid' => $photo->uuid]))
            ->assertStatus(403)->assertJsonPath('code', 'DOWNLOADS_DISABLED');
        $this->postJson(route('api.public.gallery.zip', ['username' => 'lapsed', 'slug' => $gallery->slug]))
            ->assertStatus(403)->assertJsonPath('code', 'DOWNLOADS_DISABLED');

        // The photographer can't start a new gallery
        $this->actingAs($this->user)->get(route('studio.galleries.create'))
            ->assertRedirect(route('studio.galleries.index'));
        $this->actingAs($this->user)->post(route('studio.galleries.store'), ['title' => 'New one', 'visibility' => 'public'])
            ->assertRedirect(route('studio.galleries.index'))
            ->assertSessionHas('error');
        $this->assertDatabaseMissing('galleries', ['title' => 'New one']);
    }

    public function test_archived_stage_takes_galleries_offline_for_clients(): void
    {
        [$gallery] = $this->overQuotaFor(65);

        $this->assertSame(OverQuotaStage::Archived, $this->user->refresh()->overQuotaStage());
        $this->get(route('public.gallery.photos', ['username' => 'lapsed', 'slug' => $gallery->slug]))
            ->assertStatus(403)->assertJsonPath('code', 'GALLERY_ARCHIVED');
    }

    public function test_upgrading_or_freeing_space_lifts_restrictions_immediately(): void
    {
        [$oldest, $middle] = $this->overQuotaFor(65);

        $this->user->forceFill(['plan_id' => $this->proPlan->id])->save();
        $this->assertNull($this->user->refresh()->overQuotaStage());
        $this->get(route('public.gallery.photos', ['username' => 'lapsed', 'slug' => $oldest->slug]))->assertOk();

        // Back on Free and over the limit again, then delete a gallery to fit
        $this->user->forceFill(['plan_id' => $this->freePlan->id])->save();
        $this->assertSame(OverQuotaStage::Archived, $this->user->refresh()->overQuotaStage());

        $middle->photos()->forceDelete();
        app(GalleryStatisticsService::class)->recalculateUserStorage($this->user->id);
        $this->assertNull($this->user->refresh()->overQuotaStage());
        $this->get(route('public.gallery.photos', ['username' => 'lapsed', 'slug' => $oldest->slug]))->assertOk();

        // The daily run clears the flag so a later overage starts a fresh timeline
        $this->artisan('storage:enforce-free-quota')->assertSuccessful();
        $this->assertNull($this->user->refresh()->over_quota_since);
    }

    public function test_daily_run_flags_free_accounts_over_the_limit_and_sends_each_notice_once(): void
    {
        $this->createGalleryWithPhoto('One');
        $this->createGalleryWithPhoto('Two');
        $this->createGalleryWithPhoto('Three');

        $this->artisan('storage:enforce-free-quota')->assertSuccessful();
        $this->assertNotNull($this->user->refresh()->over_quota_since);
        Mail::assertQueued(OverQuotaNoticeMail::class, fn ($mail) => $mail->kind === 'started');

        // Day 23: one week before restrictions
        $this->travel(23)->days();
        $this->artisan('storage:enforce-free-quota')->assertSuccessful();
        $this->artisan('storage:enforce-free-quota')->assertSuccessful();
        Mail::assertQueued(OverQuotaNoticeMail::class, fn ($mail) => $mail->kind === 'restrict_soon');
        Mail::assertQueued(OverQuotaNoticeMail::class, 2);

        // A missed week only sends the latest notice
        $this->travel(61)->days(); // day 84
        $this->artisan('storage:enforce-free-quota')->assertSuccessful();
        Mail::assertQueued(OverQuotaNoticeMail::class, fn ($mail) => $mail->kind === 'delete_soon');
        Mail::assertNotQueued(OverQuotaNoticeMail::class, fn ($mail) => $mail->kind === 'archive_soon');
        $this->assertSame('delete_soon', $this->user->refresh()->over_quota_notice);
    }

    public function test_paid_and_admin_accounts_are_never_flagged(): void
    {
        $this->createGalleryWithPhoto('One');
        $this->createGalleryWithPhoto('Two');
        $this->createGalleryWithPhoto('Three');
        $this->user->forceFill(['plan_id' => $this->proPlan->id])->save();

        $this->artisan('storage:enforce-free-quota')->assertSuccessful();
        $this->assertNull($this->user->refresh()->over_quota_since);

        $this->user->forceFill(['plan_id' => $this->freePlan->id, 'role' => 'admin'])->save();
        $this->artisan('storage:enforce-free-quota')->assertSuccessful();
        $this->assertNull($this->user->refresh()->over_quota_since);
    }

    public function test_after_the_deadline_the_oldest_galleries_are_deleted_until_within_the_limit(): void
    {
        [$oldest, $middle, $newest] = $this->overQuotaFor(90);
        $oldestPhotoKey = $oldest->photos()->first()->path . '/image.jpg';
        Storage::disk('b2')->assertExists($oldestPhotoKey);

        $this->artisan('storage:enforce-free-quota')->assertSuccessful();

        $this->assertDatabaseMissing('galleries', ['id' => $oldest->id]);
        $this->assertDatabaseHas('galleries', ['id' => $middle->id, 'deleted_at' => null]);
        $this->assertDatabaseHas('galleries', ['id' => $newest->id, 'deleted_at' => null]);
        Storage::disk('b2')->assertMissing($oldestPhotoKey);

        $this->user->refresh();
        $this->assertSame(2 * self::PHOTO_BYTES, $this->user->storage_used_bytes);
        $this->assertNull($this->user->over_quota_since);
        $this->assertNull($this->user->overQuotaStage());
        Mail::assertQueued(OverQuotaNoticeMail::class, fn ($mail) => $mail->kind === 'deleted' && $mail->deletedGalleries === ['Oldest Wedding']);

        // Remaining galleries are back online
        $this->get(route('public.gallery.photos', ['username' => 'lapsed', 'slug' => $middle->slug]))->assertOk();
    }

    public function test_nothing_is_deleted_before_the_deadline(): void
    {
        $galleries = $this->overQuotaFor(89);

        $this->artisan('storage:enforce-free-quota')->assertSuccessful();

        foreach ($galleries as $gallery) {
            $this->assertDatabaseHas('galleries', ['id' => $gallery->id, 'deleted_at' => null]);
        }
        Mail::assertQueued(OverQuotaNoticeMail::class, fn ($mail) => $mail->kind === 'delete_tomorrow');
    }

    public function test_notice_emails_render(): void
    {
        $this->overQuotaFor(0);

        foreach (['started', 'restrict_soon', 'archive_soon', 'delete_soon', 'delete_tomorrow'] as $notice) {
            $html = (new OverQuotaNoticeMail($this->user->refresh(), $notice))->render();
            $this->assertStringContainsString('What happens next', $html);
        }

        $html = (new OverQuotaNoticeMail($this->user, 'deleted', ['Oldest Wedding']))->render();
        $this->assertStringContainsString('Oldest Wedding', $html);
    }

    public function test_studio_shows_the_over_quota_banner(): void
    {
        $this->overQuotaFor(35);

        $this->actingAs($this->user->refresh())->get(route('studio.dashboard'))
            ->assertOk()
            ->assertSee('over the Free plan', false)
            ->assertSee('your galleries go offline for clients', false);
    }
}
