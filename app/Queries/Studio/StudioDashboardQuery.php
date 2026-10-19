<?php

namespace App\Queries\Studio;

use App\Models\Booking;
use App\Models\Gallery;
use App\Models\Photo;
use App\Models\User;
use App\Services\StorageStatisticsService;

class StudioDashboardQuery
{
    public function __construct(
        protected StorageStatisticsService $storageService
    ) {}

    /**
     * Fetch optimized, bounded dashboard metrics and recent items for a photographer.
     */
    public function get(User $user): array
    {
        $storage = $this->storageService->getStorageStats($user);

        $totalGalleries = Gallery::where('user_id', $user->id)->count();
        $totalPhotos = Photo::whereHas('gallery', fn ($q) => $q->where('user_id', $user->id))->count();
        $pendingBookings = Booking::where('user_id', $user->id)->where('status', 'pending')->count();

        $recentGalleries = Gallery::where('user_id', $user->id)
            ->with(['coverPhoto', 'stats'])
            ->orderBy('created_at', 'desc')
            ->limit(6)
            ->get();

        $upcomingBookings = Booking::where('user_id', $user->id)
            ->whereIn('status', ['confirmed', 'pending'])
            ->where('starts_at', '>=', now()->startOfDay())
            ->with(['client', 'package'])
            ->orderBy('starts_at', 'asc')
            ->limit(5)
            ->get();

        return [
            'user'             => $user,
            'storage'          => $storage,
            'totalGalleries'   => $totalGalleries,
            'totalPhotos'      => $totalPhotos,
            'pendingBookings'  => $pendingBookings,
            'recentGalleries'  => $recentGalleries,
            'upcomingBookings' => $upcomingBookings,
        ];
    }
}
