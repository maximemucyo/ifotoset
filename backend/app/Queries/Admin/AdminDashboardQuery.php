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
        $totalRevenue = Payment::where('status', 'completed')->sum('amount') ?: 0;

        $recentUsers = User::withCount('galleries')
            ->orderBy('created_at', 'desc')
            ->take(6)
            ->get();

        $recentGalleries = Gallery::with(['user', 'stats', 'coverPhoto'])
            ->orderBy('created_at', 'desc')
            ->take(6)
            ->get();

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
        ];
    }
}
