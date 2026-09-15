<?php

namespace Tests\Feature;

use App\Models\AdminAuditLog;
use App\Models\Plan;
use App\Models\User;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Ramsey\Uuid\Uuid;
use Tests\TestCase;

class AdminPlanManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $user;
    protected Plan $basicPlan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PlanSeeder::class);

        $this->basicPlan = Plan::where('slug', 'basic')->firstOrFail();

        $this->user = User::create([
            'uuid' => Uuid::uuid7()->toString(),
            'plan_id' => Plan::where('slug', 'free')->first()->id,
            'name' => 'Regular User',
            'username' => 'regularuser',
            'email' => 'user@example.com',
            'password' => Hash::make('secret123'),
            'is_active' => true,
        ]);

        $this->admin = User::create([
            'uuid' => Uuid::uuid7()->toString(),
            'plan_id' => $this->basicPlan->id,
            'name' => 'Admin User',
            'username' => 'adminboss',
            'email' => 'admin@example.com',
            'password' => Hash::make('secret123'),
            'is_active' => true,
        ]);
        $this->admin->role = 'admin';
        $this->admin->save();
    }

    public function test_non_admin_cannot_access_or_update_plans(): void
    {
        $response = $this->actingAs($this->user)->get('/admin/plans');
        $response->assertRedirect(route('studio.dashboard'));

        $updateResponse = $this->actingAs($this->user)->put(route('admin.plans.update', 'basic'), [
            'name' => 'Hacked Basic',
            'monthly_price' => 100,
            'annual_price' => 1000,
            'storage_gb' => 100,
            'currency' => 'RWF',
        ]);
        $updateResponse->assertRedirect(route('studio.dashboard'));
    }

    public function test_admin_can_view_plans_index(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.plans.index'));

        $response->assertStatus(200);
        $response->assertSee('Payment Plans &amp; Storage Quotas', false);
        $response->assertSee('Basic');
        $response->assertSee('Professional');
        $response->assertSee('Business');
        $response->assertSee('Free');
    }

    public function test_admin_can_update_plan_pricing_and_quotas(): void
    {
        $response = $this->actingAs($this->admin)->put(route('admin.plans.update', $this->basicPlan->slug), [
            'name' => 'Basic Starter',
            'monthly_price' => 4500,
            'annual_price' => 45000,
            'storage_gb' => 75,
            'currency' => 'RWF',
            'unlimited_galleries' => '1',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->basicPlan->refresh();

        $this->assertEquals('Basic Starter', $this->basicPlan->name);
        $this->assertEquals(4500, (float) $this->basicPlan->monthly_price);
        $this->assertEquals(45000, (float) $this->basicPlan->annual_price);
        $this->assertEquals(75 * 1000000000, $this->basicPlan->storage_limit);
        $this->assertNull($this->basicPlan->gallery_limit);

        // Verify audit log entry was created
        $this->assertDatabaseHas('admin_audit_logs', [
            'action' => 'plan.pricing_updated',
            'admin_user_id' => $this->admin->id,
            'target_type' => 'Plan',
            'target_id' => (string) $this->basicPlan->id,
        ]);
    }

    public function test_admin_can_set_custom_gallery_cap(): void
    {
        $response = $this->actingAs($this->admin)->put(route('admin.plans.update', $this->basicPlan->slug), [
            'name' => 'Basic Starter',
            'monthly_price' => 5000,
            'annual_price' => 50000,
            'storage_gb' => 50,
            'currency' => 'RWF',
            'unlimited_galleries' => '0',
            'gallery_limit' => 25,
        ]);

        $response->assertRedirect();
        $this->basicPlan->refresh();

        $this->assertEquals(25, $this->basicPlan->gallery_limit);
    }
}
