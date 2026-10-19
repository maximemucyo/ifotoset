<?php

namespace App\Console\Commands;

use App\Jobs\RelocateGalleryMediaJob;
use App\Models\Gallery;
use App\Models\Photo;
use Illuminate\Console\Command;

class RelocateGalleryMedia extends Command
{
    protected $signature = 'media:relocate-galleries
        {--dry-run : List mismatched galleries without moving anything}
        {--sync : Run the moves in this process instead of queueing them}';

    protected $description = 'Move media of galleries whose storage prefix does not match their privacy (e.g. PIN galleries with files under galleries/)';

    public function handle(): int
    {
        $found = 0;

        Gallery::query()->chunkById(200, function ($galleries) use (&$found) {
            foreach ($galleries as $gallery) {
                $wrongPrefix = $gallery->mediaPrefix(!$gallery->hasProtectedMedia());

                $mismatched = Photo::withTrashed()
                    ->where('gallery_id', $gallery->id)
                    ->where(fn ($q) => $q->where('path', 'like', "{$wrongPrefix}/%")->orWhere('original_path', 'like', "{$wrongPrefix}/%"))
                    ->count();

                if ($mismatched === 0) {
                    continue;
                }

                $found++;
                $this->line(sprintf(
                    '#%d "%s" (%s): %d photo(s) under %s/',
                    $gallery->id,
                    $gallery->title,
                    $gallery->hasProtectedMedia() ? 'protected' : 'public',
                    $mismatched,
                    $wrongPrefix
                ));

                if ($this->option('dry-run')) {
                    continue;
                }

                $this->option('sync')
                    ? RelocateGalleryMediaJob::dispatchSync($gallery->id)
                    : RelocateGalleryMediaJob::dispatch($gallery->id);
            }
        });

        $verb = $this->option('dry-run') ? 'found' : ($this->option('sync') ? 'relocated' : 'queued');
        $this->info("{$found} gallery(ies) {$verb}.");

        return self::SUCCESS;
    }
}
