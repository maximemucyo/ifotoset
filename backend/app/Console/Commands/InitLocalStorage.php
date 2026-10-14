<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Prepares the local S3-compatible bucket (SeaweedFS in docker-compose) that
 * stands in for Backblaze B2 during development.
 */
class InitLocalStorage extends Command
{
    protected $signature = 'storage:init-local';

    protected $description = 'Create the media bucket on the local S3-compatible storage service';

    public function handle(): int
    {
        if (app()->environment('production')) {
            $this->error('Refusing to run in production.');
            return self::FAILURE;
        }

        $client = Storage::disk('b2')->getClient();
        $bucket = config('filesystems.disks.b2.bucket');

        if ($client->doesBucketExist($bucket)) {
            $this->info("Bucket [{$bucket}] already exists.");
            return self::SUCCESS;
        }

        $client->createBucket(['Bucket' => $bucket]);
        $this->info("Bucket [{$bucket}] created.");

        return self::SUCCESS;
    }
}
