<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class StorageStatisticsService
{
    /**
     * Retrieve storage statistics for the user, with caching.
     */
    public function getStorageStats(User $user): array
    {
        $cacheKey = "user_storage_stats_{$user->id}";

        return Cache::remember($cacheKey, 60, function () use ($user) {
            $userStats = DB::table('photos')
                ->join('galleries', 'photos.gallery_id', '=', 'galleries.id')
                ->where('galleries.user_id', $user->id)
                ->selectRaw("
                    COALESCE(SUM(CASE WHEN photos.deleted_at IS NULL AND galleries.deleted_at IS NULL THEN photos.size ELSE 0 END), 0) as active_bytes,
                    COALESCE(SUM(CASE WHEN photos.deleted_at IS NOT NULL OR galleries.deleted_at IS NOT NULL THEN photos.size ELSE 0 END), 0) as trash_bytes
                ")
                ->first();

            $activeBytes = (int) ($userStats->active_bytes ?? 0);
            $trashBytes = (int) ($userStats->trash_bytes ?? 0);
            $usedBytes = $activeBytes + $trashBytes;

            // Load plan limit
            $plan = $user->plan;
            $limitBytes = $plan ? $plan->storage_limit : 5368709120; // 5 GB default
            $isUnlimited = is_null($limitBytes) || $limitBytes < 0;

            if ($isUnlimited) {
                $remainingBytes = null;
                $activePercent = 0.0;
                $trashPercent = 0.0;
                $percentUsed = 0.0;
            } else {
                $remainingBytes = max(0, $limitBytes - $usedBytes);
                $activePercent = $limitBytes > 0 ? round(($activeBytes / $limitBytes) * 100, 2) : 0.0;
                $trashPercent = $limitBytes > 0 ? round(($trashBytes / $limitBytes) * 100, 2) : 0.0;
                $percentUsed = $limitBytes > 0 ? round(($usedBytes / $limitBytes) * 100, 2) : 0.0;
            }

            return [
                'plan_name' => $plan->name ?? 'Free Tier',
                'limit_bytes' => $isUnlimited ? null : (int) $limitBytes,
                'active_bytes' => $activeBytes,
                'trash_bytes' => $trashBytes,
                'used_bytes' => $usedBytes,
                'remaining_bytes' => $remainingBytes,
                'active_percent' => $activePercent,
                'trash_percent' => $trashPercent,
                'percent_used' => $percentUsed,
                'is_unlimited' => $isUnlimited,
            ];
        });
    }

    /**
     * Clear the storage statistics cache for a user.
     */
    public static function clearCache(int $userId): void
    {
        Cache::forget("user_storage_stats_{$userId}");
    }
}
