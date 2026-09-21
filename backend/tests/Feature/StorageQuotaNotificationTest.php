<?php

namespace Tests\Feature;

use App\Events\StorageRecalculatedEvent;
use App\Jobs\StorageQuotaAlertJob;
use App\Mail\StorageQuotaAlertMail;
use App\Models\Plan;
use App\Models\StorageQuotaNotification;
use App\Models\User;
use App\Services\StorageQuotaNotifierService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Ramsey\Uuid\Uuid;
use Tests\TestCase;

class StorageQuotaNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Plan $plan;
    protected StorageQuotaNotifierService $notifier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->plan = Plan::firstOrCreate([
            'slug' => 'quota-test-plan',
        ], [
            'uuid' => Uuid::uuid7()->toString(),
            'name' => 'Quota Test Plan',
            'storage_limit' => 100 * 1024 * 1024, // 100 MB
            'video_limit' => 0,
            'gallery_limit' => 5,
            'team_limit' => 0,
        ]);

        $this->user = User::create([
            'uuid' => Uuid::uuid7()->toString(),
            'plan_id' => $this->plan->id,
            'name' => 'Notifier Tester',
            'username' => 'notifiertest',
            'email' => 'notifier@example.com',
            'password' => Hash::make('password123'),
            'role' => 'photographer',
            'email_verified_at' => now(),
            'storage_used_bytes' => 50 * 1024 * 1024, // 50%
            'storage_warning_75_active' => false,
            'storage_warning_100_active' => false,
            'storage_warning_generation' => 1,
        ]);

        $this->notifier = app(StorageQuotaNotifierService::class);
    }

    public function test_75_percent_notification_occurs_only_on_threshold_crossing(): void
    {
        Queue::fake();

        // 1. Cross to 76 MB (76%)
        $this->user->update(['storage_used_bytes' => 76 * 1024 * 1024]);
        $this->notifier->evaluateUsage($this->user->id);

        Queue::assertPushed(StorageQuotaAlertJob::class, function ($job) {
            return $job->userId === $this->user->id && $job->threshold === 75;
        });

        $this->assertTrue($this->user->fresh()->storage_warning_75_active);
        $this->assertFalse($this->user->fresh()->storage_warning_100_active);

        // 2. Further increase to 85 MB (85%) -> should NOT dispatch a duplicate 75% job
        Queue::fake();
        $this->user->update(['storage_used_bytes' => 85 * 1024 * 1024]);
        $this->notifier->evaluateUsage($this->user->id);

        Queue::assertNothingPushed();
    }

    public function test_100_percent_notification_occurs_only_on_threshold_crossing(): void
    {
        Queue::fake();

        // Cross to 100 MB (100%)
        $this->user->update(['storage_used_bytes' => 100 * 1024 * 1024]);
        $this->notifier->evaluateUsage($this->user->id);

        Queue::assertPushed(StorageQuotaAlertJob::class, function ($job) {
            return $job->userId === $this->user->id && $job->threshold === 100;
        });

        $this->assertTrue($this->user->fresh()->storage_warning_100_active);
        $this->assertTrue($this->user->fresh()->storage_warning_75_active);

        // Subsequence check while still >= 100% does not dispatch again
        Queue::fake();
        $this->user->update(['storage_used_bytes' => 105 * 1024 * 1024]);
        $this->notifier->evaluateUsage($this->user->id);

        Queue::assertNothingPushed();
    }

    public function test_drop_from_100_to_80_allows_future_100_percent_notification(): void
    {
        // 1. Start at 100% with warning flags set
        $this->user->update([
            'storage_used_bytes' => 100 * 1024 * 1024,
            'storage_warning_75_active' => true,
            'storage_warning_100_active' => true,
        ]);

        // 2. User deletes some photos -> drops to 82 MB (82%)
        $this->user->update(['storage_used_bytes' => 82 * 1024 * 1024]);
        $this->notifier->evaluateUsage($this->user->id);

        // 100% active flag must be reset to false, 75% flag stays true
        $this->assertFalse($this->user->fresh()->storage_warning_100_active);
        $this->assertTrue($this->user->fresh()->storage_warning_75_active);

        // 3. User uploads again and hits 100% -> Dispatches 100% alert again!
        Queue::fake();
        $this->user->update(['storage_used_bytes' => 100 * 1024 * 1024]);
        $this->notifier->evaluateUsage($this->user->id);

        Queue::assertPushed(StorageQuotaAlertJob::class, function ($job) {
            return $job->userId === $this->user->id && $job->threshold === 100;
        });
        $this->assertTrue($this->user->fresh()->storage_warning_100_active);
    }

    public function test_drop_below_75_allows_future_75_percent_notification(): void
    {
        // 1. Start at 80% with 75% flag active
        $this->user->update([
            'storage_used_bytes' => 80 * 1024 * 1024,
            'storage_warning_75_active' => true,
            'storage_warning_generation' => 1,
        ]);

        // 2. Empties trash -> drops to 50 MB
        $this->user->update(['storage_used_bytes' => 50 * 1024 * 1024]);
        $this->notifier->evaluateUsage($this->user->id);

        $fresh = $this->user->fresh();
        $this->assertFalse($fresh->storage_warning_75_active);
        $this->assertFalse($fresh->storage_warning_100_active);
        $this->assertEquals(2, $fresh->storage_warning_generation);

        // 3. Rises back to 78 MB -> Dispatches 75% alert for generation 2!
        Queue::fake();
        $this->user->update(['storage_used_bytes' => 78 * 1024 * 1024]);
        $this->notifier->evaluateUsage($this->user->id);

        Queue::assertPushed(StorageQuotaAlertJob::class, function ($job) {
            return $job->userId === $this->user->id && $job->threshold === 75 && $job->generation === 2;
        });
    }

    public function test_job_sends_email_and_marks_notification_record(): void
    {
        Mail::fake();

        $job = new StorageQuotaAlertJob(
            userId: $this->user->id,
            threshold: 75,
            generation: 1,
            usedBytes: 75 * 1024 * 1024,
            limitBytes: 100 * 1024 * 1024
        );
        $job->handle();

        Mail::assertQueued(StorageQuotaAlertMail::class, function ($mail) {
            return $mail->hasTo($this->user->email) && $mail->threshold === 75;
        });

        $record = StorageQuotaNotification::where('user_id', $this->user->id)
            ->where('threshold', 75)
            ->first();

        $this->assertNotNull($record);
        $this->assertEquals('sent', $record->status);
        $this->assertNotNull($record->sent_at);
    }
}
