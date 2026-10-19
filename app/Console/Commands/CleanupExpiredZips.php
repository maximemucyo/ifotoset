<?php

namespace App\Console\Commands;

use App\Models\GalleryDownload;
use App\Services\GalleryZipDownloadService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class CleanupExpiredZips extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'gallery:cleanup-expired-zips';

    /**
     * The console command description.
     */
    protected $description = 'Clean up expired gallery ZIP downloads from storage and update their status in the database.';

    /**
     * Execute the console command.
     */
    public function handle(GalleryZipDownloadService $zipService): int
    {
        $this->info("Checking for expired ZIP downloads (older than 24 hours)...");

        $threshold = now()->subHours(24);
        $expiredCount = 0;

        GalleryDownload::whereIn('status', ['ready', 'ready_with_errors'])
            ->where('completed_at', '<', $threshold)
            ->chunkById(50, function ($downloads) use ($zipService, &$expiredCount) {
                foreach ($downloads as $download) {
                    try {
                        if ($zipService->expireIfNecessary($download)) {
                            $expiredCount++;
                        }
                    } catch (\Throwable $e) {
                        Log::error("Failed to clean up expired ZIP download ID {$download->id}: " . $e->getMessage());
                    }
                }
            });

        $this->info("Cleanup process complete! Expired and updated {$expiredCount} ZIP downloads.");

        $abortedCount = $this->abortStaleZipUploads($threshold);

        Log::info("CleanupExpiredZips run complete.", [
            'expired_count' => $expiredCount,
            'aborted_uploads' => $abortedCount,
        ]);

        return Command::SUCCESS;
    }

    /**
     * Cancel ZIP multipart uploads left unfinished by killed or failed export jobs.
     * B2 bills their parts as stored data until they are cancelled.
     */
    private function abortStaleZipUploads(\Carbon\CarbonInterface $threshold): int
    {
        $disk = Storage::disk('b2');
        if (!method_exists($disk, 'getClient')) {
            return 0;
        }

        $aborted = 0;

        try {
            $client = $disk->getClient();
            $bucket = config('filesystems.disks.b2.bucket');
            $params = ['Bucket' => $bucket];

            do {
                $page = $client->listMultipartUploads($params);

                foreach ($page['Uploads'] ?? [] as $upload) {
                    // Only export archives; photo and video uploads share the bucket
                    if (!preg_match('#/downloads/photos-[0-9a-f]+\.zip$#', $upload['Key'])) {
                        continue;
                    }
                    if (\Illuminate\Support\Carbon::parse($upload['Initiated'])->gte($threshold)) {
                        continue;
                    }

                    $client->abortMultipartUpload(['Bucket' => $bucket, 'Key' => $upload['Key'], 'UploadId' => $upload['UploadId']]);
                    $aborted++;
                }

                $params['KeyMarker'] = $page['NextKeyMarker'] ?? null;
                $params['UploadIdMarker'] = $page['NextUploadIdMarker'] ?? null;
            } while (!empty($page['IsTruncated']));
        } catch (\Throwable $e) {
            Log::error('Failed to cancel stale ZIP uploads: ' . $e->getMessage());
        }

        if ($aborted > 0) {
            $this->info("Cancelled {$aborted} unfinished ZIP uploads.");
        }

        return $aborted;
    }
}
