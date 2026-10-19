<?php

namespace App\Console\Commands;

use App\Jobs\PurgeDeletedUserJob;
use App\Models\User;
use Illuminate\Console\Command;

class PurgeDeletedUsers extends Command
{
    protected $signature = 'users:purge-deleted {--days=30 : Grace period after deletion before content is erased}';

    protected $description = 'Erase galleries, storage objects and personal data of accounts deleted longer ago than the grace period';

    public function handle(): int
    {
        $threshold = now()->subDays((int) $this->option('days'));

        $count = 0;
        User::onlyTrashed()
            ->whereNull('purged_at')
            ->where('deleted_at', '<=', $threshold)
            ->select('id')
            ->chunkById(100, function ($users) use (&$count) {
                foreach ($users as $user) {
                    PurgeDeletedUserJob::dispatch($user->id);
                    $count++;
                }
            });

        $this->info("Queued {$count} deleted accounts for purge.");

        return self::SUCCESS;
    }
}
