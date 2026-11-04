<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\Portfolio\LogoTone;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;
use Throwable;

class BackfillLogoTones extends Command
{
    protected $signature = 'portfolio:backfill-logo-tones {--dry-run : Report each tone without saving it}';

    protected $description = 'Check whether logos uploaded before tones were recorded are cut out, and dark or light';

    public function handle(): int
    {
        $manager = new ImageManager(new Driver());
        $users = User::query()->whereNotNull('logo_path')->get();
        $this->info("Checking {$users->count()} logo(s)...");

        foreach ($users as $user) {
            if (array_key_exists('logo_tone', $user->portfolio_settings ?? [])) {
                continue;
            }

            try {
                $tone = LogoTone::detect($manager->read(Storage::disk('b2')->get($user->logo_path))->core()->native());
            } catch (Throwable $e) {
                $this->warn("{$user->username}: {$e->getMessage()}");
                continue;
            }

            $this->line("{$user->username}: " . ($tone ?? 'own background'));
            if (!$this->option('dry-run')) {
                $user->update(['portfolio_settings' => array_merge($user->portfolio_settings ?? [], ['logo_tone' => $tone])]);
            }
        }

        return self::SUCCESS;
    }
}
