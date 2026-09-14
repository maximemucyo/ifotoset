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

        return [
            'totalUsers'        => $totalUsers,
            'totalGalleries'    => $totalGalleries,
            'totalStorageBytes' => $totalStorageBytes,
            'totalRevenue'      => $totalRevenue,
            'recentUsers'       => $recentUsers,
            'recentGalleries'   => $recentGalleries,
        ];
    }
}
