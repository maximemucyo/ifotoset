<?php

namespace App\Queries\Admin;

use App\Models\Gallery;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AdminAnalyticsQuery
{
    /**
     * Compute platform-wide analytics and growth trends.
     */
    public function get(): array
    {
        $totalUsers = User::count();
        $activeStudios = User::whereHas('galleries')->count();
        $totalGalleries = Gallery::count();
        $totalDownloads = DB::table('activity_logs')->where('event', 'photo_downloaded')->count();

        // 12-month user signup growth
        $userGrowth = [];
        $maxGrowth = 1;
        $signupStats = User::where('created_at', '>=', now()->subMonths(11)->startOfMonth())
            ->select(DB::raw('DATE_FORMAT(created_at, "%Y-%m") as month_label'), DB::raw('COUNT(*) as count'))
            ->groupBy('month_label')
            ->get()
            ->pluck('count', 'month_label')
            ->toArray();

        for ($i = 11; $i >= 0; $i--) {
            $monthStr = now()->subMonths($i)->format('Y-m');
            $count = $signupStats[$monthStr] ?? 0;
            if ($count > $maxGrowth) {
                $maxGrowth = $count;
            }
            $userGrowth[] = [
                'label' => now()->subMonths($i)->format('M'),
                'month' => $monthStr,
                'count' => $count,
            ];
        }

        // Top Performing Studios
        $topStudios = User::withCount('galleries')
            ->whereHas('galleries')
            ->take(6)
            ->get()
            ->map(function ($studio) {
                $galleryIds = $studio->galleries()->pluck('id')->toArray();
                $views = DB::table('activity_logs')
                    ->whereIn('gallery_id', $galleryIds)
                    ->where('event', 'gallery_viewed')
                    ->count();

                $revenue = Payment::where('user_id', $studio->id)
                    ->where('status', 'completed')
                    ->sum('amount') ?: 0;

                return [
                    'name'            => $studio->name,
                    'username'        => $studio->username,
                    'galleries_count' => $studio->galleries_count,
                    'views'           => $views,
                    'revenue'         => $revenue,
                    'joined'          => $studio->created_at->format('M Y'),
                ];
            });

        return [
            'totalUsers'     => $totalUsers,
            'activeStudios'  => $activeStudios,
            'totalGalleries' => $totalGalleries,
            'totalDownloads' => $totalDownloads,
            'userGrowth'     => $userGrowth,
            'maxGrowth'      => $maxGrowth,
            'topStudios'     => $topStudios,
        ];
    }
}
