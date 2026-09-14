<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Ramsey\Uuid\Uuid;
use Tests\TestCase;

class RouteSmokeTest extends TestCase
{
    use RefreshDatabase;

    protected User $photographer;
    protected User $admin;

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

        $this->photographer = User::create([
            'uuid' => Uuid::uuid7()->toString(),
            'plan_id' => $plan->id,
            'name' => 'Photographer Kigali',
            'username' => 'kigaliphoto',
            'email' => 'photo@kigali.rw',
            'password' => Hash::make('secret123'),
            'role' => 'user',
        ]);

        $this->admin = User::create([
            'uuid' => Uuid::uuid7()->toString(),
            'plan_id' => $plan->id,
            'name' => 'Admin User',
            'username' => 'adminuser',
            'email' => 'admin@kigali.rw',
            'password' => Hash::make('secret123'),
        ]);
        $this->admin->role = 'admin';
        $this->admin->save();
    }

    public function test_all_studio_routes_render_cleanly_for_authenticated_photographer(): void
    {
        $studioRoutes = [
            'studio.dashboard',
            'studio.galleries.index',
            'studio.galleries.create',
            'studio.bookings.index',
            'studio.availability.index',
            'studio.clients.index',
            'studio.packages.index',
            'studio.analytics.index',
            'studio.settings.index',
            'studio.trash.index',
        ];

        foreach ($studioRoutes as $routeName) {
            $response = $this->actingAs($this->photographer)->get(route($routeName));
            $response->assertStatus(200);
        }
    }

    public function test_all_admin_routes_render_cleanly_for_authenticated_admin(): void
    {
        $adminRoutes = [
            'admin.dashboard',
            'admin.users.index',
            'admin.galleries.index',
            'admin.payments.index',
            'admin.analytics.index',
            'admin.moderation.index',
            'admin.support.index',
            'admin.settings.index',
        ];

        foreach ($adminRoutes as $routeName) {
            $response = $this->actingAs($this->admin)->get(route($routeName));
            $response->assertStatus(200);
        }
    }
}
