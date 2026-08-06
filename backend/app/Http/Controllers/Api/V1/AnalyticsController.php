<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AnalyticsController extends Controller
{
    /**
     * Get aggregated analytics for the authenticated photographer.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $period = $request->input('period', '30d');
        $days = match ($period) {
            '7d' => 7,
            '90d' => 90,
            '30d' => 30,
            default => 30,
        };

        $since = now()->subDays($days);
        $prevSince = now()->subDays($days * 2);

        $galleryIds = $user->galleries()->pluck('id')->toArray();
        $galleryCount = count($galleryIds);

        $totalViews = 0;
        $prevViews = 0;
        $totalDownloads = 0;
        $prevDownloads = 0;
        $uniqueVisitors = 0;
        $galleryShares = 0;

        if ($galleryCount > 0) {
            // Total Views
            $totalViews = DB::table('activity_logs')
                ->whereIn('gallery_id', $galleryIds)
                ->where('event', 'gallery_viewed')
                ->where('created_at', '>=', $since)
                ->count();

            // Previous Period Views
            $prevViews = DB::table('activity_logs')
                ->whereIn('gallery_id', $galleryIds)
                ->where('event', 'gallery_viewed')
                ->where('created_at', '>=', $prevSince)
                ->where('created_at', '<', $since)
                ->count();

            // Total Downloads
            $totalDownloads = DB::table('activity_logs')
                ->whereIn('gallery_id', $galleryIds)
                ->where('event', 'photo_downloaded')
                ->where('created_at', '>=', $since)
                ->count();

            // Previous Period Downloads
            $prevDownloads = DB::table('activity_logs')
                ->whereIn('gallery_id', $galleryIds)
                ->where('event', 'photo_downloaded')
                ->where('created_at', '>=', $prevSince)
                ->where('created_at', '<', $since)
                ->count();

            // Unique Visitors (cookie session based)
            $uniqueVisitors = DB::table('activity_logs')
                ->whereIn('gallery_id', $galleryIds)
                ->whereNotNull('visitor_session_id')
                ->where('created_at', '>=', $since)
                ->distinct()
                ->count('visitor_session_id');

            // Gallery Shares
            $galleryShares = DB::table('activity_logs')
                ->whereIn('gallery_id', $galleryIds)
                ->where('event', 'gallery_shared')
                ->where('created_at', '>=', $since)
                ->count();
        }

        // Percentage changes
        $viewsChangePct = $prevViews > 0 ? round((($totalViews - $prevViews) / $prevViews) * 100, 1) : 0.0;
        $downloadsChangePct = $prevDownloads > 0 ? round((($totalDownloads - $prevDownloads) / $prevDownloads) * 100, 1) : 0.0;

        // Averages
        $avgViewsPerGallery = $galleryCount > 0 ? round($totalViews / $galleryCount, 1) : 0.0;
        $avgDownloadsPerGallery = $galleryCount > 0 ? round($totalDownloads / $galleryCount, 1) : 0.0;

        // Last 12 months view distribution
        $monthlyViews = [];
        if ($galleryCount > 0) {
            $monthlyStats = DB::table('activity_logs')
                ->whereIn('gallery_id', $galleryIds)
                ->where('event', 'gallery_viewed')
                ->where('created_at', '>=', now()->subMonths(11)->startOfMonth())
                ->select(DB::raw('DATE_FORMAT(created_at, "%Y-%m") as month_label'), DB::raw('COUNT(*) as count'))
                ->groupBy('month_label')
                ->get()
                ->pluck('count', 'month_label')
                ->toArray();

            for ($i = 11; $i >= 0; $i--) {
                $monthStr = now()->subMonths($i)->format('Y-m');
                $monthlyViews[] = [
                    'month' => $monthStr,
                    'count' => $monthlyStats[$monthStr] ?? 0,
                ];
            }
        } else {
            for ($i = 11; $i >= 0; $i--) {
                $monthlyViews[] = [
                    'month' => now()->subMonths($i)->format('Y-m'),
                    'count' => 0,
                ];
            }
        }

        // Download distribution by source
        $downloadsBySource = [];
        if ($galleryCount > 0) {
            $sourceCounts = DB::table('activity_logs')
                ->whereIn('gallery_id', $galleryIds)
                ->where('event', 'photo_downloaded')
                ->where('created_at', '>=', $since)
                ->select(DB::raw('COALESCE(source, "direct") as clean_source'), DB::raw('COUNT(*) as count'))
                ->groupBy('clean_source')
                ->get();

            $totalSourceDownloads = $sourceCounts->sum('count');
            foreach ($sourceCounts as $row) {
                $pct = $totalSourceDownloads > 0 ? round(($row->count / $totalSourceDownloads) * 100, 1) : 0.0;
                $downloadsBySource[] = [
                    'source' => $row->clean_source,
                    'count' => $row->count,
                    'percent' => $pct,
                ];
            }
        }

        if (empty($downloadsBySource)) {
            $downloadsBySource = [
                ['source' => 'direct', 'count' => 0, 'percent' => 0.0],
                ['source' => 'email', 'count' => 0, 'percent' => 0.0],
                ['source' => 'social', 'count' => 0, 'percent' => 0.0],
            ];
        }

        // Top Performing Galleries
        $topGalleries = [];
        if ($galleryCount > 0) {
            $topGalleriesRaw = DB::table('activity_logs')
                ->join('galleries', 'activity_logs.gallery_id', '=', 'galleries.id')
                ->whereIn('activity_logs.gallery_id', $galleryIds)
                ->where('activity_logs.created_at', '>=', $since)
                ->select(
                    DB::raw('BIN_TO_UUID(galleries.uuid) as uuid'),
                    'galleries.title',
                    DB::raw('SUM(CASE WHEN activity_logs.event = "gallery_viewed" THEN 1 ELSE 0 END) as views_count'),
                    DB::raw('SUM(CASE WHEN activity_logs.event = "photo_downloaded" THEN 1 ELSE 0 END) as downloads_count'),
                    DB::raw('SUM(CASE WHEN activity_logs.event = "photo_favorited" THEN 1 ELSE 0 END) as favorites_count')
                )
                ->groupBy('galleries.uuid', 'galleries.title')
                ->orderBy('views_count', 'desc')
                ->take(5)
                ->get();

            foreach ($topGalleriesRaw as $row) {
                $topGalleries[] = [
                    'uuid' => $row->uuid,
                    'title' => $this->safeUtf8($row->title),
                    'views' => (int) $row->views_count,
                    'downloads' => (int) $row->downloads_count,
                    'favorites' => (int) $row->favorites_count,
                ];
            }
        }

        // Recent Activity log
        $recentActivity = [];
        if ($galleryCount > 0) {
            $recentActivityRaw = DB::table('activity_logs')
                ->join('galleries', 'activity_logs.gallery_id', '=', 'galleries.id')
                ->whereIn('activity_logs.gallery_id', $galleryIds)
                ->select('activity_logs.event', 'galleries.title as gallery_title', 'activity_logs.created_at')
                ->orderBy('activity_logs.created_at', 'desc')
                ->take(10)
                ->get();

            foreach ($recentActivityRaw as $row) {
                $recentActivity[] = [
                    'event' => $this->safeUtf8($row->event),
                    'gallery_title' => $this->safeUtf8($row->gallery_title),
                    'created_at' => (new \DateTime($row->created_at))->format(\DateTime::ATOM),
                ];
            }
        }

        return response()->json([
            'period' => $period,
            'overview' => [
                'total_views' => $totalViews,
                'prev_views' => $prevViews,
                'views_change_pct' => $viewsChangePct,
                'total_downloads' => $totalDownloads,
                'prev_downloads' => $prevDownloads,
                'downloads_change_pct' => $downloadsChangePct,
                'unique_visitors' => $uniqueVisitors,
                'gallery_shares' => $galleryShares,
                'avg_views_per_gallery' => $avgViewsPerGallery,
                'avg_downloads_per_gallery' => $avgDownloadsPerGallery,
            ],
            'monthly_views' => $monthlyViews,
            'downloads_by_source' => $downloadsBySource,
            'top_galleries' => $topGalleries,
            'recent_activity' => $recentActivity,
        ]);
    }

    /**
     * Strip any non-UTF-8 bytes from a string to prevent json_encode failures.
     */
    private function safeUtf8(?string $value): string
    {
        if ($value === null) {
            return '';
        }
        $result = iconv('UTF-8', 'UTF-8//IGNORE', $value);
        return $result === false ? '' : $result;
    }
}
