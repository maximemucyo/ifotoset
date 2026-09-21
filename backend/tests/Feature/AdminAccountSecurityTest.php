<?php

namespace Tests\Feature;

use App\Mail\AdminEmailVerificationCodeMail;
use App\Models\AdminAuditLog;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Ramsey\Uuid\Uuid;
use Tests\TestCase;

class AdminAccountSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected Plan $plan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->plan = Plan::firstOrCreate([
            'slug' => 'admin-plan',
        ], [
            'uuid' => Uuid::uuid7()->toString(),
            'name' => 'Admin Plan',
            'storage_limit' => 100 * 1024 * 1024 * 1024,
            'video_limit' => 0,
            'gallery_limit' => 50,
            'team_limit' => 5,
        ]);

        $this->admin = User::create([
            'uuid' => Uuid::uuid7()->toString(),
            'plan_id' => $this->plan->id,
            'name' => 'Super Admin',
            'username' => 'superadmin',
            'email' => 'admin@ifotoset.com',
            'password' => Hash::make('CurrentSecret123!'),
            'role' => 'admin',
            'email_verified_at' => now(),
            'is_active' => true,
        ]);
    }

    public function test_password_update_requires_valid_current_password(): void
    {
        $response = $this->actingAs($this->admin)->put('/admin/settings/password', [
            'current_password' => 'WrongSecret999!',
            'password' => 'NewStrongSecret456!',
            'password_confirmation' => 'NewStrongSecret456!',
        ]);

        $response->assertSessionHasErrors('current_password');
        $this->assertTrue(Hash::check('CurrentSecret123!', $this->admin->fresh()->password));
    }

    public function test_password_update_succeeds_and_records_audit_log(): void
    {
        $response = $this->actingAs($this->admin)->put('/admin/settings/password', [
            'current_password' => 'CurrentSecret123!',
            'password' => 'NewStrongSecret456!',
            'password_confirmation' => 'NewStrongSecret456!',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();
        $this->assertTrue(Hash::check('NewStrongSecret456!', $this->admin->fresh()->password));

        $this->assertDatabaseHas('admin_audit_logs', [
            'admin_user_id' => $this->admin->id,
            'action' => 'admin.password_updated',
        ]);
    }

    public function test_request_email_verification_code_requires_current_password(): void
    {
        $response = $this->actingAs($this->admin)->post('/admin/settings/email/request-code', [
            'current_password' => 'WrongPassword!',
            'new_email' => 'newadmin@ifotoset.com',
        ]);

        $response->assertSessionHasErrors('current_password');
    }

    public function test_request_email_verification_code_sends_email_with_hmac_cached(): void
    {
        Mail::fake();

        $response = $this->actingAs($this->admin)->post('/admin/settings/email/request-code', [
            'current_password' => 'CurrentSecret123!',
            'new_email' => 'NewAdmin@iFotoSet.com', // test normalization
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertSessionHas('email_otp_sent', true);
        $response->assertSessionHas('target_new_email', 'newadmin@ifotoset.com');

        Mail::assertQueued(AdminEmailVerificationCodeMail::class, function ($mail) {
            return $mail->hasTo('newadmin@ifotoset.com') && strlen($mail->code) === 6;
        });

        $cached = Cache::get("admin_email_change:{$this->admin->id}");
        $this->assertNotNull($cached);
        $this->assertEquals('newadmin@ifotoset.com', $cached['new_email']);
        $this->assertNotNull($cached['otp_hash']);
    }

    public function test_request_email_code_is_rate_limited(): void
    {
        Mail::fake();

        // 1st request succeeds
        $res1 = $this->actingAs($this->admin)->post('/admin/settings/email/request-code', [
            'current_password' => 'CurrentSecret123!',
            'new_email' => 'newadmin@ifotoset.com',
        ]);
        $res1->assertSessionHasNoErrors();

        // 2nd request immediately triggers 60-second cooldown
        $res2 = $this->actingAs($this->admin)->post('/admin/settings/email/request-code', [
            'current_password' => 'CurrentSecret123!',
            'new_email' => 'newadmin2@ifotoset.com',
        ]);
        $res2->assertSessionHasErrors('new_email');
    }

    public function test_verify_email_rejects_invalid_code_and_limits_attempts(): void
    {
        $code = '123456';
        Cache::put("admin_email_change:{$this->admin->id}", [
            'new_email' => 'updated@ifotoset.com',
            'otp_hash' => hash_hmac('sha256', $code, config('app.key')),
            'attempts' => 0,
        ], now()->addMinutes(15));

        // Submit wrong code
        $res = $this->actingAs($this->admin)->put('/admin/settings/email/verify', [
            'code' => '999999',
        ]);
        $res->assertSessionHasErrors('code');

        $cached = Cache::get("admin_email_change:{$this->admin->id}");
        $this->assertEquals(1, $cached['attempts']);
        $this->assertEquals('admin@ifotoset.com', $this->admin->fresh()->email);
    }

    public function test_verify_email_succeeds_and_updates_email_atomically_with_audit_log(): void
    {
        $code = '654321';
        Cache::put("admin_email_change:{$this->admin->id}", [
            'new_email' => 'finaladmin@ifotoset.com',
            'otp_hash' => hash_hmac('sha256', $code, config('app.key')),
            'attempts' => 0,
        ], now()->addMinutes(15));

        $res = $this->actingAs($this->admin)->put('/admin/settings/email/verify', [
            'code' => '654321',
        ]);
        $res->assertSessionHasNoErrors();

        $this->assertEquals('finaladmin@ifotoset.com', $this->admin->fresh()->email);
        $this->assertNull(Cache::get("admin_email_change:{$this->admin->id}"));

        $this->assertDatabaseHas('admin_audit_logs', [
            'admin_user_id' => $this->admin->id,
            'action' => 'admin.email_updated',
        ]);
    }
}
