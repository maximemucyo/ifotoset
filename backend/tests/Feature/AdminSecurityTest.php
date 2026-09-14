<?php

namespace Tests\Feature;

use App\Actions\Admin\ChangeUserRole;
use App\Actions\Admin\ToggleUserStatus;
use App\Models\AdminAuditLog;
use App\Models\Plan;
use App\Models\User;
use BadMethodCallException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Ramsey\Uuid\Uuid;
use Tests\TestCase;

class AdminSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected User $admin;
    protected User $secondAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $plan = Plan::firstOrCreate(['slug' => 'free'], [
            'uuid' => Uuid::uuid7()->toString(),
            'name' => 'Free Plan',
            'storage_limit' => 5 * 1024 * 1024 * 1024,
            'video_limit' => 0,
            'gallery_limit' => 5,
            'team_limit' => 0,
        ]);

        $this->user = User::create([
            'uuid' => Uuid::uuid7()->toString(),
            'plan_id' => $plan->id,
            'name' => 'Jean Luc',
            'username' => 'jeanluc',
            'email' => 'jean@example.com',
            'password' => Hash::make('secret123'),
            'is_active' => true,
        ]);

        $this->admin = User::create([
            'uuid' => Uuid::uuid7()->toString(),
            'plan_id' => $plan->id,
            'name' => 'Principal Admin',
            'username' => 'principaladmin',
            'email' => 'admin@example.com',
            'password' => Hash::make('secret123'),
            'is_active' => true,
        ]);
        $this->admin->role = 'admin';
        $this->admin->save();

        $this->secondAdmin = User::create([
            'uuid' => Uuid::uuid7()->toString(),
            'plan_id' => $plan->id,
            'name' => 'Second Admin',
            'username' => 'secondadmin',
            'email' => 'admin2@example.com',
            'password' => Hash::make('secret123'),
            'is_active' => true,
        ]);
        $this->secondAdmin->role = 'admin';
        $this->secondAdmin->save();
    }

    public function test_guest_is_redirected_to_login_when_accessing_admin_portal(): void
    {
        $response = $this->get('/admin/dashboard');

        $response->assertRedirect(route('login'));
    }

    public function test_non_admin_user_is_redirected_to_studio_dashboard_with_error(): void
    {
        $response = $this->actingAs($this->user)->get('/admin/dashboard');

        $response->assertRedirect(route('studio.dashboard'));
        $response->assertSessionHas('toast');
    }

    public function test_admin_user_can_access_admin_dashboard(): void
    {
        $response = $this->actingAs($this->admin)->get('/admin/dashboard');

        $response->assertStatus(200);
        $response->assertSee('Platform Overview');
    }

    public function test_admin_cannot_demote_themselves(): void
    {
        $action = app(ChangeUserRole::class);

        $this->expectException(ValidationException::class);
        $action->execute($this->admin, $this->admin, 'user');
    }

    public function test_admin_cannot_suspend_themselves(): void
    {
        $action = app(ToggleUserStatus::class);

        $this->expectException(ValidationException::class);
        $action->execute($this->admin, $this->admin);
    }

    public function test_last_active_admin_protection_prevents_demotion(): void
    {
        // First, demote secondAdmin to make principalAdmin the sole active admin
        $action = app(ChangeUserRole::class);
        $action->execute($this->admin, $this->secondAdmin, 'user');

        $this->assertEquals('user', $this->secondAdmin->fresh()->role);

        // Now attempt to demote the last remaining admin
        $this->expectException(ValidationException::class);
        $action->execute($this->secondAdmin, $this->admin, 'user');
    }

    public function test_last_active_admin_protection_prevents_suspension(): void
    {
        $roleAction = app(ChangeUserRole::class);
        $statusAction = app(ToggleUserStatus::class);

        // Demote secondAdmin
        $roleAction->execute($this->admin, $this->secondAdmin, 'user');

        // Now attempt to suspend the sole active admin
        $this->expectException(ValidationException::class);
        $statusAction->execute($this->secondAdmin, $this->admin);
    }

    public function test_admin_audit_logs_are_append_only(): void
    {
        $log = AdminAuditLog::record(
            $this->admin,
            'test.action',
            'User',
            (string) $this->user->id,
            ['sample' => 'data']
        );

        $this->assertDatabaseHas('admin_audit_logs', [
            'id' => $log->id,
            'action' => 'test.action',
            'admin_user_id' => $this->admin->id,
        ]);

        $this->expectException(BadMethodCallException::class);
        $log->update(['action' => 'tampered.action']);
    }

    public function test_admin_audit_logs_cannot_be_deleted(): void
    {
        $log = AdminAuditLog::record(
            $this->admin,
            'test.action',
            'User',
            (string) $this->user->id,
            ['sample' => 'data']
        );

        $this->expectException(BadMethodCallException::class);
        $log->delete();
    }
}
