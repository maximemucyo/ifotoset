<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\Directory;
use Illuminate\Console\Command;

class MatchDirectoryCities extends Command
{
    protected $signature = 'directory:match-cities';

    protected $description = 'Match every profile location to a directory city, picking up new cities and aliases';

    public function handle(): int
    {
        $changed = 0;

        User::query()
            ->where(fn ($q) => $q->whereNotNull('location')->orWhereNotNull('city'))
            ->select(['id', 'location', 'city'])
            ->chunkById(500, function ($users) use (&$changed) {
                foreach ($users as $user) {
                    $city = Directory::matchCity($user->location);
                    if ($city === $user->city) {
                        continue;
                    }

                    // Quietly, so the profile's updated_at (the sitemap lastmod) stays put
                    User::whereKey($user->id)->toBase()->update(['city' => $city]);
                    $changed++;
                }
            });

        $this->info("Updated {$changed} " . str('profile')->plural($changed) . '.');

        return self::SUCCESS;
    }
}
