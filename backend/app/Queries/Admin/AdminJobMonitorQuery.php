<?php

namespace App\Queries\Admin;

use App\Models\GalleryDownload;
use App\Models\GooglePhotoSync;
use App\Models\MediaJob;
use App\Services\ExportProgressService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AdminJobMonitorQuery
{
    public function __construct(
        protected ExportProgressService $exportProgressService
    ) {}

    /**
     * Get operational queue metrics with precise timestamps.
     */
    public function metrics(): array
    {
        $todayStart = Carbon::now()->startOfDay();

        $queued = MediaJob::where('status', 'queued')->count();
        $processing = MediaJob::where('status', 'processing')->count();
        $failedToday = MediaJob::where('status', 'failed')
            ->where('failed_at', '>=', $todayStart)
            ->count();
        $completedToday = MediaJob::where('status', 'completed')
            ->where('completed_at', '>=', $todayStart)
            ->count();

        $failedTotal = MediaJob::where('status', 'failed')->count();

        $activeZipExports = GalleryDownload::whereIn('status', ['pending', 'processing'])->count();
        $activeGoogleSyncs = GooglePhotoSync::whereIn('status', ['pending', 'processing'])->count();
        $activeExports = $activeZipExports + $activeGoogleSyncs;

        $hasActiveJobs = ($queued > 0 || $processing > 0 || $activeExports > 0);

        return [
            'queued' => $queued,
            'processing' => $processing,
            'failed_today' => $failedToday,
            'failed_total' => $failedTotal,
            'completed_today' => $completedToday,
            'active_exports' => $activeExports,
            'has_active_jobs' => $hasActiveJobs,
            'queue_backlog' => $this->queueBacklog(),
        ];
    }

    /**
     * Get queue backlog sizes across critical queues.
     */
    public function queueBacklog(): array
    {
        try {
            return [
                'photos' => (int) \Illuminate\Support\Facades\Queue::connection('redis')->size('photos'),
                'default' => (int) \Illuminate\Support\Facades\Queue::connection('redis')->size('default'),
            ];
        } catch (\Throwable) {
            return [
                'photos' => 0,
                'default' => 0,
            ];
        }
    }

    /**
     * Get batch processing summaries grouped by gallery.
     */
    public function galleryBatches(): array
    {
        try {
            $galleries = DB::table('galleries')
                ->join('users', 'galleries.user_id', '=', 'users.id')
                ->join('photos', 'galleries.id', '=', 'photos.gallery_id')
                ->leftJoin('media_jobs', 'photos.id', '=', 'media_jobs.photo_id')
                ->select([
                    'galleries.id as gallery_id',
                    'galleries.title as gallery_title',
                    'galleries.uuid as gallery_uuid',
                    'users.name as studio_name',
                    DB::raw('COUNT(DISTINCT photos.id) as total_photos'),
                    DB::raw("COUNT(DISTINCT CASE WHEN photos.status = 'ready' OR media_jobs.status = 'completed' THEN photos.id END) as completed_photos"),
                    DB::raw("COUNT(DISTINCT CASE WHEN media_jobs.status = 'queued' THEN photos.id END) as queued_photos"),
                    DB::raw("COUNT(DISTINCT CASE WHEN media_jobs.status = 'processing' THEN photos.id END) as processing_photos"),
                    DB::raw("COUNT(DISTINCT CASE WHEN media_jobs.status = 'failed' THEN photos.id END) as failed_photos"),
                    DB::raw('MAX(media_jobs.created_at) as latest_job_at'),
                ])
                ->groupBy('galleries.id', 'galleries.title', 'galleries.uuid', 'users.name')
                ->havingRaw("queued_photos > 0 OR processing_photos > 0 OR failed_photos > 0")
                ->orderByDesc('latest_job_at')
                ->limit(25)
                ->get();

            return $galleries->map(function ($g) {
                $total = (int) $g->total_photos;
                $completed = (int) $g->completed_photos;
                $percentage = $total > 0 ? min(100, (int) round(($completed / $total) * 100)) : 0;

                return [
                    'gallery_id' => (int) $g->gallery_id,
                    'gallery_title' => $g->gallery_title,
                    'gallery_uuid' => $g->gallery_uuid,
                    'studio_name' => $g->studio_name,
                    'total_photos' => $total,
                    'completed_photos' => $completed,
                    'queued_photos' => (int) $g->queued_photos,
                    'processing_photos' => (int) $g->processing_photos,
                    'failed_photos' => (int) $g->failed_photos,
                    'progress_percentage' => $percentage,
                    'latest_job_at' => $g->latest_job_at ? Carbon::parse($g->latest_job_at)->toIso8601String() : null,
                ];
            })->values()->all();
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Fetch paginated MediaJob records with relationships, filters, and search.
     */
    public function mediaJobs(?string $status = null, ?string $search = null, int $perPage = 15): LengthAwarePaginator
    {
        $query = MediaJob::with('photo.gallery.user');

        if ($status && in_array($status, ['queued', 'processing', 'completed', 'failed'], true)) {
            $query->where('status', $status);
        }

        if (!empty($search)) {
            $query->whereHas('photo', function ($q) use ($search) {
                $q->where('filename', 'like', "%{$search}%")
                    ->orWhere('original_filename', 'like', "%{$search}%")
                    ->orWhere('uuid', 'like', "%{$search}%")
                    ->orWhereHas('gallery', function ($gq) use ($search) {
                        $gq->where('title', 'like', "%{$search}%")
                            ->orWhereHas('user', function ($uq) use ($search) {
                                $uq->where('name', 'like', "%{$search}%")
                                    ->orWhere('username', 'like', "%{$search}%");
                            });
                    });
            });
        }

        return $query->orderBy('id', 'desc')->paginate($perPage)->withQueryString();
    }

    /**
     * Fetch paginated GalleryDownload (ZIP) and GooglePhotoSync records combined.
     */
    public function exportJobs(int $perPage = 10, int $page = 1): LengthAwarePaginator
    {
        $downloadsQuery = DB::table('gallery_downloads')
            ->join('galleries', 'gallery_downloads.gallery_id', '=', 'galleries.id')
            ->join('users', 'galleries.user_id', '=', 'users.id')
            ->select([
                'gallery_downloads.id',
                DB::raw("'zip' as type"),
                'galleries.title as gallery_title',
                'users.name as studio_name',
                'gallery_downloads.status',
                'gallery_downloads.email',
                'gallery_downloads.notify_when_ready',
                'gallery_downloads.total_photos',
                'gallery_downloads.processed_photos',
                'gallery_downloads.failed_photos',
                'gallery_downloads.error',
                'gallery_downloads.started_at',
                'gallery_downloads.completed_at',
                'gallery_downloads.created_at',
            ]);

        $syncsQuery = DB::table('google_photo_syncs')
            ->join('galleries', 'google_photo_syncs.gallery_id', '=', 'galleries.id')
            ->join('users', 'galleries.user_id', '=', 'users.id')
            ->select([
                'google_photo_syncs.id',
                DB::raw("'google-photos' as type"),
                'galleries.title as gallery_title',
                'users.name as studio_name',
                'google_photo_syncs.status',
                'google_photo_syncs.email',
                'google_photo_syncs.notify_when_ready',
                'google_photo_syncs.total_photos',
                'google_photo_syncs.processed_photos',
                'google_photo_syncs.failed_photos',
                'google_photo_syncs.error',
                'google_photo_syncs.started_at',
                'google_photo_syncs.completed_at',
                'google_photo_syncs.created_at',
            ]);

        $unionQuery = $downloadsQuery->unionAll($syncsQuery);

        $paginator = DB::query()
            ->fromSub($unionQuery, 'combined')
            ->orderBy('created_at', 'desc')
            ->paginate($perPage, ['*'], 'export_page', $page);

        // Transform records using ExportProgressService
        $transformed = collect($paginator->items())->map(function ($job) {
            $progress = $job->type === 'zip'
                ? $this->exportProgressService->forZip($job)
                : $this->exportProgressService->forSync($job);

            return [
                'id' => $job->id,
                'type' => $job->type,
                'gallery_title' => $job->gallery_title,
                'studio_name' => $job->studio_name,
                'status' => $job->status,
                'email' => $this->maskEmail($job->email),
                'notify_when_ready' => (bool) $job->notify_when_ready,
                'total_photos' => (int) $job->total_photos,
                'processed_photos' => (int) $job->processed_photos,
                'failed_photos' => (int) $job->failed_photos,
                'error' => $job->error,
                'started_at' => $job->started_at ? Carbon::parse($job->started_at)->toIso8601String() : null,
                'completed_at' => $job->completed_at ? Carbon::parse($job->completed_at)->toIso8601String() : null,
                'created_at' => Carbon::parse($job->created_at)->toIso8601String(),
                'percentage' => $progress['percentage'],
                'indeterminate' => $progress['indeterminate'] ?? false,
                'elapsed_seconds' => $progress['elapsed_seconds'] ?? null,
                'remaining_seconds' => $progress['remaining_seconds'] ?? null,
                'estimated_finish_time' => $progress['estimated_finish_time'] ?? null,
            ];
        });

        return new LengthAwarePaginator(
            $transformed,
            $paginator->total(),
            $paginator->perPage(),
            $paginator->currentPage(),
            ['path' => LengthAwarePaginator::resolveCurrentPath(), 'pageName' => 'export_page']
        );
    }

    /**
     * Transform a single MediaJob record for consistent API and frontend consumption.
     */
    public function transformMediaJob(MediaJob $job): array
    {
        return [
            'id' => $job->id,
            'photo_uuid' => $job->photo?->uuid,
            'original_filename' => $job->photo?->original_filename ?: ($job->photo?->filename ?: 'photo.jpg'),
            'gallery_title' => $job->photo?->gallery?->title ?: 'N/A',
            'studio_name' => $job->photo?->gallery?->user?->name ?: 'N/A',
            'status' => $job->status,
            'progress' => $job->progress ?: ($job->status === 'completed' ? 'Finalized' : ($job->status === 'failed' ? 'Failed' : 'Queued')),
            'attempts' => $job->attempts,
            'max_attempts' => $job->max_attempts ?? 3,
            'duration_ms' => $job->duration_ms,
            'error_message' => $job->error_message ?: $job->error,
            'started_at' => $job->started_at?->toIso8601String(),
            'completed_at' => $job->completed_at?->toIso8601String(),
            'failed_at' => $job->failed_at?->toIso8601String(),
            'created_at' => $job->created_at?->toIso8601String(),
        ];
    }

    /**
     * Build the lightweight JSON payload for continuous Alpine.js smart-polling.
     */
    public function statusPayload(Request $request): array
    {
        $metrics = $this->metrics();
        $status = $request->query('status');
        $search = $request->query('search');

        // Recent / Active Media Jobs (top 15)
        $jobsQuery = MediaJob::with('photo.gallery.user')->orderBy('id', 'desc');
        if ($status && in_array($status, ['queued', 'processing', 'completed', 'failed'], true)) {
            $jobsQuery->where('status', $status);
        }
        if (!empty($search)) {
            $jobsQuery->whereHas('photo', function ($q) use ($search) {
                $q->where('filename', 'like', "%{$search}%")
                    ->orWhere('original_filename', 'like', "%{$search}%")
                    ->orWhere('uuid', 'like', "%{$search}%")
                    ->orWhereHas('gallery', function ($gq) use ($search) {
                        $gq->where('title', 'like', "%{$search}%")
                            ->orWhereHas('user', function ($uq) use ($search) {
                                $uq->where('name', 'like', "%{$search}%");
                            });
                    });
            });
        }
        $mediaJobs = $jobsQuery->take(15)->get()->map(fn($job) => $this->transformMediaJob($job))->values()->all();

        // Recent / Active Exports (top 10)
        $exports = collect($this->exportJobs(10, 1)->items())->values()->all();

        return [
            'metrics' => $metrics,
            'has_active_jobs' => $metrics['has_active_jobs'],
            'media_jobs' => $mediaJobs,
            'exports' => $exports,
            'gallery_batches' => $this->galleryBatches(),
        ];
    }

    /**
     * Bundle data for initial full Blade view render.
     */
    public function forIndex(Request $request): array
    {
        $status = $request->query('status');
        $search = $request->query('search');

        $mediaJobs = $this->mediaJobs($status, $search);
        $exportJobs = $this->exportJobs();
        $galleryBatches = $this->galleryBatches();

        $initialMediaJobs = collect($mediaJobs->items())->map(fn($job) => $this->transformMediaJob($job))->values()->all();
        $initialExportJobs = collect($exportJobs->items())->values()->all();

        return [
            'metrics' => $this->metrics(),
            'mediaJobs' => $mediaJobs,
            'exportJobs' => $exportJobs,
            'galleryBatches' => $galleryBatches,
            'initialMediaJobs' => $initialMediaJobs,
            'initialExportJobs' => $initialExportJobs,
            'initialGalleryBatches' => $galleryBatches,
            'statusFilter' => $status,
            'searchFilter' => $search,
        ];
    }

    /**
     * Helper to mask email address for security and privacy (e.g. j***e@example.com).
     */
    public function maskEmail(?string $email): ?string
    {
        if (empty($email) || !str_contains($email, '@')) {
            return null;
        }

        [$name, $domain] = explode('@', $email, 2);
        $len = strlen($name);

        if ($len <= 2) {
            $maskedName = substr($name, 0, 1) . '*';
        } else {
            $maskedName = substr($name, 0, 1) . str_repeat('*', min(4, $len - 2)) . substr($name, -1);
        }

        return $maskedName . '@' . $domain;
    }
}
