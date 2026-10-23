<?php

namespace App\Queries\Admin;

use App\Models\Gallery;
use App\Models\Payment;
use App\Models\User;

class AdminDashboardQuery
{
    /**
     * Fetch platform-wide summary metrics for superadmins.
     */
    public function get(): array
    {
        $totalUsers = User::count();
        $totalGalleries = Gallery::count();
        $totalStorageBytes = User::sum('storage_used_bytes') ?: 0;
        // Booking deposits are client money for photographers, not platform revenue
        $totalRevenue = Payment::where('status', 'completed')->where('purpose', '!=', 'booking_deposit')->sum('amount') ?: 0;

        $recentUsers = User::withCount('galleries')
            ->orderBy('created_at', 'desc')
            ->take(6)
            ->get();

        $recentGalleries = Gallery::with(['user', 'stats', 'coverPhoto'])
            ->orderBy('created_at', 'desc')
            ->take(6)
            ->get();

        $recentPayments = Payment::with(['user', 'plan'])
            ->orderBy('created_at', 'desc')
            ->take(6)
            ->get();

        $newUsers30d = User::where('created_at', '>=', now()->subDays(30))->count();
        $takenDownGalleries = Gallery::where('moderation_status', 'taken_down')->count();

        $queueSummary = [
            'processing'   => \App\Models\MediaJob::where('status', 'processing')->count(),
            'queued'       => \App\Models\MediaJob::where('status', 'queued')->count(),
            'failed_today' => \App\Models\MediaJob::where('status', 'failed')
                ->where('failed_at', '>=', now()->startOfDay())
                ->count(),
        ];

        return [
            'totalUsers'        => $totalUsers,
            'totalGalleries'    => $totalGalleries,
            'totalStorageBytes' => $totalStorageBytes,
            'totalRevenue'      => $totalRevenue,
            'recentUsers'       => $recentUsers,
            'recentGalleries'   => $recentGalleries,
            'queueSummary'      => $queueSummary,
            'recentPayments'    => $recentPayments,
            'newUsers30d'       => $newUsers30d,
            'takenDownGalleries' => $takenDownGalleries,
        ];
    }
}
