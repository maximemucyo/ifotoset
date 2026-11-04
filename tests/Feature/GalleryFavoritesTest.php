<?php

namespace Tests\Feature;

use App\Mail\FavoritesSubmittedMail;
use App\Models\Gallery;
use App\Models\GalleryFavoriteList;
use App\Models\Photo;
use App\Models\Plan;
use App\Models\StorageDisk;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Ramsey\Uuid\Uuid;
use Tests\TestCase;

class GalleryFavoritesTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected User $otherUser;
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

        $this->otherUser = User::forceCreate([
            'email_verified_at' => now(),
            'uuid' => Uuid::uuid7()->toString(),
            'plan_id' => $plan->id,
            'name' => 'Bob Photographer',
            'username' => 'bob',
            'email' => 'bob@example.com',
            'password' => Hash::make('secret123'),
        ]);

        $this->gallery = Gallery::create([
            'uuid' => Uuid::uuid7()->toString(),
            'user_id' => $this->user->id,
            'title' => 'Diane & Kevin',
            'slug' => 'diane-kevin',
            'visibility' => 'public',
        ]);
    }

    protected function createPhoto(array $attributes = []): Photo
    {
        return Photo::create(array_merge([
            'uuid' => Uuid::uuid7()->toString(),
            'gallery_id' => $this->gallery->id,
            'disk_id' => $this->disk->id,
            'path' => 'galleries/' . $this->gallery->uuid,
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
        ], $attributes));
    }

    private function api(string $path): string
    {
        return "/api/v1/public/galleries/alice/diane-kevin{$path}";
    }

    private function pick(Photo $photo, string $email = 'Diane@Example.com', bool $on = true)
    {
        return $this->postJson($this->api('/favorite'), ['photo_uuid' => $photo->uuid, 'is_favorite' => $on, 'email' => $email]);
    }

    public function test_picks_are_stored_per_email_and_counted_once(): void
    {
        $photo = $this->createPhoto();

        $this->pick($photo)->assertOk()->assertJsonPath('count', 1);
        $this->pick($photo)->assertOk()->assertJsonPath('count', 1);
        $this->pick($photo, 'kevin@example.com')->assertOk();

        $this->assertSame(2, $this->gallery->stats->fresh()->favorites_count);
        $this->assertSame(['diane@example.com', 'kevin@example.com'], GalleryFavoriteList::orderBy('email')->pluck('email')->all());

        $this->pick($photo, 'diane@example.com', false)->assertOk()->assertJsonPath('count', 0);
        $this->pick($photo, 'diane@example.com', false)->assertOk();
        $this->assertSame(1, $this->gallery->stats->fresh()->favorites_count);
    }

    public function test_picks_come_back_by_email_without_hidden_photos(): void
    {
        $shown = $this->createPhoto();
        $later = $this->createPhoto();
        $this->pick($shown);
        $this->pick($later);
        $later->update(['is_hidden' => true]);

        $this->getJson($this->api('/favorites?email=diane@example.com'))
            ->assertOk()
            ->assertJsonPath('photos', [$shown->uuid])
            ->assertJsonPath('submitted_at', null);

        $this->getJson($this->api('/favorites?email=nobody@example.com'))->assertOk()->assertJsonPath('photos', []);
    }

    public function test_photo_from_another_gallery_is_refused(): void
    {
        $other = Gallery::create([
            'uuid' => Uuid::uuid7()->toString(),
            'user_id' => $this->otherUser->id,
            'title' => 'Corporate',
            'slug' => 'corporate',
            'visibility' => 'public',
        ]);
        $foreign = $this->createPhoto(['gallery_id' => $other->id]);

        $this->pick($foreign)->assertNotFound();
        $this->assertSame(0, $this->gallery->stats->fresh()->favorites_count);
    }

    public function test_limit_stops_extra_picks(): void
    {
        $this->gallery->update(['favorites_limit' => 2]);
        [$a, $b, $c] = [$this->createPhoto(), $this->createPhoto(), $this->createPhoto()];

        $this->pick($a)->assertOk();
        $this->pick($b)->assertOk();
        $this->pick($c)->assertStatus(422)->assertJsonPath('code', 'FAVORITES_LIMIT');

        // Removing one makes room again
        $this->pick($a, on: false)->assertOk();
        $this->pick($c)->assertOk()->assertJsonPath('count', 2);
    }

    public function test_turned_off_favorites_refuse_picks_and_hide_the_heart(): void
    {
        $this->gallery->update(['favorites_enabled' => false]);
        $photo = $this->createPhoto();

        $this->pick($photo)->assertForbidden()->assertJsonPath('code', 'FAVORITES_DISABLED');
        $this->getJson($this->api('/favorites?email=diane@example.com'))->assertForbidden();

        $url = app(\App\Services\PublicUrlService::class)->galleryUrl('alice', 'diane-kevin');
        $this->get($url)->assertOk()->assertDontSee('btn-filter-favorites')->assertDontSee('favorites-bar');
    }

    public function test_restored_picks_are_stored_without_counting_again(): void
    {
        $photo = $this->createPhoto();

        $this->postJson($this->api('/favorite'), ['photo_uuid' => $photo->uuid, 'is_favorite' => true, 'email' => 'diane@example.com', 'restore' => true])
            ->assertOk()->assertJsonPath('count', 1);

        $this->assertSame(0, $this->gallery->stats->fresh()->favorites_count);
    }

    public function test_sending_picks_emails_the_photographer(): void
    {
        Mail::fake();
        $photo = $this->createPhoto();

        $this->postJson($this->api('/favorites/submit'), ['email' => 'diane@example.com'])
            ->assertStatus(422)->assertJsonPath('code', 'FAVORITES_EMPTY');

        $this->pick($photo);
        $this->postJson($this->api('/favorites/submit'), ['email' => 'diane@example.com'])->assertOk();

        $list = GalleryFavoriteList::firstWhere('email', 'diane@example.com');
        $this->assertNotNull($list->submitted_at);
        $this->assertFalse($list->changedSinceSubmitted());
        Mail::assertQueued(FavoritesSubmittedMail::class, fn ($mail) => $mail->hasTo('alice@example.com') && $mail->count === 1);

        $this->travel(1)->minute();
        $this->pick($this->createPhoto());
        $this->assertTrue($list->fresh()->changedSinceSubmitted());
        $this->getJson($this->api('/favorites?email=diane@example.com'))->assertJsonPath('changed', true);
    }

    public function test_photographer_can_turn_off_the_email(): void
    {
        Mail::fake();
        $this->user->forceFill(['notification_preferences' => ['favorites_submitted' => false]])->save();
        $this->pick($this->createPhoto());

        $this->postJson($this->api('/favorites/submit'), ['email' => 'diane@example.com'])->assertOk();

        Mail::assertNothingQueued();
    }

    public function test_favorites_tab_shows_each_persons_picks_and_file_names(): void
    {
        $first = $this->createPhoto(['original_filename' => 'IMG_0100.JPG', 'sort_name' => 'img_0100']);
        $second = $this->createPhoto(['original_filename' => 'IMG_0103.JPG', 'sort_name' => 'img_0103']);
        $this->gallery->update(['photo_order' => 'filename']);
        $this->pick($second);
        $this->pick($first);
        $this->pick($second, 'kevin@example.com');

        $this->actingAs($this->user);
        $url = "/studio/galleries/{$this->gallery->uuid}/activity";

        $this->get($url)->assertOk()
            ->assertSee('diane@example.com')
            ->assertSee('kevin@example.com')
            ->assertSee('IMG_0100, IMG_0103');

        $this->get($url . '?person=kevin@example.com')->assertOk()
            ->assertSee('IMG_0103.JPG')
            ->assertDontSee('IMG_0100.JPG');
    }

    public function test_csv_lists_each_pick(): void
    {
        $photo = $this->createPhoto(['original_filename' => 'IMG_0412.JPG']);
        $this->pick($photo);
        $this->pick($photo, 'kevin@example.com');

        $csv = $this->actingAs($this->user)
            ->get("/studio/galleries/{$this->gallery->uuid}/favorites/export?person=diane@example.com")
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString('Email,Name,"File name",Set,"Picked on","Sent on"', $csv);
        $this->assertStringContainsString('diane@example.com,,IMG_0412.JPG', $csv);
        $this->assertStringNotContainsString('kevin@example.com', $csv);
    }

    public function test_other_photographers_cannot_see_favorites(): void
    {
        $this->actingAs($this->otherUser)->get("/studio/galleries/{$this->gallery->uuid}/activity")->assertForbidden();
        $this->actingAs($this->otherUser)->get("/studio/galleries/{$this->gallery->uuid}/favorites/export")->assertForbidden();
    }

    public function test_settings_turn_favorites_off_and_set_a_limit(): void
    {
        $this->actingAs($this->user);
        $url = "/studio/galleries/{$this->gallery->uuid}";

        $this->patchJson($url, ['favorites_limit' => 30])->assertOk()->assertJsonPath('summary.favorites', 'Up to 30');
        $this->patchJson($url, ['favorites_limit' => 0])->assertJsonValidationErrors('favorites_limit');
        $this->patchJson($url, ['favorites_enabled' => '0'])->assertOk()->assertJsonPath('summary.favorites', 'Off');

        $gallery = $this->gallery->fresh();
        $this->assertFalse($gallery->favorites_enabled);
        $this->assertSame(30, $gallery->favorites_limit);

        $this->patchJson($url, ['favorites_enabled' => '1', 'favorites_limit' => ''])->assertOk()->assertJsonPath('summary.favorites', 'On');
    }

    public function test_old_favorites_links_open_the_activity_tab(): void
    {
        $this->actingAs($this->user)
            ->get("/studio/galleries/{$this->gallery->uuid}/favorites?person=diane@example.com")
            ->assertRedirect("/studio/galleries/{$this->gallery->uuid}/activity?view=favorites&person=diane%40example.com");
    }

    public function test_photographer_removes_a_persons_picks(): void
    {
        $photo = $this->createPhoto();
        $this->pick($photo);
        $this->pick($photo, 'kevin@example.com');
        $list = GalleryFavoriteList::firstWhere('email', 'diane@example.com');

        $this->actingAs($this->otherUser)->delete("/studio/galleries/{$this->gallery->uuid}/favorites/{$list->id}")->assertForbidden();
        $this->actingAs($this->user)->delete("/studio/galleries/{$this->gallery->uuid}/favorites/{$list->id}")->assertRedirect();

        $this->assertNull($list->fresh());
        $this->assertSame(1, $this->gallery->stats->fresh()->favorites_count);
        $this->getJson($this->api('/favorites?email=diane@example.com'))->assertJsonPath('photos', []);
    }

    public function test_downloads_view_shows_who_downloaded(): void
    {
        $this->gallery->update(['allow_photo_downloads' => true]);
        $photo = $this->createPhoto(['original_filename' => 'IMG_0777.JPG']);

        $this->get("/p/alice/diane-kevin/photos/{$photo->uuid}/download?email=Diane@Example.com");

        $this->actingAs($this->user)->get("/studio/galleries/{$this->gallery->uuid}/activity?view=downloads")
            ->assertOk()
            ->assertSee('IMG_0777.JPG')
            ->assertSee('diane@example.com');
    }
}
