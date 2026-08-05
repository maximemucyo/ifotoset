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
}
