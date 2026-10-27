<?php

namespace Tests\Feature;

use App\Actions\Billing\AssignPlan;
use App\Actions\Billing\RevokePlan;
use App\Enums\AccountRole;
use App\Mail\TeamInvitationMail;
use App\Mail\TeamSeatsSuspendedMail;
use App\Models\AccountInvitation;
use App\Models\AccountMember;
use App\Models\Booking;
use App\Models\Client;
use App\Models\Feature;
use App\Models\Gallery;
use App\Models\Plan;
use App\Models\User;
use App\Services\StorageService;
use App\Support\StudioAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Mockery;
use Ramsey\Uuid\Uuid;
use Tests\TestCase;

class TeamAccountsTest extends TestCase
{
    use RefreshDatabase;

    protected Plan $freePlan;
    protected Plan $businessPlan;
    protected User $owner;
    protected User $otherOwner;
    protected User $admin;
    protected Gallery $ownerGallery;
    protected Gallery $otherGallery;
    protected Client $ownerClient;

    protected function setUp(): void
    {
        parent::setUp();

        $this->freePlan = $this->plan('free', 0, 2147483648);
        $this->businessPlan = $this->plan('business', 5, 3 * 1024 ** 4);

        $teams = Feature::create(['code' => 'teams', 'name' => 'Multi-user Team Accounts']);
        DB::table('plan_features')->insert(['plan_id' => $this->businessPlan->id, 'feature_id' => $teams->id]);

        $this->owner = $this->user('Studio Owner', 'ownerstudio', 'owner@example.com', $this->businessPlan);
        $this->otherOwner = $this->user('Other Studio', 'otherstudio', 'other@example.com', $this->businessPlan);
        $this->admin = $this->user('Platform Admin', 'platformadmin', 'admin@example.com', $this->freePlan);
        $this->admin->forceFill(['role' => 'admin'])->save();

        $this->ownerGallery = $this->gallery($this->owner, 'Owner Wedding');
        $this->otherGallery = $this->gallery($this->otherOwner, 'Other Wedding');

        $this->ownerClient = Client::create([
            'uuid' => Uuid::uuid7()->toString(),
            'user_id' => $this->owner->id,
            'name' => 'Owner Client',
            'email' => 'client@example.com',
        ]);
    }

    private function plan(string $slug, int $teamLimit, int $storage): Plan
    {
        return Plan::create([
            'uuid' => Uuid::uuid7()->toString(),
            'slug' => $slug,
            'name' => ucfirst($slug),
            'monthly_price' => $slug === 'free' ? 0 : 50000,
            'annual_price' => $slug === 'free' ? 0 : 500000,
            'currency' => 'RWF',
            'storage_limit' => $storage,
            'video_limit' => 0,
            'gallery_limit' => null,
            'team_limit' => $teamLimit,
        ]);
    }

    private function user(string $name, ?string $username, string $email, ?Plan $plan = null): User
    {
        return User::forceCreate([
            'uuid' => Uuid::uuid7()->toString(),
            'plan_id' => ($plan ?? $this->freePlan)->id,
            'name' => $name,
            'username' => $username,
            'email' => $email,
            'password' => Hash::make('password123'),
            'email_verified_at' => now(),
        ]);
    }

    private function gallery(User $owner, string $title): Gallery
    {
        return Gallery::create([
            'uuid' => Uuid::uuid7()->toString(),
            'user_id' => $owner->id,
            'title' => $title,
            'slug' => \Illuminate\Support\Str::slug($title),
            'visibility' => 'public',
            'version' => 1,
        ]);
    }

    private function member(User $owner, User $user, AccountRole $role = AccountRole::Member): AccountMember
    {
        return AccountMember::create([
            'account_id' => $owner->id,
            'user_id' => $user->id,
            'role' => $role,
            'invited_by' => $owner->id,
        ]);
    }

    /**
     * Act as $user with $owner's studio selected.
     */
    private function inStudio(User $user, User $owner): static
    {
        return $this->actingAs($user)->withSession([StudioAccount::SESSION_KEY => $owner->id]);
    }

    private function invite(string $email, string $role = 'member'): string
    {
        Mail::fake();

        $this->actingAs($this->owner)
            ->post(route('studio.team.invite'), ['email' => $email, 'role' => $role])
            ->assertSessionHas('success');

        $token = null;
        Mail::assertQueued(TeamInvitationMail::class, function (TeamInvitationMail $mail) use ($email, &$token) {
            $token = basename($mail->acceptUrl);

            return $mail->hasTo(strtolower($email));
        });

        return $token;
    }

    // ---------------------------------------------------------------- Invitations

    public function test_new_person_accepts_invite_and_sees_owners_studio(): void
    {
        $token = $this->invite('Shooter@Example.com');

        $this->assertDatabaseHas('account_invitations', ['account_id' => $this->owner->id, 'email' => 'shooter@example.com']);
        $this->assertDatabaseMissing('account_invitations', ['token_hash' => $token]);

        auth()->logout();
        $this->get(route('team.invitation.show', $token))->assertOk()->assertSee('Join Studio Owner');

        $this->post(route('team.invitation.register', $token), [
            'name' => 'New Shooter',
            'password' => 'secret-pass-1',
            'password_confirmation' => 'secret-pass-1',
        ])->assertRedirect(route('studio.dashboard'));

        $member = User::where('email', 'shooter@example.com')->firstOrFail();
        $this->assertNull($member->username);
        $this->assertNotNull($member->email_verified_at);
        $this->assertSame($this->freePlan->id, $member->plan_id);
        $this->assertAuthenticatedAs($member);
        $this->assertDatabaseHas('account_members', ['account_id' => $this->owner->id, 'user_id' => $member->id, 'role' => 'member']);

        $this->get(route('studio.galleries.index'))->assertOk()->assertSee('Owner Wedding')->assertDontSee('Other Wedding');
        $this->get(route('studio.galleries.show', $this->ownerGallery->uuid))->assertOk();
        $this->get(route('studio.clients.index'))->assertOk()->assertSee('Owner Client');
    }

    public function test_existing_logged_in_user_accepts_invite(): void
    {
        $existing = $this->user('Existing Shooter', 'existingshooter', 'existing@example.com');
        $token = $this->invite('existing@example.com', 'manager');

        $this->actingAs($existing)->get(route('team.invitation.show', $token))->assertOk()->assertSee('Join Studio Owner');
        $this->post(route('team.invitation.accept', $token))->assertRedirect(route('studio.dashboard'));

        $this->assertDatabaseHas('account_members', ['account_id' => $this->owner->id, 'user_id' => $existing->id, 'role' => 'manager']);
        $this->assertSame($this->owner->id, session(StudioAccount::SESSION_KEY));
        $this->get(route('studio.galleries.index'))->assertSee('Owner Wedding');
    }

    public function test_guest_with_existing_login_is_sent_to_login_then_back(): void
    {
        $this->user('Existing Shooter', 'existingshooter', 'existing@example.com');
        $token = $this->invite('existing@example.com');
        auth()->logout();

        $this->get(route('team.invitation.show', $token))->assertRedirect(route('login'));

        $this->post('/login', ['email' => 'existing@example.com', 'password' => 'password123'])
            ->assertRedirect(route('team.invitation.show', $token));
    }

    public function test_logged_in_as_someone_else_cannot_accept(): void
    {
        $stranger = $this->user('Stranger', 'stranger', 'stranger@example.com');
        $token = $this->invite('shooter@example.com');

        $this->actingAs($stranger)->get(route('team.invitation.show', $token))->assertOk()->assertSee('This invitation is for shooter@example.com');
        $this->post(route('team.invitation.accept', $token))->assertRedirect(route('team.invitation.show', $token));

        $this->assertDatabaseMissing('account_members', ['user_id' => $stranger->id]);
    }

    public function test_new_person_joins_with_google_without_choosing_a_username(): void
    {
        config(['services.google.client_id' => 'client-id', 'services.google.client_secret' => 'client-secret']);
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'token']),
            'openidconnect.googleapis.com/v1/userinfo' => Http::response([
                'sub' => 'google-999', 'email' => 'shooter@example.com', 'email_verified' => true, 'name' => 'Google Shooter',
            ]),
        ]);
        $token = $this->invite('shooter@example.com');
        auth()->logout();

        $this->get(route('team.invitation.google', $token))->assertRedirect(route('auth.google'));
        $this->withSession(['google_auth.state' => 'state-abc'])
            ->get(route('auth.google.callback', ['state' => 'state-abc', 'code' => 'code']))
            ->assertRedirect(route('studio.dashboard'));

        $member = User::where('email', 'shooter@example.com')->firstOrFail();
        $this->assertNull($member->username);
        $this->assertSame('google-999', $member->google_id);
        $this->assertTrue(AccountMember::where('account_id', $this->owner->id)->where('user_id', $member->id)->exists());
    }

    public function test_expired_revoked_and_used_tokens_are_rejected(): void
    {
        $token = $this->invite('shooter@example.com');
        $invitation = AccountInvitation::findByToken($token);
        auth()->logout();

        $invitation->update(['expires_at' => now()->subMinute()]);
        $this->get(route('team.invitation.show', $token))->assertSee('This invitation has expired');

        $invitation->update(['expires_at' => now()->addDay(), 'revoked_at' => now()]);
        $this->get(route('team.invitation.show', $token))->assertSee('cancelled by the studio owner');

        $invitation->update(['revoked_at' => null]);
        $this->post(route('team.invitation.register', $token), [
            'name' => 'Shooter', 'password' => 'secret-pass-1', 'password_confirmation' => 'secret-pass-1',
        ])->assertRedirect(route('studio.dashboard'));
        auth()->logout();

        $this->get(route('team.invitation.show', $token))->assertSee('already been used');
        $this->get(route('team.invitation.show', 'not-a-real-token'))->assertSee('not valid');
    }

    public function test_seat_limit_counts_pending_invites(): void
    {
        $this->businessPlan->update(['team_limit' => 2]);
        $this->member($this->owner, $this->user('First', null, 'first@example.com'));
        $this->invite('second@example.com');

        $this->actingAs($this->owner)
            ->post(route('studio.team.invite'), ['email' => 'third@example.com', 'role' => 'member'])
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'All your team seats are taken'));

        $this->assertDatabaseMissing('account_invitations', ['email' => 'third@example.com']);
    }

    public function test_invite_refuses_owner_existing_member_duplicates_admins_and_plans_without_teams(): void
    {
        $existing = $this->user('Existing', null, 'existing@example.com');
        $this->member($this->owner, $existing);
        $this->invite('pending@example.com');

        foreach (['owner@example.com', 'existing@example.com', 'pending@example.com', 'admin@example.com'] as $email) {
            $this->actingAs($this->owner)
                ->post(route('studio.team.invite'), ['email' => $email, 'role' => 'member'])
                ->assertSessionHas('error');
        }

        $freeOwner = $this->user('Free Owner', 'freeowner', 'free@example.com');
        $this->actingAs($freeOwner)
            ->post(route('studio.team.invite'), ['email' => 'someone@example.com', 'role' => 'member'])
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'Upgrade to Business'));

        $this->assertSame(1, AccountInvitation::count());
    }

    public function test_register_with_a_member_email_explains_how_to_create_a_studio(): void
    {
        $this->user('Member', null, 'member@example.com');

        $this->post('/register', [
            'name' => 'Member', 'username' => 'memberstudio', 'email' => 'Member@Example.com',
            'password' => 'secret-pass-1', 'password_confirmation' => 'secret-pass-1',
        ])->assertSessionHasErrors(['email' => 'You already have an account. Log in, then choose "Create my own studio" from the studio menu.']);
    }

    // ---------------------------------------------------------------- Isolation

    public function test_member_of_a_cannot_reach_studio_b_data(): void
    {
        $member = $this->user('Member', null, 'member@example.com');
        $this->member($this->owner, $member, AccountRole::Manager);

        $otherClient = Client::create(['uuid' => Uuid::uuid7()->toString(), 'user_id' => $this->otherOwner->id, 'name' => 'Other Client']);

        $this->inStudio($member, $this->owner);
        $this->get(route('studio.galleries.show', $this->otherGallery->uuid))->assertForbidden();
        $this->patch(route('studio.galleries.update', $this->otherGallery->uuid), ['title' => 'Hijack', 'visibility' => 'public'])->assertForbidden();
        $this->delete(route('studio.galleries.destroy', $this->otherGallery->uuid))->assertForbidden();
        $this->patch(route('studio.clients.update', $otherClient->uuid), ['name' => 'Hijack'])->assertNotFound();

        // Picking a studio they don't belong to falls back to their own context
        $this->withSession([StudioAccount::SESSION_KEY => $this->otherOwner->id])
            ->get(route('studio.galleries.index'))->assertDontSee('Other Wedding');

        $this->assertSame('Other Wedding', $this->otherGallery->fresh()->title);
    }

    public function test_member_of_two_studios_only_sees_the_selected_one(): void
    {
        $member = $this->user('Member', null, 'member@example.com');
        $this->member($this->owner, $member);
        $this->member($this->otherOwner, $member);

        $this->inStudio($member, $this->owner)->get(route('studio.galleries.index'))->assertSee('Owner Wedding')->assertDontSee('Other Wedding');
        $this->get(route('studio.galleries.show', $this->otherGallery->uuid))->assertForbidden();

        $this->post(route('studio.account.switch'), ['account' => $this->otherOwner->uuid])->assertRedirect(route('studio.dashboard'));
        $this->get(route('studio.galleries.index'))->assertSee('Other Wedding')->assertDontSee('Owner Wedding');
        $this->get(route('studio.galleries.show', $this->ownerGallery->uuid))->assertForbidden();
    }

    public function test_cannot_switch_to_a_studio_without_membership(): void
    {
        $stranger = $this->user('Stranger', 'stranger', 'stranger@example.com');

        $this->actingAs($stranger)->post(route('studio.account.switch'), ['account' => $this->owner->uuid])->assertSessionHas('error');
        $this->assertNull(session(StudioAccount::SESSION_KEY));
    }

    // ---------------------------------------------------------------- Roles

    public function test_member_role_limits(): void
    {
        $member = $this->user('Member', null, 'member@example.com');
        $this->member($this->owner, $member, AccountRole::Member);
        $this->inStudio($member, $this->owner);

        // Allowed
        $this->get(route('studio.dashboard'))->assertOk()->assertDontSee('Billing &amp; plans', false);
        $this->get(route('studio.bookings.index'))->assertOk();
        $this->get(route('studio.packages.index'))->assertOk();
        $this->get(route('studio.availability.index'))->assertOk();
        $this->get(route('studio.trash.index'))->assertOk();
        $this->get(route('studio.settings.index'))->assertOk()->assertSee('Leave team');
        $this->post(route('studio.galleries.store'), ['title' => 'Member Gallery', 'visibility' => 'public'])->assertRedirect();
        $this->assertDatabaseHas('galleries', ['title' => 'Member Gallery', 'user_id' => $this->owner->id]);
        $this->post(route('studio.clients.store'), ['name' => 'Member Client'])->assertRedirect();
        $this->assertDatabaseHas('clients', ['name' => 'Member Client', 'user_id' => $this->owner->id]);

        // Not allowed
        $this->get(route('studio.billing.index'))->assertForbidden();
        $this->get(route('studio.analytics.index'))->assertForbidden();
        $this->get(route('studio.portfolio.edit'))->assertForbidden();
        $this->get(route('studio.team.index'))->assertForbidden();
        $this->get(route('studio.settings.export'))->assertForbidden();
        $this->post(route('studio.settings.public-profile'), ['username' => 'stolen'])->assertForbidden();
        $this->delete(route('studio.galleries.destroy', $this->ownerGallery->uuid))->assertForbidden();
        $this->post(route('studio.clients.destroy', $this->ownerClient->uuid))->assertForbidden();
        $this->post(route('studio.packages.store'), [])->assertForbidden();
        $this->post(route('studio.availability.settings'), [])->assertForbidden();
        $this->post(route('studio.trash.empty'))->assertForbidden();

        $this->assertNotNull($this->ownerGallery->fresh());
        $this->assertSame('ownerstudio', $this->owner->fresh()->username);
    }

    public function test_manager_role_limits(): void
    {
        $manager = $this->user('Manager', null, 'manager@example.com');
        $this->member($this->owner, $manager, AccountRole::Manager);
        $this->inStudio($manager, $this->owner);

        $this->get(route('studio.analytics.index'))->assertOk();
        $this->get(route('studio.portfolio.edit'))->assertOk();
        $this->get(route('studio.team.index'))->assertOk()->assertSee('Only the studio owner can invite');
        $this->delete(route('studio.galleries.destroy', $this->ownerGallery->uuid))->assertRedirect();
        $this->assertSoftDeleted($this->ownerGallery);

        $this->get(route('studio.billing.index'))->assertForbidden();
        $this->get(route('studio.settings.export'))->assertForbidden();
        $this->post(route('studio.team.invite'), ['email' => 'x@example.com', 'role' => 'member'])->assertForbidden();
        $membership = $this->member($this->owner, $this->user('Other Member', null, 'om@example.com'));
        $this->patch(route('studio.team.role', $membership->id), ['role' => 'manager'])->assertForbidden();
        $this->delete(route('studio.team.remove', $membership->id))->assertForbidden();
    }

    public function test_member_cannot_delete_owners_account_but_member_only_user_can_delete_own_login(): void
    {
        $member = $this->user('Member', null, 'member@example.com');
        $this->member($this->owner, $member);

        $this->inStudio($member, $this->owner)
            ->delete(route('studio.settings.account.destroy'), ['delete_password' => 'password123', 'confirm' => 'DELETE'])
            ->assertRedirect(route('home'));

        $this->assertSoftDeleted($member);
        $this->assertNotSoftDeleted($this->owner);
        $this->assertDatabaseMissing('account_members', ['user_id' => $member->id]);
        $this->assertNotNull($this->ownerGallery->fresh());
    }

    public function test_owner_manages_roles_and_removes_members(): void
    {
        $member = $this->user('Member', null, 'member@example.com');
        $membership = $this->member($this->owner, $member);

        $this->actingAs($this->owner)->patch(route('studio.team.role', $membership->id), ['role' => 'manager'])->assertSessionHas('success');
        $this->assertSame(AccountRole::Manager, $membership->fresh()->role);

        $this->delete(route('studio.team.remove', $membership->id))->assertSessionHas('success');
        $this->assertDatabaseMissing('account_members', ['id' => $membership->id]);
        $this->assertNotNull($member->fresh());

        // A removed member who joined by invitation is sent to create a studio of their own
        AccountInvitation::create([
            'account_id' => $this->owner->id, 'email' => $member->email, 'role' => 'member',
            'token_hash' => str_repeat('a', 64), 'expires_at' => now()->addDay(), 'accepted_at' => now(),
        ]);
        $this->inStudio($member, $this->owner)->get(route('studio.galleries.index'))->assertRedirect(route('studio.own.create'));
    }

    // ---------------------------------------------------------------- Own studio

    public function test_member_creates_own_studio_and_both_stay_separate(): void
    {
        $member = $this->user('Member', null, 'member@example.com');
        $this->member($this->owner, $member);

        $this->actingAs($member)->get(route('studio.galleries.index'))->assertSee('Owner Wedding');
        $this->get(route('studio.own.create'))->assertOk();
        $this->post(route('studio.own.store'), ['username' => 'membersown'])->assertRedirect(route('studio.dashboard'));

        $this->assertSame('membersown', $member->fresh()->username);
        $this->post(route('studio.galleries.store'), ['title' => 'My Own Shoot', 'visibility' => 'public']);
        $this->assertDatabaseHas('galleries', ['title' => 'My Own Shoot', 'user_id' => $member->id]);
        $this->get(route('studio.galleries.index'))->assertSee('My Own Shoot')->assertDontSee('Owner Wedding');

        $this->get(route('studio.dashboard'))->assertSee('Studio Owner')->assertSee('My studio');

        $this->post(route('studio.account.switch'), ['account' => $this->owner->uuid]);
        $this->get(route('studio.galleries.index'))->assertSee('Owner Wedding')->assertDontSee('My Own Shoot');

        $this->actingAs($this->owner)->withSession([StudioAccount::SESSION_KEY => $this->owner->id])
            ->get(route('studio.galleries.index'))->assertDontSee('My Own Shoot');
    }

    public function test_member_leaves_team(): void
    {
        $member = $this->user('Member', 'memberstudio', 'member@example.com');
        $this->member($this->owner, $member);

        $this->inStudio($member, $this->owner)->post(route('studio.team.leave'))->assertRedirect(route('studio.dashboard'));
        $this->assertDatabaseMissing('account_members', ['user_id' => $member->id]);
        $this->get(route('studio.galleries.index'))->assertDontSee('Owner Wedding');
    }

    // ---------------------------------------------------------------- Plans and seats

    public function test_downgrade_suspends_newest_members_and_upgrade_restores_them(): void
    {
        Mail::fake();
        $first = $this->user('First', null, 'first@example.com');
        $second = $this->user('Second', null, 'second@example.com');
        $this->member($this->owner, $first);
        $this->travel(1)->minutes();
        $this->member($this->owner, $second);
        $this->invite('pending@example.com');

        app(RevokePlan::class)->execute($this->admin, $this->owner, 'test');

        $this->assertSame(2, AccountMember::where('account_id', $this->owner->id)->whereNotNull('suspended_at')->count());
        $this->assertSame(0, $this->owner->fresh()->teamInvitations()->pending()->count());
        Mail::assertQueued(TeamSeatsSuspendedMail::class);

        $this->inStudio($first, $this->owner)->get(route('studio.galleries.index'))->assertDontSee('Owner Wedding');

        $this->businessPlan->update(['team_limit' => 1]);
        app(AssignPlan::class)->execute($this->admin, $this->owner->fresh(), $this->businessPlan);

        $this->assertNull(AccountMember::where('user_id', $first->id)->value('suspended_at'));
        $this->assertNotNull(AccountMember::where('user_id', $second->id)->value('suspended_at'));
        $this->inStudio($first, $this->owner)->get(route('studio.galleries.index'))->assertSee('Owner Wedding');
    }

    public function test_admin_lowering_team_limit_pauses_members(): void
    {
        $this->member($this->owner, $this->user('First', null, 'first@example.com'));
        $this->member($this->owner, $this->user('Second', null, 'second@example.com'));

        $this->actingAs($this->admin)->put(route('admin.plans.update', $this->businessPlan), [
            'name' => 'Business', 'monthly_price' => 50000, 'annual_price' => 500000, 'storage_gb' => 3000,
            'unlimited_galleries' => 1, 'currency' => 'RWF', 'team_limit' => 1,
        ])->assertSessionHas('success');

        $this->assertSame(1, $this->businessPlan->fresh()->team_limit);
        $this->assertSame(1, AccountMember::where('account_id', $this->owner->id)->whereNotNull('suspended_at')->count());
    }

    public function test_member_upload_counts_against_owner_quota(): void
    {
        $member = $this->user('Member', null, 'member@example.com');
        $this->member($this->owner, $member);

        $storage = Mockery::mock(StorageService::class);
        $storage->shouldReceive('generatePresignedUploadUrl')->andReturn('https://example.com/presigned');
        $this->app->instance(StorageService::class, $storage);

        $payload = fn (string $name) => [
            'gallery_id' => $this->ownerGallery->uuid, 'filename' => $name, 'file_size' => 1024 * 1024,
            'mime_type' => 'image/jpeg', 'sha256' => hash('sha256', $name),
        ];

        $this->inStudio($member, $this->owner)->postJson(route('studio.uploads.request'), $payload('a.jpg'))->assertCreated();
        $this->assertDatabaseHas('upload_sessions', ['user_id' => $this->owner->id, 'gallery_id' => $this->ownerGallery->id]);

        $this->owner->forceFill(['storage_used_bytes' => $this->businessPlan->storage_limit])->save();
        $this->postJson(route('studio.uploads.request'), $payload('b.jpg'))->assertStatus(409)->assertJson(['code' => 'STORAGE_QUOTA_EXCEEDED']);
    }

    // ---------------------------------------------------------------- Lifecycle

    public function test_owner_deletion_ends_team_and_members_fall_back(): void
    {
        $member = $this->user('Member', 'memberstudio', 'member@example.com');
        $this->member($this->owner, $member);
        $this->invite('pending@example.com');

        $this->actingAs($this->owner)
            ->delete(route('studio.settings.account.destroy'), ['delete_password' => 'password123', 'confirm' => 'DELETE'])
            ->assertRedirect(route('home'));

        $this->assertDatabaseMissing('account_members', ['account_id' => $this->owner->id]);
        $this->assertSame(0, AccountInvitation::where('account_id', $this->owner->id)->pending()->count());
        $this->assertNotSoftDeleted($member);

        $this->inStudio($member, $this->owner)->get(route('studio.galleries.index'))->assertOk()->assertDontSee('Owner Wedding');
    }

    public function test_suspended_owner_locks_out_members(): void
    {
        $member = $this->user('Member', 'memberstudio', 'member@example.com');
        $this->member($this->owner, $member);
        $this->owner->forceFill(['is_active' => false])->save();

        $this->inStudio($member, $this->owner)->get(route('studio.galleries.index'))->assertOk()->assertDontSee('Owner Wedding');
        $this->assertNull(session(StudioAccount::SESSION_KEY));
    }

    public function test_owner_export_includes_team(): void
    {
        $this->member($this->owner, $this->user('Member', null, 'member@example.com'));

        $response = $this->actingAs($this->owner)->get(route('studio.settings.export'))->assertOk();
        $data = json_decode($response->streamedContent(), true);

        $this->assertSame('member@example.com', $data['team'][0]['email']);
    }

    public function test_leaving_impersonation_clears_the_selected_studio(): void
    {
        $member = $this->user('Member', 'memberstudio', 'member@example.com');
        $this->member($this->owner, $member);

        $this->actingAs($this->admin)->post(route('admin.users.impersonate', $member->id))->assertRedirect(route('studio.dashboard'));
        $this->post(route('studio.account.switch'), ['account' => $this->owner->uuid]);
        $this->assertSame($this->owner->id, session(StudioAccount::SESSION_KEY));

        $this->post(route('impersonation.leave'))->assertRedirect(route('admin.users.index'));
        $this->assertNull(session(StudioAccount::SESSION_KEY));
    }

    public function test_studio_pages_render_for_owner_manager_and_member(): void
    {
        $manager = $this->user('Manager', null, 'manager@example.com');
        $member = $this->user('Member', 'memberstudio', 'member@example.com');
        $this->member($this->owner, $manager, AccountRole::Manager);
        $this->member($this->owner, $member, AccountRole::Member);
        $this->invite('pending@example.com');

        $pages = [
            'studio.dashboard' => [200, 200, 200],
            'studio.galleries.index' => [200, 200, 200],
            'studio.galleries.create' => [200, 200, 200],
            'studio.bookings.index' => [200, 200, 200],
            'studio.availability.index' => [200, 200, 200],
            'studio.clients.index' => [200, 200, 200],
            'studio.packages.index' => [200, 200, 200],
            'studio.trash.index' => [200, 200, 200],
            'studio.settings.index' => [200, 200, 200],
            'studio.analytics.index' => [200, 200, 403],
            'studio.portfolio.edit' => [200, 200, 403],
            'studio.team.index' => [200, 200, 403],
            'studio.billing.index' => [200, 403, 403],
        ];

        foreach ([[$this->owner, 0], [$manager, 1], [$member, 2]] as [$user, $column]) {
            foreach ($pages as $route => $statuses) {
                $this->inStudio($user, $this->owner)->get(route($route))->assertStatus($statuses[$column]);
            }
            $this->get(route('studio.galleries.show', $this->ownerGallery->uuid))->assertOk();
            $this->get(route('studio.galleries.edit', $this->ownerGallery->uuid))->assertOk();
        }

        $this->inStudio($this->owner, $this->owner)->get(route('studio.team.index'))
            ->assertSee('Send invitation')->assertSee('pending@example.com')->assertSee('3 of 5 used')
            ->assertSee('Manager')->assertSee('member@example.com');
        $this->inStudio($member, $this->owner)->get(route('studio.dashboard'))->assertSee('Working in');
    }

    public function test_team_emails_render(): void
    {
        $token = $this->invite('shooter@example.com', 'manager');
        $invitation = AccountInvitation::findByToken($token);

        $html = (new TeamInvitationMail($invitation, $this->owner, $this->owner, route('team.invitation.show', $token)))->render();
        $this->assertStringContainsString('Join Studio Owner', $html);
        $this->assertStringContainsString(route('team.invitation.show', $token), $html);

        $html = (new TeamSeatsSuspendedMail($this->owner, ['First Member']))->render();
        $this->assertStringContainsString('First Member', $html);
    }

    public function test_bookings_status_is_scoped_to_selected_studio(): void
    {
        $member = $this->user('Member', null, 'member@example.com');
        $this->member($this->owner, $member);

        $booking = Booking::create([
            'uuid' => Uuid::uuid7()->toString(),
            'user_id' => $this->owner->id,
            'client_id' => $this->ownerClient->id,
            'title' => 'Shoot',
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDay()->addHour(),
            'status' => 'pending',
        ]);

        $this->inStudio($member, $this->owner)->patch(route('studio.bookings.status', $booking->uuid), ['status' => 'confirmed'])->assertSessionHas('success');
        $this->assertSame('confirmed', $booking->fresh()->status->value);
    }
}
