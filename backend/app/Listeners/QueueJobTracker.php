<?php

namespace App\Listeners;

use App\Jobs\ProcessPhotoJob;
use App\Models\MediaJob;
use App\Enums\MediaJobStatus;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class QueueJobTracker
{
    /**
     * Handle job processing starting.
     */
    public static function handleProcessing(JobProcessing $event): void
    {
        $jobInstance = self::resolveJobInstance($event->job);
        if (!$jobInstance instanceof ProcessPhotoJob) {
            return;
        }

        $photo = $jobInstance->getPhoto();
        if (!$photo) {
            return;
        }

        // Find or create the media job record
        $mediaJob = MediaJob::where('photo_id', $photo->id)->orderBy('id', 'desc')->first();
        if (!$mediaJob) {
            $mediaJob = new MediaJob(['photo_id' => $photo->id]);
        }

        $mediaJob->fill([
            'job_name' => $event->job->resolveName(),
            'job_uuid' => $event->job->uuid(),
            'job_type' => $event->job->resolveName(),
            'queue' => $event->job->getQueue(),
            'status' => MediaJobStatus::Processing->value,
            'attempts' => $event->job->attempts(),
            'max_attempts' => $event->job->maxTries() ?? 3,
            'started_at' => now(),
            'completed_at' => null,
            'failed_at' => null,
            'error_message' => null,
        ]);
        $mediaJob->save();
    }

    /**
     * Handle job processed successfully.
     */
    public static function handleProcessed(JobProcessed $event): void
    {
        $jobInstance = self::resolveJobInstance($event->job);
        if (!$jobInstance instanceof ProcessPhotoJob) {
            return;
        }

        $photo = $jobInstance->getPhoto();
        if (!$photo) {
            return;
        }

        $mediaJob = MediaJob::where('photo_id', $photo->id)->orderBy('id', 'desc')->first();
        if ($mediaJob) {
            $durationMs = $mediaJob->started_at ? (int) (now()->diffInRealMicroseconds($mediaJob->started_at) / 1000) : null;
            $mediaJob->update([
                'status' => MediaJobStatus::Completed->value,
                'completed_at' => now(),
                'duration_ms' => $durationMs,
                'progress' => 'Completed',
            ]);
        }
    }

    /**
     * Handle job failure.
     */
    public static function handleFailed(JobFailed $event): void
    {
        $jobInstance = self::resolveJobInstance($event->job);
        if (!$jobInstance instanceof ProcessPhotoJob) {
            return;
        }

        $photo = $jobInstance->getPhoto();
        if (!$photo) {
            return;
        }

        $mediaJob = MediaJob::where('photo_id', $photo->id)->orderBy('id', 'desc')->first();
        if ($mediaJob) {
            $durationMs = $mediaJob->started_at ? (int) (now()->diffInRealMicroseconds($mediaJob->started_at) / 1000) : null;
            $mediaJob->update([
                'status' => MediaJobStatus::Failed->value,
                'failed_at' => now(),
                'duration_ms' => $durationMs,
                'error_message' => $event->exception->getMessage(),
            ]);
        }
    }

    /**
     * Helper to extract the actual job class instance from the queue driver's wrapper.
     */
    protected static function resolveJobInstance($job)
    {
        try {
            $payload = $job->payload();
            if (isset($payload['data']['command'])) {
                return unserialize($payload['data']['command']);
            }
        } catch (\Throwable $e) {
            Log::error("QueueJobTracker: Failed to unserialize job payload: " . $e->getMessage());
        }
        return null;
    }
}
