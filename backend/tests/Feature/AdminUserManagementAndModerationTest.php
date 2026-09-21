<?php

namespace Tests\Feature;

use App\Models\AdminAuditLog;
use App\Models\AdminNote;
use App\Models\Gallery;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Ramsey\Uuid\Uuid;
use Tests\TestCase;

class AdminUserManagementAndModerationTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $photographer;
    protected Plan $plan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->plan = Plan::firstOrCreate([
            'slug' => 'starter',
        ], [
            'uuid' => Uuid::uuid7()->toString(),
            'name' => 'Starter',
            'storage_limit' => 2 * 1024 * 1024 * 1024,
            'video_limit' => 0,
            'gallery_limit' => 10,
            'team_limit' => 1,
        ]);

        $this->admin = User::create([
            'uuid' => Uuid::uuid7()->toString(),
            'plan_id' => $this->plan->id,
            'name' => 'Super Admin',
            'username' => 'superadmin',
            'email' => 'admin@ifotoset.com',
            'password' => Hash::make('AdminSecret123!'),
            'role' => 'admin',
            'email_verified_at' => now(),
            'is_active' => true,
        ]);

        $this->photographer = User::create([
            'uuid' => Uuid::uuid7()->toString(),
            'plan_id' => $this->plan->id,
            'name' => 'Jane Photographer',
            'username' => 'janephoto',
            'email' => 'jane@example.com',
            'password' => Hash::make('UserSecret123!'),
            'role' => 'photographer',
            'email_verified_at' => null,
            'is_active' => true,
        ]);
    }

    public function test_admin_can_view_user_directory_with_verification_filter(): void
    {
        $response = $this->actingAs($this->admin)->get('/admin/users?verified=unverified');
        $response->assertOk();
        $response->assertSee('Jane Photographer');
        $response->assertSee('Unverified');
    }

    public function test_admin_can_fetch_user_details_json_for_drawer(): void
    {
        $gallery = Gallery::create([
            'uuid' => Uuid::uuid7()->toString(),
            'user_id' => $this->photographer->id,
            'title' => 'Jane Summer Wedding',
            'slug' => 'jane-summer-wedding',
            'visibility' => 'private',
        ]);

        DB::table('activity_logs')->insert([
            'user_id' => $this->photographer->id,
            'gallery_id' => $gallery->id,
            'event' => 'gallery_created',
            'ip_address' => '192.168.1.100',
            'created_at' => now(),
        ]);

        $response = $this->actingAs($this->admin)->getJson("/admin/users/{$this->photographer->id}/details");
        $response->assertOk();
        $response->assertJsonPath('user.name', 'Jane Photographer');
        $response->assertJsonPath('user.is_email_verified', false);
        $response->assertJsonCount(1, 'galleries');
        $response->assertJsonPath('galleries.0.title', 'Jane Summer Wedding');
        $response->assertJsonCount(1, 'activities');
        $response->assertJsonPath('activities.0.ip_masked', '192.168.***.***');
    }

    public function test_email_verification_actions_and_audit(): void
    {
        // 1. Verify email
        $verifyRes = $this->actingAs($this->admin)->postJson("/admin/users/{$this->photographer->id}/email/verify");
        $verifyRes->assertOk();
        $this->photographer->refresh();
        $this->assertNotNull($this->photographer->email_verified_at);
        $this->assertDatabaseHas('admin_audit_logs', [
            'action' => 'user.email_verified',
            'target_type' => 'User',
            'target_id' => (string) $this->photographer->id,
        ]);

        // 2. Unverify email
        $unverifyRes = $this->actingAs($this->admin)->postJson("/admin/users/{$this->photographer->id}/email/unverify");
        $unverifyRes->assertOk();
        $this->photographer->refresh();
        $this->assertNull($this->photographer->email_verified_at);
        $this->assertDatabaseHas('admin_audit_logs', [
            'action' => 'user.email_unverified',
            'target_type' => 'User',
            'target_id' => (string) $this->photographer->id,
        ]);
    }

    public function test_admin_can_add_administrative_notes(): void
    {
        $response = $this->actingAs($this->admin)->postJson("/admin/users/{$this->photographer->id}/notes", [
            'content' => 'Customer requested help with domain verification.',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('admin_notes', [
            'user_id' => $this->photographer->id,
            'admin_user_id' => $this->admin->id,
            'content' => 'Customer requested help with domain verification.',
        ]);
        $this->assertDatabaseHas('admin_audit_logs', [
            'action' => 'admin_note.added',
            'target_type' => 'User',
            'target_id' => (string) $this->photographer->id,
        ]);
    }

    public function test_admin_cannot_delete_self_or_last_admin(): void
    {
        // Cannot delete self
        $selfRes = $this->actingAs($this->admin)->delete("/admin/users/{$this->admin->id}");
        $selfRes->assertSessionHas('error', 'You cannot delete your own administrator account.');
        $this->assertFalse($this->admin->fresh()->trashed());

        // Can soft-delete regular user
        $userRes = $this->actingAs($this->admin)->delete("/admin/users/{$this->photographer->id}");
        $userRes->assertSessionHas('success');
        $this->assertTrue($this->photographer->fresh()->trashed());
    }

    public function test_impersonation_lifecycle_and_security_restrictions(): void
    {
        // 1. Cannot impersonate another admin
        $otherAdmin = User::create([
            'uuid' => Uuid::uuid7()->toString(),
            'plan_id' => $this->plan->id,
            'name' => 'Other Admin',
            'username' => 'otheradmin',
            'email' => 'otheradmin@ifotoset.com',
            'password' => Hash::make('Secret123!'),
            'role' => 'admin',
            'is_active' => true,
        ]);
        $adminRes = $this->actingAs($this->admin)->post("/admin/users/{$otherAdmin->id}/impersonate");
        $adminRes->assertSessionHas('error', 'You cannot impersonate another platform administrator.');

        // 2. Start impersonating regular photographer
        $startRes = $this->actingAs($this->admin)->post("/admin/users/{$this->photographer->id}/impersonate");
        $startRes->assertRedirect(route('studio.dashboard'));
        $this->assertEquals($this->photographer->id, auth()->id());
        $this->assertTrue(session()->has('impersonation'));
        $this->assertEquals($this->admin->id, session('impersonation.admin_id'));
        $this->assertNotEmpty(session('impersonation.nonce'));

        // 3. While impersonating, accessing /admin/* is blocked by PreventAdminDuringImpersonation
        $blockedRes = $this->get('/admin/users');
        $blockedRes->assertRedirect(route('studio.dashboard'));

        // 4. Leave impersonation
        $leaveRes = $this->post('/auth/impersonation/leave');
        $leaveRes->assertRedirect(route('admin.users.index'));
        $this->assertEquals($this->admin->id, auth()->id());
        $this->assertFalse(session()->has('impersonation'));
    }

    public function test_gallery_moderation_preview_bypasses_privacy(): void
    {
        $gallery = Gallery::create([
            'uuid' => Uuid::uuid7()->toString(),
            'user_id' => $this->photographer->id,
            'title' => 'Secret VIP Portraits',
            'slug' => 'secret-vip-portraits',
            'visibility' => 'private',
            'password_hash' => Hash::make('1234'),
        ]);

        // Explicit admin preview route bypasses privacy and renders moderation mode
        $previewRes = $this->actingAs($this->admin)->get("/admin/galleries/{$gallery->uuid}/preview");
        $previewRes->assertOk();
        $previewRes->assertSee('Admin Moderation Preview');
        $previewRes->assertSee('Secret VIP Portraits');
    }

    public function test_gallery_takedown_and_restore_workflow(): void
    {
        $gallery = Gallery::create([
            'uuid' => Uuid::uuid7()->toString(),
            'user_id' => $this->photographer->id,
            'title' => 'Client Event Photos',
            'slug' => 'client-event-photos',
            'visibility' => 'public',
            'moderation_status' => 'normal',
        ]);

        // 1. Take down gallery with reason
        $takedownRes = $this->actingAs($this->admin)->post("/admin/galleries/{$gallery->uuid}/takedown", [
            'reason' => 'DMCA copyright takedown request.',
        ]);
        $takedownRes->assertSessionHas('success');

        $gallery->refresh();
        $this->assertEquals('taken_down', $gallery->moderation_status);
        $this->assertEquals('public', $gallery->visibility); // original visibility preserved!
        $this->assertEquals('DMCA copyright takedown request.', $gallery->takedown_reason);
        $this->assertEquals($this->admin->id, $gallery->taken_down_by);

        // 2. Public gallery view shows unavailable screen
        $galleryUrl = app(\App\Services\PublicUrlService::class)->galleryUrl($this->photographer->username, $gallery->slug);
        $publicRes = $this->get($galleryUrl);
        $publicRes->assertSee('Gallery Unavailable');
        $publicRes->assertSee('This gallery is currently unavailable due to content moderation.');

        // 3. Restore gallery
        $restoreRes = $this->actingAs($this->admin)->post("/admin/galleries/{$gallery->uuid}/restore");
        $restoreRes->assertSessionHas('success');

        $gallery->refresh();
        $this->assertEquals('normal', $gallery->moderation_status);
        $this->assertEquals('public', $gallery->visibility); // original visibility still public!
    }

    public function test_admin_can_reveal_audit_ip(): void
    {
        $audit = AdminAuditLog::record(
            $this->admin,
            'user.viewed',
            'User',
            (string) $this->photographer->id
        );

        $response = $this->actingAs($this->admin)->postJson("/admin/audit-logs/{$audit->id}/reveal-ip");
        $response->assertOk();
        $this->assertNotEmpty($response->json('ip'));
        $this->assertDatabaseHas('admin_audit_logs', [
            'action' => 'admin.ip_revealed',
            'target_type' => 'AdminAuditLog',
            'target_id' => (string) $audit->id,
        ]);
    }
}
