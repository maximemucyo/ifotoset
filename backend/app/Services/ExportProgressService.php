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
        ];
    }
}
