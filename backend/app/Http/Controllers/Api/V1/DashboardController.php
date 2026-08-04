<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\GalleryResource;
use App\Models\Gallery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * Get aggregated statistics and recent galleries for the authenticated user.
     * GET /api/v1/dashboard
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        // Get aggregated downloads and favorites from gallery_stats
        $stats = DB::table('galleries')
            ->join('gallery_stats', 'galleries.id', '=', 'gallery_stats.gallery_id')
            ->where('galleries.user_id', $user->id)
            ->whereNull('galleries.deleted_at')
            ->select(
                DB::raw('COUNT(galleries.id) as active_galleries'),
                DB::raw('SUM(gallery_stats.downloads_count) as total_downloads'),
                DB::raw('SUM(gallery_stats.favorites_count) as total_favorites')
            )
            ->first();

        // Retrieve the 5 most recent galleries
        $recentGalleries = Gallery::where('user_id', $user->id)
            ->with(['stats', 'coverPhoto'])
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get();

        return response()->json([
            'data' => [
                'stats' => [
                    'active_galleries' => (int) ($stats->active_galleries ?? 0),
                    'total_downloads' => (int) ($stats->total_downloads ?? 0),
                    'total_favorites' => (int) ($stats->total_favorites ?? 0),
                    'storage_used_bytes' => (int) ($user->storage_used_bytes ?? 0),
                ],
                'recent_galleries' => GalleryResource::collection($recentGalleries),
            ]
        ]);
    }
}
