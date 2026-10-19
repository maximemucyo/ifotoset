<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\GalleryStatisticsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ReconcileMediaStatisticsCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'media:reconcile-statistics {--user= : Optional specific user ID to reconcile}';

    /**
     * The console command description.
     */
    protected $description = 'Reconcile storage and video quota usage invariants from active media records';

    public function handle(GalleryStatisticsService $statisticsService): int
    {
        $this->info('Starting media statistics reconciliation...');

        $specificUserId = $this->option('user');
        $usersQuery = User::query();

        if ($specificUserId) {
            $usersQuery->where('id', $specificUserId);
        }

        $count = 0;
        $usersQuery->chunkById(100, function ($users) use ($statisticsService, &$count) {
            foreach ($users as $user) {
                // 1. Recalculate storage used bytes
                $statisticsService->recalculateUserStorage($user->id);

                // 2. Recalculate video seconds used
                $statisticsService->recalculateUserVideoSeconds($user->id);

                // 3. Recalculate gallery stats for all user galleries
                $galleryIds = DB::table('galleries')
                    ->where('user_id', $user->id)
                    ->whereNull('deleted_at')
                    ->pluck('id');

                foreach ($galleryIds as $gid) {
                    $statisticsService->recalculateGallery($gid);
                }

                $count++;
            }
        });

        $this->info("Successfully reconciled media statistics for {$count} user(s).");
        return Command::SUCCESS;
    }
}
