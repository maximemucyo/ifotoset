<?php

namespace App\Queries\Studio;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class StudioAnalyticsQuery
{
    /**
     * Compute bounded, indexed analytics strictly isolated to the authenticated photographer.
     */
    public function get(User $user, string $period = '30d'): array
    {
        $days = match ($period) {
            '7d'  => 7,
            '90d' => 90,
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
            $totalViews = DB::table('activity_logs')
                ->whereIn('gallery_id', $galleryIds)
                ->where('event', 'gallery_viewed')
                ->where('created_at', '>=', $since)
                ->count();

            $prevViews = DB::table('activity_logs')
                ->whereIn('gallery_id', $galleryIds)
                ->where('event', 'gallery_viewed')
                ->where('created_at', '>=', $prevSince)
                ->where('created_at', '<', $since)
                ->count();

            $totalDownloads = DB::table('activity_logs')
                ->whereIn('gallery_id', $galleryIds)
                ->where('event', 'photo_downloaded')
                ->where('created_at', '>=', $since)
                ->count();

            $prevDownloads = DB::table('activity_logs')
                ->whereIn('gallery_id', $galleryIds)
                ->where('event', 'photo_downloaded')
                ->where('created_at', '>=', $prevSince)
                ->where('created_at', '<', $since)
                ->count();

            $uniqueVisitors = DB::table('activity_logs')
                ->whereIn('gallery_id', $galleryIds)
                ->whereNotNull('visitor_session_id')
                ->where('created_at', '>=', $since)
                ->distinct()
                ->count('visitor_session_id');

            $galleryShares = DB::table('activity_logs')
                ->whereIn('gallery_id', $galleryIds)
                ->where('event', 'gallery_shared')
                ->where('created_at', '>=', $since)
                ->count();
        }

        $viewsChange = $prevViews > 0 ? round((($totalViews - $prevViews) / $prevViews) * 100, 1) : 0.0;
        $downloadsChange = $prevDownloads > 0 ? round((($totalDownloads - $prevDownloads) / $prevDownloads) * 100, 1) : 0.0;

        // Last 12 months view distribution for SVG bar chart
        $monthlyViews = [];
        $maxMonthlyView = 1;
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
                $count = $monthlyStats[$monthStr] ?? 0;
                if ($count > $maxMonthlyView) {
                    $maxMonthlyView = $count;
                }
                $monthlyViews[] = [
                    'label' => now()->subMonths($i)->format('M'),
                    'month' => $monthStr,
                    'count' => $count,
                ];
            }
        } else {
            for ($i = 11; $i >= 0; $i--) {
                $monthlyViews[] = [
                    'label' => now()->subMonths($i)->format('M'),
                    'month' => now()->subMonths($i)->format('Y-m'),
                    'count' => 0,
                ];
            }
        }

        // Top Performing Galleries
        $topGalleries = [];
        if ($galleryCount > 0) {
            $topGalleries = DB::table('activity_logs')
                ->join('galleries', 'activity_logs.gallery_id', '=', 'galleries.id')
                ->whereIn('activity_logs.gallery_id', $galleryIds)
                ->where('activity_logs.created_at', '>=', $since)
                ->select(
                    DB::raw('BIN_TO_UUID(galleries.uuid) as uuid'),
                    'galleries.title',
                    'galleries.slug',
                    DB::raw('SUM(CASE WHEN activity_logs.event = "gallery_viewed" THEN 1 ELSE 0 END) as views_count'),
                    DB::raw('SUM(CASE WHEN activity_logs.event = "photo_downloaded" THEN 1 ELSE 0 END) as downloads_count')
                )
                ->groupBy('galleries.uuid', 'galleries.title', 'galleries.slug')
                ->orderBy('views_count', 'desc')
                ->take(5)
                ->get();
        }

        return [
            'period'           => $period,
            'totalViews'       => $totalViews,
            'viewsChange'      => $viewsChange,
            'totalDownloads'   => $totalDownloads,
            'downloadsChange'  => $downloadsChange,
            'uniqueVisitors'   => $uniqueVisitors,
            'galleryShares'    => $galleryShares,
            'monthlyViews'     => $monthlyViews,
            'maxMonthlyView'   => $maxMonthlyView,
            'topGalleries'     => $topGalleries,
        ];
    }
}
