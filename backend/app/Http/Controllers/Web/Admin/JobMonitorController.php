<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Queries\Admin\AdminJobMonitorQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class JobMonitorController extends Controller
{
    public function __construct(
        protected AdminJobMonitorQuery $jobMonitorQuery
    ) {}

    /**
     * Render the admin processing queue & exports monitor page.
     * GET /admin/queue
     */
    public function index(Request $request): View
    {
        $data = $this->jobMonitorQuery->forIndex($request);

        return view('admin.jobs', $data);
    }

    /**
     * Lightweight status JSON endpoint for continuous smart-polling.
     * GET /admin/queue/status
     */
    public function status(Request $request): JsonResponse
    {
        $payload = $this->jobMonitorQuery->statusPayload($request);

        return response()->json($payload);
    }

    /**
     * Retry a single media job.
     * POST /admin/queue/retry/{id}
     */
    public function retry(Request $request, int $id): JsonResponse
    {
        $job = \App\Models\MediaJob::with('photo')->find($id);

        if (!$job) {
            return response()->json(['success' => false, 'error' => 'Media job not found.'], 404);
        }

        if (!$job->photo) {
            return response()->json(['success' => false, 'error' => 'Associated photo record not found.'], 422);
        }

        $job->update([
            'status' => \App\Enums\MediaJobStatus::Queued->value,
            'progress' => 'Queued',
            'attempts' => 0,
            'failed_at' => null,
            'completed_at' => null,
            'error_message' => null,
        ]);

        \App\Jobs\ProcessPhotoJob::dispatch($job->photo);

        return response()->json([
            'success' => true,
            'message' => "Job #{$id} ({$job->photo->original_filename}) was re-queued successfully.",
            'job' => $this->jobMonitorQuery->transformMediaJob($job->fresh()),
        ]);
    }

    /**
     * Retry all failed media jobs.
     * POST /admin/queue/retry-failed
     */
    public function retryFailed(Request $request): JsonResponse
    {
        $failedJobs = \App\Models\MediaJob::where('status', 'failed')
            ->with('photo')
            ->get();

        $count = 0;
        foreach ($failedJobs as $job) {
            if ($job->photo) {
                $job->update([
                    'status' => \App\Enums\MediaJobStatus::Queued->value,
                    'progress' => 'Queued',
                    'attempts' => 0,
                    'failed_at' => null,
                    'completed_at' => null,
                    'error_message' => null,
                ]);
                \App\Jobs\ProcessPhotoJob::dispatch($job->photo);
                $count++;
            }
        }

        return response()->json([
            'success' => true,
            'count' => $count,
            'message' => "{$count} failed jobs were re-queued successfully.",
        ]);
    }

    /**
     * Refresh / re-queue all currently queued jobs to ensure presence in Redis.
     * POST /admin/queue/retry-all-queued
     */
    public function retryAllQueued(Request $request): JsonResponse
    {
        $queuedJobs = \App\Models\MediaJob::where('status', 'queued')
            ->with('photo')
            ->get();

        $count = 0;
        foreach ($queuedJobs as $job) {
            if ($job->photo) {
                \App\Jobs\ProcessPhotoJob::dispatch($job->photo);
                $count++;
            }
        }

        return response()->json([
            'success' => true,
            'count' => $count,
            'message' => "{$count} queued jobs were re-dispatched into the worker queue.",
        ]);
    }

    /**
     * Retry all pending/failed jobs for a specific gallery.
     * POST /admin/queue/retry-gallery/{galleryId}
     */
    public function retryGallery(Request $request, int $galleryId): JsonResponse
    {
        $jobs = \App\Models\MediaJob::whereHas('photo', function ($q) use ($galleryId) {
            $q->where('gallery_id', $galleryId);
        })
        ->whereIn('status', ['failed', 'queued'])
        ->with('photo')
        ->get();

        $count = 0;
        foreach ($jobs as $job) {
            if ($job->photo) {
                $job->update([
                    'status' => \App\Enums\MediaJobStatus::Queued->value,
                    'progress' => 'Queued',
                    'attempts' => 0,
                    'failed_at' => null,
                    'completed_at' => null,
                    'error_message' => null,
                ]);
                \App\Jobs\ProcessPhotoJob::dispatch($job->photo);
                $count++;
            }
        }

        return response()->json([
            'success' => true,
            'count' => $count,
            'message' => "{$count} jobs for this gallery were re-queued successfully.",
        ]);
    }

    /**
     * Restart Laravel queue workers.
     * POST /admin/queue/restart-workers
     */
    public function restartWorkers(Request $request): JsonResponse
    {
        \Illuminate\Support\Facades\Artisan::call('queue:restart');

        return response()->json([
            'success' => true,
            'message' => 'Queue worker restart signal broadcasted successfully.',
        ]);
    }
}
