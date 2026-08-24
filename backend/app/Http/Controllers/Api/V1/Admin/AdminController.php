<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\Admin\AdminDashboardResource;
use App\Http\Resources\V1\Admin\AdminGalleryResource;
use App\Http\Resources\V1\Admin\MediaJobResource;
use App\Http\Resources\V1\Admin\UserResource;
use App\Models\Gallery;
use App\Models\MediaJob;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Cache;
use App\Models\SystemSetting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class AdminController extends Controller
{
    /**
     * Get aggregated statistics, recent users, recent galleries, and queue monitor metrics.
     * GET /api/v1/admin/dashboard
     */
    public function dashboard(Request $request): AdminDashboardResource
    {
        // Cache stats for 10 minutes (auto-invalidated on saved/deleted events of related models)
        $stats = Cache::remember('admin_dashboard_stats', now()->addMinutes(10), function () {
            return [
                'total_users' => (int) User::count(),
                'active_galleries' => (int) Gallery::count(),
                'total_storage_bytes' => (int) User::sum('storage_used_bytes'),
                'total_revenue' => (float) Payment::where('status', 'completed')->sum('amount'),
            ];
        });

        // Current queue counts (not cached, to keep them live)
        $queueStats = [
            'queued' => (int) MediaJob::where('status', 'queued')->count(),
            'processing' => (int) MediaJob::where('status', 'processing')->count(),
            'completed' => (int) MediaJob::where('status', 'completed')->count(),
            'failed' => (int) MediaJob::where('status', 'failed')->count(),
        ];

        // Recent users
        $recentUsers = User::withCount('galleries')
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get();

        // Recent galleries
        $recentGalleries = Gallery::with(['user', 'stats'])
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get();

        // Recent queue jobs
        $recentJobs = MediaJob::with('photo.gallery.user')
            ->orderBy('id', 'desc')
            ->take(10)
            ->get();

        return new AdminDashboardResource([
            'stats' => $stats,
            'queue_stats' => $queueStats,
            'recent_users' => $recentUsers,
            'recent_galleries' => $recentGalleries,
            'queue_jobs' => $recentJobs,
        ]);
    }

    /**
     * Get a paginated list of queue jobs.
     * GET /api/v1/admin/queue
     */
    public function queue(Request $request): AnonymousResourceCollection
    {
        $status = $request->query('status');
        $search = $request->query('search');

        $query = MediaJob::with('photo.gallery.user');

        if ($status && in_array($status, ['queued', 'processing', 'completed', 'failed'])) {
            $query->where('status', $status);
        }

        if ($search) {
            $query->whereHas('photo', function ($q) use ($search) {
                $q->where('filename', 'like', "%{$search}%")
                  ->orWhere('original_filename', 'like', "%{$search}%")
                  ->orWhereHas('gallery', function ($gq) use ($search) {
                      $gq->where('title', 'like', "%{$search}%")
                        ->orWhereHas('user', function ($uq) use ($search) {
                            $uq->where('name', 'like', "%{$search}%");
                        });
                  });
            });
        }

        $jobs = $query->orderBy('id', 'desc')->paginate(10);

        return MediaJobResource::collection($jobs);
    }

    /**
     * Get a paginated list of users.
     * GET /api/v1/admin/users
     */
    public function users(Request $request): AnonymousResourceCollection
    {
        $search = $request->query('search');
        $plan = $request->query('plan');

        $query = User::withCount('galleries')->with('plan');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($plan) {
            $query->whereHas('plan', function ($q) use ($plan) {
                $q->where('slug', $plan);
            });
        }

        $users = $query->orderBy('created_at', 'desc')->paginate(10);

        return UserResource::collection($users);
    }

    /**
     * Get a paginated list of galleries.
     * GET /api/v1/admin/galleries
     */
    public function galleries(Request $request): AnonymousResourceCollection
    {
        $search = $request->query('search');

        $query = Gallery::with(['user', 'stats']);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhereHas('user', function ($uq) use ($search) {
                      $uq->where('name', 'like', "%{$search}%");
                  });
            });
        }

        $galleries = $query->orderBy('created_at', 'desc')->paginate(10);

        return AdminGalleryResource::collection($galleries);
    }

    /**
     * Get the SMTP settings.
     * GET /api/v1/admin/settings/smtp
     */
    public function getSmtpSettings(Request $request): JsonResponse
    {
        $password = SystemSetting::getOption('smtp_password');
        $fallbackPassword = config('mail.mailers.smtp.password');

        return response()->json([
            'data' => [
                'host' => SystemSetting::getOption('smtp_host', config('mail.mailers.smtp.host', 'smtp.gmail.com')),
                'port' => SystemSetting::getOption('smtp_port', config('mail.mailers.smtp.port', '587')),
                'username' => SystemSetting::getOption('smtp_username', config('mail.mailers.smtp.username', 'ifotoset1@gmail.com')),
                'encryption' => SystemSetting::getOption('smtp_encryption', config('mail.mailers.smtp.encryption', 'tls')),
                'from_address' => SystemSetting::getOption('smtp_from_address', config('mail.from.address', 'ifotoset1@gmail.com')),
                'from_name' => SystemSetting::getOption('smtp_from_name', config('mail.from.name', 'ifotoset')),
                'has_password' => !empty($password) || !empty($fallbackPassword),
            ]
        ]);
    }

    /**
     * Update the SMTP settings and optionally send a test email.
     * PUT /api/v1/admin/settings/smtp
     */
    public function updateSmtpSettings(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'host' => ['required', 'string'],
            'port' => ['required', 'integer'],
            'username' => ['required', 'string'],
            'password' => ['nullable', 'string'],
            'encryption' => ['nullable', 'string'],
            'from_address' => ['required', 'email'],
            'from_name' => ['required', 'string'],
            'test_email' => ['nullable', 'email'],
        ]);

        SystemSetting::setOption('smtp_host', $validated['host']);
        SystemSetting::setOption('smtp_port', (string) $validated['port']);
        SystemSetting::setOption('smtp_username', $validated['username']);
        
        // If password is submitted (and not placeholder/empty), update it in database
        $passwordUpdated = false;
        if ($request->has('password') && $validated['password'] !== null && $validated['password'] !== '********' && $validated['password'] !== '') {
            SystemSetting::setOption('smtp_password', $validated['password']);
            $passwordUpdated = true;
        }
        
        SystemSetting::setOption('smtp_encryption', $validated['encryption'] ?? 'tls');
        SystemSetting::setOption('smtp_from_address', $validated['from_address']);
        SystemSetting::setOption('smtp_from_name', $validated['from_name']);

        // Write a secure audit log entry in the activity_logs table
        try {
            DB::table('activity_logs')->insert([
                'user_id' => $request->user()?->id,
                'event' => 'smtp_settings_updated',
                'properties' => json_encode([
                    'host' => $validated['host'],
                    'port' => $validated['port'],
                    'username' => $validated['username'],
                    'encryption' => $validated['encryption'] ?? 'tls',
                    'from_address' => $validated['from_address'],
                    'from_name' => $validated['from_name'],
                    'password_updated' => $passwordUpdated,
                ]),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'created_at' => now(),
            ]);
        } catch (\Exception $e) {
            Log::warning('Failed to write SMTP update audit log: ' . $e->getMessage());
        }

        // Dynamically configure Laravel Mail configs for the current process
        config([
            'mail.mailers.smtp.host' => $validated['host'],
            'mail.mailers.smtp.port' => $validated['port'],
            'mail.mailers.smtp.username' => $validated['username'],
            'mail.mailers.smtp.encryption' => $validated['encryption'] ?? 'tls',
            'mail.from.address' => $validated['from_address'],
            'mail.from.name' => $validated['from_name'],
        ]);
        
        if ($passwordUpdated) {
            config(['mail.mailers.smtp.password' => $validated['password']]);
        } else {
            $dbPassword = SystemSetting::getOption('smtp_password');
            if ($dbPassword !== null) {
                config(['mail.mailers.smtp.password' => $dbPassword]);
            }
        }

        // Restart queue workers so they reload the new configuration
        try {
            Artisan::call('queue:restart');
        } catch (\Exception $e) {
            Log::error('Failed to restart queue workers during SMTP update: ' . $e->getMessage());
        }

        $testSent = false;
        $testError = null;

        if (!empty($validated['test_email'])) {
            try {
                $recipient = $validated['test_email'];
                Mail::raw('This is a test email verifying your ifotoset SMTP configuration.', function ($message) use ($recipient) {
                    $message->to($recipient)->subject('ifotoset SMTP Configuration Test');
                });
                $testSent = true;
            } catch (\Exception $e) {
                // Log detailed error internally for administration debugging
                Log::error('SMTP Test email failed: ' . $e->getMessage(), [
                    'exception' => $e
                ]);
                
                // Sanitize error message to prevent exposing low level library dumps
                $lowerMsg = strtolower($e->getMessage());
                if (str_contains($lowerMsg, 'authentication') || str_contains($lowerMsg, 'username') || str_contains($lowerMsg, 'password') || str_contains($lowerMsg, 'credentials')) {
                    $testError = 'SMTP authentication failed. Please verify the host, port, username, password, and encryption settings.';
                } else if (str_contains($lowerMsg, 'connection') || str_contains($lowerMsg, 'connect') || str_contains($lowerMsg, 'timeout') || str_contains($lowerMsg, 'resolv')) {
                    $testError = 'SMTP connection failed. Please verify the server host, port, and security (SSL/TLS) configuration.';
                } else {
                    $testError = 'Failed to send test email. Please check your SMTP settings and try again.';
                }
            }
        }

        return response()->json([
            'message' => 'SMTP settings updated successfully.',
            'test_sent' => $testSent,
            'test_error' => $testError,
        ]);
    }

    /**
     * Get recent and active exports (Zips and Google Photo syncs).
     * GET /api/v1/admin/exports
     */
    public function exports(Request $request): JsonResponse
    {
        $downloadsQuery = \Illuminate\Support\Facades\DB::table('gallery_downloads')
            ->join('galleries', 'gallery_downloads.gallery_id', '=', 'galleries.id')
            ->join('users', 'galleries.user_id', '=', 'users.id')
            ->select([
                'gallery_downloads.id',
                \Illuminate\Support\Facades\DB::raw("'zip' as type"),
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
                'gallery_downloads.created_at'
            ]);

        $syncsQuery = \Illuminate\Support\Facades\DB::table('google_photo_syncs')
            ->join('galleries', 'google_photo_syncs.gallery_id', '=', 'galleries.id')
            ->join('users', 'galleries.user_id', '=', 'users.id')
            ->select([
                'google_photo_syncs.id',
                \Illuminate\Support\Facades\DB::raw("'google-photos' as type"),
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
                'google_photo_syncs.created_at'
            ]);

        $unionQuery = $downloadsQuery->unionAll($syncsQuery);

        $jobs = \Illuminate\Support\Facades\DB::query()
            ->fromSub($unionQuery, 'combined')
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        $progressService = app(\App\Services\ExportProgressService::class);

        $items = collect($jobs->items())->map(function ($job) use ($progressService) {
            $startedAt = $job->started_at ? \Carbon\Carbon::parse($job->started_at) : null;
            $completedAt = $job->completed_at ? \Carbon\Carbon::parse($job->completed_at) : null;

            $progress = $progressService->calculate(
                $startedAt,
                $completedAt,
                $job->total_photos,
                $job->processed_photos,
                $job->failed_photos,
                $job->status
            );

            return [
                'id' => $job->id,
                'type' => $job->type,
                'gallery_title' => $job->gallery_title,
                'studio_name' => $job->studio_name,
                'status' => $job->status,
                'email' => $this->maskEmail($job->email),
                'notify_when_ready' => (bool)$job->notify_when_ready,
                'total_photos' => (int)$job->total_photos,
                'processed_photos' => (int)$job->processed_photos,
                'failed_photos' => (int)$job->failed_photos,
                'error' => $job->error,
                'started_at' => $startedAt ? $startedAt->toIso8601String() : null,
                'completed_at' => $completedAt ? $completedAt->toIso8601String() : null,
                'created_at' => \Carbon\Carbon::parse($job->created_at)->toIso8601String(),
                'percentage' => $progress['percentage'],
                'remaining_seconds' => $progress['remaining_seconds'],
                'estimated_finish_time' => $progress['estimated_finish_time'],
            ];
        });

        return response()->json([
            'data' => $items,
            'links' => [
                'first' => $jobs->url(1),
                'last' => $jobs->url($jobs->lastPage()),
                'prev' => $jobs->previousPageUrl(),
                'next' => $jobs->nextPageUrl(),
            ],
            'meta' => [
                'current_page' => $jobs->currentPage(),
                'from' => $jobs->firstItem(),
                'last_page' => $jobs->lastPage(),
                'path' => $jobs->path(),
                'per_page' => $jobs->perPage(),
                'to' => $jobs->lastItem(),
                'total' => $jobs->total(),
            ]
        ]);
    }

    /**
     * Dynamic deterministic email masking.
     */
    private function maskEmail(?string $email): ?string
    {
        if (!$email) {
            return null;
        }

        $parts = explode('@', $email);
        if (count($parts) !== 2) {
            return substr($email, 0, 3) . '***';
        }

        $local = $parts[0];
        $domain = $parts[1];
        
        $len = strlen($local);
        if ($len <= 2) {
            $maskedLocal = str_repeat('*', $len);
        } elseif ($len === 3) {
            $maskedLocal = $local[0] . '**';
        } else {
            $visibleLen = min(3, (int)ceil($len / 3));
            $maskedLocal = substr($local, 0, $visibleLen) . str_repeat('*', $len - $visibleLen);
        }

        return $maskedLocal . '@' . $domain;
    }
}
