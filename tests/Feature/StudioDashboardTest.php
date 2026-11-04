<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Gallery;
use App\Models\GalleryFavoriteList;
use App\Models\GalleryInvitation;
use App\Models\Photo;
use App\Models\Plan;
use App\Models\StorageDisk;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Ramsey\Uuid\Uuid;
use Tests\TestCase;

class StudioDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Gallery $gallery;
    protected StorageDisk $disk;

    protected function setUp(): void
    {
        parent::setUp();

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

        $this->user = User::forceCreate([
            'email_verified_at' => now(),
            'uuid' => Uuid::uuid7()->toString(),
            'plan_id' => $plan->id,
            'name' => 'Alice Photographer',
            'username' => 'alice',
            'email' => 'alice@example.com',
            'password' => Hash::make('secret123'),
        ]);

        $this->gallery = $this->createGallery('Diane & Kevin');
        $this->createPhoto();
    }

    protected function createGallery(string $title, array $attributes = []): Gallery
    {
        return Gallery::create(array_merge([
            'uuid' => Uuid::uuid7()->toString(),
            'user_id' => $this->user->id,
            'title' => $title,
            'slug' => str($title)->slug()->toString(),
            'visibility' => 'public',
        ], $attributes));
    }

    protected function createPhoto(?Gallery $gallery = null): Photo
    {
        $gallery ??= $this->gallery;
        $gallery->stats()->increment('photo_count');

        return Photo::create([
            'uuid' => Uuid::uuid7()->toString(),
            'gallery_id' => $gallery->id,
            'disk_id' => $this->disk->id,
            'path' => 'galleries/' . $gallery->uuid,
            'filename' => 'photo_' . uniqid() . '.jpg',
            'original_filename' => 'IMG_' . rand(1000, 9999) . '.JPG',
            'mime_type' => 'image/jpeg',
            'size' => 1024 * 1024,
            'width' => 1920,
            'height' => 1080,
            'checksum' => hash('sha256', uniqid()),
            'status' => 'ready',
            'is_hidden' => false,
            'sort_order' => 0,
        ]);
    }

    protected function sentPicks(string $email = 'diane@example.com'): GalleryFavoriteList
    {
        $list = GalleryFavoriteList::create(['gallery_id' => $this->gallery->id, 'email' => $email, 'submitted_at' => now()->subHour()]);
        DB::table('gallery_favorites')->insert(['list_id' => $list->id, 'photo_id' => $this->gallery->photos()->value('id'), 'created_at' => now()]);

        return $list;
    }

    public function test_nothing_needs_you_says_so(): void
    {
        $this->actingAs($this->user)->get('/studio/dashboard')
            ->assertOk()
            ->assertSee('Nothing needs you right now.')
            ->assertDontSee('Good afternoon');
    }

    public function test_needs_you_lists_each_kind_of_waiting_work(): void
    {
        $this->sentPicks();
        $this->createGallery('Graduation', ['expires_at' => now()->addDays(3)]);
        $empty = $this->createGallery('Kevin & Aline');
        $empty->forceFill(['created_at' => now()->subDays(3)])->saveQuietly();
        GalleryInvitation::forceCreate([
            'gallery_id' => $this->gallery->id,
            'email' => 'aline@example.com',
            'token' => hash('sha256', 'invite'),
            'created_at' => now()->subDays(6),
            'updated_at' => now()->subDays(6),
        ]);
        Booking::create([
            'user_id' => $this->user->id,
            'title' => 'Portrait session',
            'status' => 'pending',
            'starts_at' => now()->addWeek(),
            'ends_at' => now()->addWeek()->addHour(),
        ]);

        $this->actingAs($this->user)->get('/studio/dashboard')
            ->assertOk()
            ->assertDontSee('Nothing needs you right now.')
            ->assertSee('diane@example.com sent 1 favorite')
            ->assertSee('1 booking request to confirm')
            ->assertSee('“Graduation” closes in 3 days', false)
            ->assertSee('1 invite to “Diane &amp; Kevin” not opened', false)
            ->assertSee('“Kevin &amp; Aline” has no photos', false);
    }

    public function test_sent_picks_leave_needs_you_once_opened_and_come_back_when_changed(): void
    {
        $list = $this->sentPicks();
        $this->travel(1)->minutes();

        $this->actingAs($this->user)->get("/studio/galleries/{$this->gallery->uuid}/activity")->assertOk();

        $list->refresh();
        $this->assertNotNull($list->seen_at);
        $this->assertTrue($list->updated_at->lt($list->seen_at), 'Opening the picks must not look like a change by the client');
        $this->actingAs($this->user)->get('/studio/dashboard')->assertSee('Nothing needs you right now.');

        $this->travel(1)->minutes();
        $list->touch();

        $this->actingAs($this->user)->get('/studio/dashboard')->assertSee('diane@example.com changed their favorites');
    }

    public function test_another_studios_galleries_never_show(): void
    {
        $other = User::forceCreate([
            'email_verified_at' => now(),
            'uuid' => Uuid::uuid7()->toString(),
            'plan_id' => $this->user->plan_id,
            'name' => 'Bob',
            'username' => 'bob',
            'email' => 'bob@example.com',
            'password' => Hash::make('secret123'),
        ]);
        $this->sentPicks();

        $this->actingAs($other)->get('/studio/dashboard')
            ->assertOk()
            ->assertDontSee('diane@example.com')
            ->assertSee('Nothing needs you right now.');
    }

    public function test_checklist_hides_for_good(): void
    {
        $this->actingAs($this->user)->get('/studio/dashboard')->assertSee('Get your studio ready');

        $this->actingAs($this->user)->post('/studio/dashboard/checklist/dismiss')->assertRedirect();

        $this->assertNotNull($this->user->fresh()->setup_checklist_dismissed_at);
        $this->actingAs($this->user)->get('/studio/dashboard')->assertDontSee('Get your studio ready');
    }

    public function test_gallery_stage_follows_client_activity(): void
    {
        $load = fn () => Gallery::withLastViewedAt()->with('stats')->find($this->gallery->id);

        $this->assertSame('shared', $load()->stage());
        $this->assertSame('Not opened yet', $load()->activitySummary());

        DB::table('activity_logs')->insert(['gallery_id' => $this->gallery->id, 'event' => 'gallery_viewed', 'created_at' => now()->subHours(2)]);
        $this->assertSame('viewed', $load()->stage());
        $this->assertSame('Viewed 2 hours ago', $load()->activitySummary());

        $this->gallery->stats()->update(['downloads_count' => 3, 'favorites_count' => 48]);
        $this->assertSame('downloaded', $load()->stage());
        $this->assertSame('Viewed 2 hours ago, 48 favorites, 3 downloads', $load()->activitySummary());

        $this->gallery->update(['expires_at' => now()->subDay()]);
        $this->assertSame('expired', $load()->stage());

        $this->gallery->update(['expires_at' => null, 'published_at' => null]);
        $this->assertSame('draft', $load()->stage());
    }

    public function test_galleries_page_filters_by_stage(): void
    {
        $this->createGallery('Draft one', ['published_at' => null]);
        $this->createGallery('Closing soon', ['expires_at' => now()->addDays(2)]);
        $this->createGallery('Long gone', ['expires_at' => now()->subDays(2)]);

        $titles = fn (string $stage) => $this->actingAs($this->user)
            ->get('/studio/galleries?stage=' . $stage)
            ->assertOk()
            ->viewData('galleries')
            ->pluck('title')
            ->sort()
            ->values()
            ->all();

        $this->assertSame(['Draft one'], $titles('drafts'));
        $this->assertSame(['Closing soon', 'Diane & Kevin'], $titles('shared'));
        $this->assertSame(['Closing soon'], $titles('expiring'));
        $this->assertSame(['Long gone'], $titles('expired'));
        $this->assertCount(4, $titles('all'));
    }
}
