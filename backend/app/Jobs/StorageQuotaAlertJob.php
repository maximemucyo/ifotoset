<?php

namespace App\Jobs;

use App\Mail\StorageQuotaAlertMail;
use App\Models\StorageQuotaNotification;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class StorageQuotaAlertJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public readonly int $userId,
        public readonly int $threshold, // 75 or 100
        public readonly int $generation,
        public readonly int $usedBytes,
        public readonly int $limitBytes
    ) {}

    public function handle(): void
    {
        $user = User::find($this->userId);
        if (!$user || !$user->email) {
            return;
        }

        // Idempotently create or retrieve notification tracking record
        $notification = StorageQuotaNotification::firstOrCreate([
            'user_id' => $this->userId,
            'threshold' => $this->threshold,
            'generation' => $this->generation,
        ], [
            'usage_bytes' => $this->usedBytes,
            'limit_bytes' => $this->limitBytes,
            'status' => 'dispatched',
        ]);

        if ($notification->status === 'sent') {
            // Already sent in a previous job execution
            return;
        }

        try {
            Mail::to($user->email)->send(
                new StorageQuotaAlertMail(
                    user: $user,
                    threshold: $this->threshold,
                    usedBytes: $this->usedBytes,
                    limitBytes: $this->limitBytes
                )
            );

            $notification->update([
                'status' => 'sent',
                'sent_at' => now(),
            ]);

            Log::info("Storage quota alert ({$this->threshold}%) sent to user #{$this->userId} (gen {$this->generation}).");
        } catch (Throwable $e) {
            $notification->update(['status' => 'failed']);
            Log::error("Failed sending storage quota alert ({$this->threshold}%) to user #{$this->userId}: " . $e->getMessage());
            throw $e;
        }
    }
}
