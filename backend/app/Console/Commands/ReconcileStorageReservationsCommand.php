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

        $expiredSessions = UploadSession::where('status', UploadStatus::Requested->value)
            ->where('expires_at', '<', now());

        if ($specificUserId) {
            $expiredSessions->where('user_id', $specificUserId);
        }

        // Clean up any partial objects in storage asynchronously for expired sessions
        $storageService = app(\App\Services\StorageService::class);
        $expiredSessions->chunkById(50, function ($sessions) use ($storageService) {
            foreach ($sessions as $session) {
                try {
                    $storageService->delete($session->object_key);
                } catch (\Throwable $e) {
                    // Ignore storage deletion errors for expired reservations
                }
            }
        });

        $expiredCount = $staleQuery->update([
            'status' => UploadStatus::Expired->value,
            'updated_at' => now(),
        ]);

        $this->info("Expired {$expiredCount} stale upload session(s).");

        // 2. Reconcile storage_reserved_bytes and video_seconds_reserved for affected users
        $userIdsToReconcile = $specificUserId ? [(int) $specificUserId] : $affectedUserIds;

        if (empty($userIdsToReconcile) && !$specificUserId) {
            // Self-healing: also pick any users who currently have storage or video reservations > 0
            $activeReservedUserIds = DB::table('users')
                ->where('storage_reserved_bytes', '>', 0)
                ->orWhere('video_seconds_reserved', '>', 0)
                ->pluck('id')
                ->all();
            $userIdsToReconcile = array_unique(array_merge($userIdsToReconcile, $activeReservedUserIds));
        }

        $reconciledCount = 0;
        foreach ($userIdsToReconcile as $uid) {
            $activeSessions = DB::table('upload_sessions')
                ->where('user_id', $uid)
                ->where('status', UploadStatus::Requested->value)
                ->where('expires_at', '>', now());

            $actualStorageReserved = (int) (clone $activeSessions)->sum('expected_size');
            $actualVideoReserved = (int) (clone $activeSessions)->sum('reserved_duration_seconds');

            DB::table('users')
                ->where('id', $uid)
                ->update([
                    'storage_reserved_bytes' => $actualStorageReserved,
                    'video_seconds_reserved' => $actualVideoReserved,
                ]);

            $reconciledCount++;
        }

        $this->info("Reconciled reservations for {$reconciledCount} user(s).");
        return Command::SUCCESS;
    }
}
