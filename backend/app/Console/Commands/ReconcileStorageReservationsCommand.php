<?php

namespace App\Console\Commands;

use App\Enums\UploadStatus;
use App\Models\UploadSession;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ReconcileStorageReservationsCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'uploads:reconcile-reservations {--user= : Optional specific user ID to reconcile}';

    /**
     * The console command description.
     */
    protected $description = 'Reconcile user storage reservation counters and expire stale upload sessions';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Starting storage reservation reconciliation...');

        // 1. Identify and expire stale requested upload sessions
        $staleQuery = UploadSession::where('status', UploadStatus::Requested->value)
            ->where('expires_at', '<', now());

        $specificUserId = $this->option('user');
        if ($specificUserId) {
            $staleQuery->where('user_id', $specificUserId);
        }

        $affectedUserIds = $staleQuery->pluck('user_id')->unique()->all();

        $expiredCount = $staleQuery->update([
            'status' => UploadStatus::Expired->value,
            'updated_at' => now(),
        ]);

        $this->info("Expired {$expiredCount} stale upload session(s).");

        // 2. Reconcile storage_reserved_bytes for affected users (or all users if specific option given)
        $userIdsToReconcile = $specificUserId ? [(int) $specificUserId] : $affectedUserIds;

        if (empty($userIdsToReconcile) && !$specificUserId) {
            // Self-healing: also pick any users who currently have storage_reserved_bytes > 0
            $activeReservedUserIds = DB::table('users')
                ->where('storage_reserved_bytes', '>', 0)
                ->pluck('id')
                ->all();
            $userIdsToReconcile = array_unique(array_merge($userIdsToReconcile, $activeReservedUserIds));
        }

        $reconciledCount = 0;
        foreach ($userIdsToReconcile as $uid) {
            $actualReserved = (int) DB::table('upload_sessions')
                ->where('user_id', $uid)
                ->where('status', UploadStatus::Requested->value)
                ->where('expires_at', '>', now())
                ->sum('expected_size');

            DB::table('users')
                ->where('id', $uid)
                ->update(['storage_reserved_bytes' => $actualReserved]);

            $reconciledCount++;
        }

        $this->info("Reconciled reservations for {$reconciledCount} user(s).");
        return Command::SUCCESS;
    }
}
