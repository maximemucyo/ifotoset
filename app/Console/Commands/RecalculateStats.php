<?php

namespace App\Console\Commands;

use App\Models\Gallery;
use App\Models\User;
use App\Services\GalleryStatisticsService;
use Illuminate\Console\Command;

class RecalculateStats extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ifotoset:recalculate-stats';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Recalculate photo counts and storage size statistics for all galleries and users';

    /**
     * Execute the console command.
     */
    public function handle(GalleryStatisticsService $statisticsService): int
    {
        $this->info('Starting recalculation of statistics...');
        $startTime = microtime(true);

        // 1. Recalculate all galleries in chunks
        $galleryCount = 0;
        $this->output->write('Processing galleries: ');
        Gallery::chunkById(500, function ($galleries) use ($statisticsService, &$galleryCount) {
            foreach ($galleries as $gallery) {
                $statisticsService->recalculateGallery($gallery->id);
                $galleryCount++;
            }
            $this->output->write('.');
        });
        $this->output->writeln('');
        $this->info("Successfully processed {$galleryCount} galleries.");

        // 2. Recalculate all users in chunks
        $userCount = 0;
        $this->output->write('Processing users: ');
        User::chunkById(500, function ($users) use ($statisticsService, &$userCount) {
            foreach ($users as $user) {
                $statisticsService->recalculateUserStorage($user->id);
                $userCount++;
            }
            $this->output->write('.');
        });
        $this->output->writeln('');
        $this->info("Successfully processed {$userCount} users.");

        $elapsedTime = round(microtime(true) - $startTime, 2);
        $this->info("Recalculation complete! Total galleries: {$galleryCount}, Total users: {$userCount}. Time taken: {$elapsedTime} seconds.");

        return Command::SUCCESS;
    }
}
?>
