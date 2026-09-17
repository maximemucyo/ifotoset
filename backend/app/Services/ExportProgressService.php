<?php

namespace App\Services;

use Carbon\Carbon;

class ExportProgressService
{
    /**
     * Calculate progress metrics for an export job.
     *
     * @param Carbon|null $startedAt
     * @param Carbon|null $completedAt
     * @param int $total
     * @param int $processed
     * @param int $failed
     * @param string $status
     * @return array
     */
    public function calculate(?Carbon $startedAt, ?Carbon $completedAt, int $total, int $processed, int $failed, string $status): array
    {
        $percentage = 0;
        if ($total > 0) {
            $percentage = min(100, (int) round((($processed + $failed) / $total) * 100));
        }

        $elapsedSeconds = null;
        $remainingSeconds = null;
        $estimatedFinishTime = null;

        if ($completedAt && ($status === 'ready' || $status === 'completed' || $status === 'completed_with_errors')) {
            $percentage = 100;
        } elseif ($status === 'processing' || $status === 'pending') {
            if ($startedAt) {
                $now = Carbon::now();
                $elapsedSeconds = max(1, abs($now->diffInSeconds($startedAt)));
                $done = $processed + $failed;
                $remainingPhotos = max(0, $total - $done);

                if ($done > 0) {
                    $rate = $done / $elapsedSeconds; // photos per second
                    $remainingSeconds = $rate > 0 ? (int) ceil($remainingPhotos / $rate) : null;
                } else {
                    // Fallback to 1.5 seconds per photo if processing has just started
                    $remainingSeconds = (int) ceil($remainingPhotos * 1.5);
                }

                if ($remainingSeconds !== null) {
                    $estimatedFinishTime = $now->copy()->addSeconds($remainingSeconds);
                }
            } else {
                // If not started yet, return a safe fallback estimate
                $remainingSeconds = (int) ceil($total * 1.5);
                $estimatedFinishTime = Carbon::now()->addSeconds($remainingSeconds);
            }
        }

        return [
            'percentage' => $percentage,
            'elapsed_seconds' => $elapsedSeconds,
            'remaining_seconds' => $remainingSeconds,
            'estimated_finish_time' => $estimatedFinishTime ? $estimatedFinishTime->toIso8601String() : null,
            'indeterminate' => false,
        ];
    }

    /**
     * Calculate progress specifically for a GalleryDownload (ZIP packaging) record.
     */
    public function forZip(object $download): array
    {
        $startedAt = $download->started_at ? Carbon::parse($download->started_at) : null;
        $completedAt = $download->completed_at ? Carbon::parse($download->completed_at) : null;
        $total = (int) ($download->total_photos ?? 0);
        $processed = (int) ($download->processed_photos ?? 0);
        $failed = (int) ($download->failed_photos ?? 0);
        $status = (string) ($download->status ?? 'pending');

        $metrics = $this->calculate($startedAt, $completedAt, $total, $processed, $failed, $status);
        $metrics['total'] = $total;
        $metrics['processed'] = $processed;
        $metrics['failed'] = $failed;
        $metrics['status'] = $status;
        return $metrics;
    }

    /**
     * Calculate progress specifically for a GooglePhotoSync record.
     * Guarantees truthful progress: if total photos is 0 during processing,
     * it marks indeterminate=true rather than inventing a fake percentage.
     */
    public function forSync(object $sync): array
    {
        $startedAt = $sync->started_at ? Carbon::parse($sync->started_at) : null;
        $completedAt = $sync->completed_at ? Carbon::parse($sync->completed_at) : null;
        $total = (int) ($sync->total_photos ?? 0);
        $processed = (int) ($sync->processed_photos ?? 0);
        $failed = (int) ($sync->failed_photos ?? 0);
        $status = (string) ($sync->status ?? 'pending');

        if ($total === 0 && in_array($status, ['pending', 'processing'])) {
            return [
                'percentage' => 0,
                'elapsed_seconds' => $startedAt ? max(1, abs(Carbon::now()->diffInSeconds($startedAt))) : null,
                'remaining_seconds' => null,
                'estimated_finish_time' => null,
                'indeterminate' => true,
                'total' => 0,
                'processed' => $processed,
                'failed' => $failed,
                'status' => $status,
            ];
        }

        $metrics = $this->calculate($startedAt, $completedAt, $total, $processed, $failed, $status);
        $metrics['total'] = $total;
        $metrics['processed'] = $processed;
        $metrics['failed'] = $failed;
        $metrics['status'] = $status;
        $metrics['indeterminate'] = false;
        return $metrics;
    }
}
