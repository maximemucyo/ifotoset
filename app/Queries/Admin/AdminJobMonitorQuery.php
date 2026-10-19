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

                $uuidStr = '';
                if (!empty($g->gallery_uuid)) {
                    if (strlen($g->gallery_uuid) === 16) {
                        try {
                            $uuidStr = \Ramsey\Uuid\Uuid::fromBytes($g->gallery_uuid)->toString();
                        } catch (\Throwable) {
                            $uuidStr = bin2hex($g->gallery_uuid);
                        }
                    } else {
                        $uuidStr = (string) $g->gallery_uuid;
                    }
                }

                return [
                    'gallery_id' => (int) $g->gallery_id,
                    'gallery_title' => $g->gallery_title,
                    'gallery_uuid' => $uuidStr,
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
     * Resolve granular stage pipeline breakdown, active stage, and completion percentage.
     */
    public function resolveStageDetails(MediaJob $job): array
    {
        $status = $job->status ?? 'queued';
        $progress = (string) ($job->progress ?? '');
        $mediaType = $job->photo?->media_type ?? 'photo';
        if (empty($mediaType) && $job->photo) {
            $mediaType = $job->photo->isVideo() ? 'video' : 'photo';
        }

        $error = $job->error_message ?: $job->error;

        if ($mediaType === 'video') {
            return $this->resolveVideoStages($status, $progress, $error);
        }

        return $this->resolvePhotoStages($status, $progress, $error);
    }

    /**
     * Resolve pipeline stages for Photo jobs.
     */
    protected function resolvePhotoStages(string $status, string $progress, ?string $error): array
    {
        $baseStages = [
            [
                'id' => 'queued',
                'name' => 'Queued for Worker',
                'target_percentage' => 5,
                'description' => 'Job enqueued in Redis waiting for an available background worker.',
            ],
            [
                'id' => 'download',
                'name' => 'Downloading Original',
                'target_percentage' => 20,
                'description' => 'Fetching full-resolution source file from Backblaze B2 storage into worker memory.',
            ],
            [
                'id' => 'metadata',
                'name' => 'Extracting Metadata',
                'target_percentage' => 35,
                'description' => 'Parsing EXIF camera metadata (make, model, lens, focal length, ISO, shutter, aperture & orientation).',
            ],
            [
                'id' => 'webp',
                'name' => 'Generating WebP Variants',
                'target_percentage' => 70,
                'description' => 'Downscaling & lossy WebP compression for responsive sizes (XL, LG, MD, SM, XS) and uploading to B2.',
            ],
            [
                'id' => 'blurhash',
                'name' => 'Generating BlurHash',
                'target_percentage' => 85,
                'description' => 'Computing ultra-compact BlurHash placeholder string from XS variant for instant blurred preview.',
            ],
            [
                'id' => 'statistics',
                'name' => 'Saving & Finalizing',
                'target_percentage' => 95,
                'description' => 'Committing image dimensions, orientation, and EXIF records to database, setting photo status to ready.',
            ],
            [
                'id' => 'completed',
                'name' => 'Completed',
                'target_percentage' => 100,
                'description' => 'Optimization finished; media ready for viewing, sharing, and client download.',
            ],
        ];

        $lowerProgress = strtolower($progress);
        $stageIndex = 0;
        $currentLabel = 'Queued';
        $computedPercentage = 0;

        if ($status === 'completed') {
            $stageIndex = 6;
            $currentLabel = 'Completed';
            $computedPercentage = 100;
        } elseif ($status === 'queued') {
            $stageIndex = 0;
            $currentLabel = 'Queued';
            $computedPercentage = 0;
        } else {
            if (str_contains($lowerProgress, 'download')) {
                $stageIndex = 1;
                $currentLabel = 'Downloading Original';
                $computedPercentage = 20;
            } elseif (str_contains($lowerProgress, 'metadata') || str_contains($lowerProgress, 'exif')) {
                $stageIndex = 2;
                $currentLabel = 'Extracting Metadata';
                $computedPercentage = 35;
            } elseif (str_contains($lowerProgress, 'webp') || str_contains($lowerProgress, 'generating & uploading') || str_contains($lowerProgress, 'variant')) {
                $stageIndex = 3;
                $currentLabel = !empty($progress) ? $progress : 'Generating WebP Variants';
                if (str_contains($lowerProgress, 'xl')) {
                    $computedPercentage = 45;
                } elseif (str_contains($lowerProgress, 'lg')) {
                    $computedPercentage = 55;
                } elseif (str_contains($lowerProgress, 'md')) {
                    $computedPercentage = 65;
                } elseif (str_contains($lowerProgress, 'sm')) {
                    $computedPercentage = 75;
                } elseif (str_contains($lowerProgress, 'xs')) {
                    $computedPercentage = 80;
                } else {
                    $computedPercentage = 60;
                }
            } elseif (str_contains($lowerProgress, 'blurhash')) {
                $stageIndex = 4;
                $currentLabel = 'Generating BlurHash';
                $computedPercentage = 85;
            } elseif (str_contains($lowerProgress, 'statistic') || str_contains($lowerProgress, 'updating') || str_contains($lowerProgress, 'finaliz')) {
                $stageIndex = 5;
                $currentLabel = 'Saving Statistics';
                $computedPercentage = 95;
            } elseif (str_contains($lowerProgress, 'completed') || str_contains($lowerProgress, 'finalized')) {
                $stageIndex = 6;
                $currentLabel = 'Completed';
                $computedPercentage = 100;
            } else {
                $stageIndex = 1;
                $currentLabel = !empty($progress) ? $progress : 'Processing';
                $computedPercentage = 20;
            }
        }

        $stages = [];
        foreach ($baseStages as $idx => $s) {
            $stageStatus = 'pending';
            if ($status === 'completed') {
                $stageStatus = 'completed';
            } elseif ($status === 'failed') {
                if ($idx < $stageIndex) {
                    $stageStatus = 'completed';
                } elseif ($idx === $stageIndex) {
                    $stageStatus = 'failed';
                } else {
                    $stageStatus = 'pending';
                }
            } else {
                if ($idx < $stageIndex) {
                    $stageStatus = 'completed';
                } elseif ($idx === $stageIndex) {
                    $stageStatus = ($status === 'queued') ? 'queued' : 'current';
                } else {
                    $stageStatus = 'pending';
                }
            }

            $stages[] = [
                'id' => $s['id'],
                'step_number' => $idx + 1,
                'name' => $s['name'],
                'target_percentage' => $s['target_percentage'],
                'description' => $s['description'],
                'status' => $stageStatus,
            ];
        }

        return [
            'media_type' => 'photo',
            'percentage' => $computedPercentage,
            'current_stage_label' => $currentLabel,
            'current_stage_index' => $stageIndex,
            'stages' => $stages,
        ];
    }

    /**
     * Resolve pipeline stages for Video jobs.
     */
    protected function resolveVideoStages(string $status, string $progress, ?string $error): array
    {
        $baseStages = [
            [
                'id' => 'queued',
                'name' => 'Queued for Worker',
                'target_percentage' => 5,
                'description' => 'Video job waiting in Redis queue for worker pick-up.',
            ],
            [
                'id' => 'download',
                'name' => 'Downloading Original Video',
                'target_percentage' => 20,
                'description' => 'Streaming source video file from Backblaze B2 storage into local scratch directory.',
            ],
            [
                'id' => 'probe',
                'name' => 'Analyzing Video Metadata',
                'target_percentage' => 35,
                'description' => 'Executing ffprobe to inspect video codec, resolution, framerate, duration, and audio stream.',
            ],
            [
                'id' => 'quota',
                'name' => 'Committing Quota',
                'target_percentage' => 45,
                'description' => 'Atomically verifying photographer plan video duration limits and locking usage quota.',
            ],
            [
                'id' => 'poster',
                'name' => 'Extracting Poster & BlurHash',
                'target_percentage' => 60,
                'description' => 'Extracting high-resolution poster frame thumbnail and computing preview BlurHash.',
            ],
            [
                'id' => 'delivery',
                'name' => 'Preparing Web Delivery',
                'target_percentage' => 75,
                'description' => 'Fast-remuxing or H.264/AAC transcoding optimized for progressive web streaming playback.',
            ],
            [
                'id' => 'upload',
                'name' => 'Uploading Delivery Files',
                'target_percentage' => 90,
                'description' => 'Transferring streaming delivery MP4 and poster images back to Backblaze B2 storage.',
            ],
            [
                'id' => 'statistics',
                'name' => 'Finalizing Video',
                'target_percentage' => 95,
                'description' => 'Persisting duration, resolutions, stream URLs, and setting status to ready.',
            ],
            [
                'id' => 'completed',
                'name' => 'Completed',
                'target_percentage' => 100,
                'description' => 'Video delivery fully prepared and ready for streaming in client gallery.',
            ],
        ];

        $lowerProgress = strtolower($progress);
        $stageIndex = 0;
        $currentLabel = 'Queued';
        $computedPercentage = 0;

        if ($status === 'completed') {
            $stageIndex = 8;
            $currentLabel = 'Completed';
            $computedPercentage = 100;
        } elseif ($status === 'queued') {
            $stageIndex = 0;
            $currentLabel = 'Queued';
            $computedPercentage = 0;
        } else {
            if (str_contains($lowerProgress, 'download')) {
                $stageIndex = 1;
                $currentLabel = 'Downloading Original';
                $computedPercentage = 20;
            } elseif (str_contains($lowerProgress, 'metadata') || str_contains($lowerProgress, 'probe') || str_contains($lowerProgress, 'analyz')) {
                $stageIndex = 2;
                $currentLabel = 'Analyzing Video Metadata';
                $computedPercentage = 35;
            } elseif (str_contains($lowerProgress, 'quota') || str_contains($lowerProgress, 'committ')) {
                $stageIndex = 3;
                $currentLabel = 'Committing Quota';
                $computedPercentage = 45;
            } elseif (str_contains($lowerProgress, 'poster')) {
                $stageIndex = 4;
                $currentLabel = 'Extracting Poster';
                $computedPercentage = 60;
            } elseif (str_contains($lowerProgress, 'delivery') || str_contains($lowerProgress, 'prepar') || str_contains($lowerProgress, 'transcod')) {
                $stageIndex = 5;
                $currentLabel = 'Preparing Web Delivery';
                $computedPercentage = 75;
            } elseif (str_contains($lowerProgress, 'upload')) {
                $stageIndex = 6;
                $currentLabel = 'Uploading Delivery Files';
                $computedPercentage = 90;
            } elseif (str_contains($lowerProgress, 'finaliz') || str_contains($lowerProgress, 'statistic')) {
                $stageIndex = 7;
                $currentLabel = 'Finalizing Video';
                $computedPercentage = 95;
            } elseif (str_contains($lowerProgress, 'completed')) {
                $stageIndex = 8;
                $currentLabel = 'Completed';
                $computedPercentage = 100;
            } else {
                $stageIndex = 1;
                $currentLabel = !empty($progress) ? $progress : 'Processing';
                $computedPercentage = 20;
            }
        }

        $stages = [];
        foreach ($baseStages as $idx => $s) {
            $stageStatus = 'pending';
            if ($status === 'completed') {
                $stageStatus = 'completed';
            } elseif ($status === 'failed') {
                if ($idx < $stageIndex) {
                    $stageStatus = 'completed';
                } elseif ($idx === $stageIndex) {
                    $stageStatus = 'failed';
                } else {
                    $stageStatus = 'pending';
                }
            } else {
                if ($idx < $stageIndex) {
                    $stageStatus = 'completed';
                } elseif ($idx === $stageIndex) {
                    $stageStatus = ($status === 'queued') ? 'queued' : 'current';
                } else {
                    $stageStatus = 'pending';
                }
            }

            $stages[] = [
                'id' => $s['id'],
                'step_number' => $idx + 1,
                'name' => $s['name'],
                'target_percentage' => $s['target_percentage'],
                'description' => $s['description'],
                'status' => $stageStatus,
            ];
        }

        return [
            'media_type' => 'video',
            'percentage' => $computedPercentage,
            'current_stage_label' => $currentLabel,
            'current_stage_index' => $stageIndex,
            'stages' => $stages,
        ];
    }

    /**
     * Transform a single MediaJob record for consistent API and frontend consumption.
     */
    public function transformMediaJob(MediaJob $job): array
    {
        $stageInfo = $this->resolveStageDetails($job);

        return [
            'id' => $job->id,
            'job_uuid' => $job->job_uuid,
            'job_name' => class_basename($job->job_name ?: ($job->job_type ?: 'ProcessPhotoJob')),
            'queue' => $job->queue ?: 'photos',
            'photo_uuid' => $job->photo?->uuid,
            'original_filename' => $job->photo?->original_filename ?: ($job->photo?->filename ?: 'photo.jpg'),
            'gallery_title' => $job->photo?->gallery?->title ?: 'N/A',
            'studio_name' => $job->photo?->gallery?->user?->name ?: 'N/A',
            'media_type' => $stageInfo['media_type'],
            'status' => $job->status,
            'progress' => $job->progress ?: ($job->status === 'completed' ? 'Finalized' : ($job->status === 'failed' ? 'Failed' : 'Queued')),
            'percentage' => $stageInfo['percentage'],
            'stage_label' => $stageInfo['current_stage_label'],
            'stages' => $stageInfo['stages'],
            'attempts' => $job->attempts,
            'max_attempts' => $job->max_attempts ?? 3,
            'duration_ms' => $job->duration_ms,
            'error_message' => $job->error_message ?: $job->error,
            'started_at' => $job->started_at?->toIso8601String(),
            'completed_at' => $job->completed_at?->toIso8601String(),
            'failed_at' => $job->failed_at?->toIso8601String(),
            'created_at' => $job->created_at?->toIso8601String(),
            'file_size' => $job->photo?->size,
            'width' => $job->photo?->width,
            'height' => $job->photo?->height,
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
