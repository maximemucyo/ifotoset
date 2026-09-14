<?php

namespace Tests\Feature;

use App\Models\AvailabilityException;
use App\Models\BlockedSlot;
use App\Models\Client;
use App\Models\Gallery;
use App\Models\Package;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Ramsey\Uuid\Uuid;
use Tests\TestCase;

class StudioIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected User $userA;
    protected User $userB;
    protected Client $clientA;
    protected Package $packageA;
    protected Gallery $galleryA;

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

        $this->userA = User::create([
            'uuid' => Uuid::uuid7()->toString(),
            'plan_id' => $plan->id,
            'name' => 'Studio A Kigali',
            'username' => 'studioa',
            'email' => 'studioa@example.com',
            'password' => Hash::make('password123'),
        ]);

        $this->userB = User::create([
            'uuid' => Uuid::uuid7()->toString(),
            'plan_id' => $plan->id,
            'name' => 'Studio B Gisenyi',
            'username' => 'studiob',
            'email' => 'studiob@example.com',
            'password' => Hash::make('password123'),
        ]);

        $this->clientA = Client::create([
            'uuid' => Uuid::uuid7()->toString(),
            'user_id' => $this->userA->id,
            'name' => 'Rwanda Development Board',
            'email' => 'contact@rdb.rw',
            'phone' => '+250 788 123 456',
        ]);

        $this->packageA = Package::create([
            'uuid' => Uuid::uuid7()->toString(),
            'user_id' => $this->userA->id,
            'name' => 'Kigali Wedding Package',
            'price' => 250000,
            'currency' => 'RWF',
            'duration_minutes' => 120,
            'deliverables' => ['50 Edited Photos', 'Online Gallery'],
            'deposit_type' => 'percentage',
            'deposit_amount' => 20,
            'is_active' => true,
        ]);

        $this->galleryA = Gallery::create([
            'uuid' => Uuid::uuid7()->toString(),
            'user_id' => $this->userA->id,
            'title' => 'Kwita Izina Ceremony 2026',
            'slug' => 'kwita-izina-ceremony-2026',
            'visibility' => 'private',
        ]);
    }

    public function test_user_b_cannot_view_user_a_clients(): void
    {
        $response = $this->actingAs($this->userB)->get(route('studio.clients.index'));

        $response->assertStatus(200);
        $response->assertDontSee('Rwanda Development Board');
        $response->assertDontSee('contact@rdb.rw');
    }

    public function test_user_b_cannot_update_user_a_client(): void
    {
        $response = $this->actingAs($this->userB)->patch(route('studio.clients.update', $this->clientA->uuid), [
            'name' => 'Hijacked Client Name',
        ]);

        $response->assertStatus(404);
        $this->assertEquals('Rwanda Development Board', $this->clientA->fresh()->name);
    }

    public function test_user_b_cannot_delete_user_a_client(): void
    {
        $response = $this->actingAs($this->userB)->post(route('studio.clients.destroy', $this->clientA->uuid));

        $response->assertStatus(404);
        $this->assertDatabaseHas('clients', ['id' => $this->clientA->id]);
    }

    public function test_user_b_cannot_view_user_a_packages(): void
    {
        $response = $this->actingAs($this->userB)->get(route('studio.packages.index'));

        $response->assertStatus(200);
        $response->assertDontSee('Kigali Wedding Package');
    }

    public function test_user_b_cannot_update_or_delete_user_a_package(): void
    {
        $updateResponse = $this->actingAs($this->userB)->patch(route('studio.packages.update', $this->packageA->uuid), [
            'name' => 'Tampered Name',
            'price' => 10,
            'currency' => 'RWF',
            'duration_minutes' => 60,
            'deposit_type' => 'none',
        ]);

        $updateResponse->assertStatus(404);
        $this->assertEquals('Kigali Wedding Package', $this->packageA->fresh()->name);

        $deleteResponse = $this->actingAs($this->userB)->post(route('studio.packages.destroy', $this->packageA->uuid));
        $deleteResponse->assertStatus(404);
    }

    public function test_user_b_cannot_delete_user_a_availability_exceptions(): void
    {
        $exception = AvailabilityException::create([
            'uuid' => Uuid::uuid7()->toString(),
            'user_id' => $this->userA->id,
            'date' => '2026-10-01',
            'is_closed' => true,
        ]);

        $response = $this->actingAs($this->userB)->post(route('studio.availability.exceptions.destroy', $exception->uuid));

        $response->assertStatus(404);
        $this->assertDatabaseHas('availability_exceptions', ['id' => $exception->id]);
    }

    public function test_user_b_cannot_delete_user_a_blocked_slots(): void
    {
        $blocked = BlockedSlot::create([
            'uuid' => Uuid::uuid7()->toString(),
            'user_id' => $this->userA->id,
            'starts_at' => now()->addDays(2),
            'ends_at' => now()->addDays(2)->addHours(3),
            'reason' => 'Private shoot at Kigali Convention Centre',
        ]);

        $response = $this->actingAs($this->userB)->post(route('studio.availability.blocked.destroy', $blocked->uuid));

        $response->assertStatus(404);
        $this->assertDatabaseHas('blocked_slots', ['id' => $blocked->id]);
    }

    public function test_user_b_cannot_access_user_a_private_gallery_in_studio(): void
    {
        $response = $this->actingAs($this->userB)->get(route('studio.galleries.show', $this->galleryA->uuid));

        $response->assertStatus(403);
    }
}
