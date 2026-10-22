<?php

namespace App\Actions\Billing;

use App\Models\Gallery;
use App\Models\User;
use App\Services\GalleryStatisticsService;
use App\Services\TrashService;
use Illuminate\Support\Facades\Log;

class DeleteOverQuotaGalleries
{
    public function __construct(
        protected TrashService $trashService,
        protected GalleryStatisticsService $statisticsService
    ) {}

    /**
     * Permanently delete a Free-plan account's oldest galleries until it is
     * back within the storage limit.
     *
     * @return list<string> Titles of the deleted galleries
     */
    public function execute(User $user): array
    {
        $deleted = [];

        $galleries = Gallery::where('user_id', $user->id)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get(['id', 'title']);

        foreach ($galleries as $gallery) {
            if (!$user->refresh()->isOverFreeQuota()) {
                break;
            }

            try {
                Gallery::whereKey($gallery->id)->first()?->delete();
                $this->trashService->purgeGallery($gallery->id);
                $deleted[] = $gallery->title;
            } catch (\Throwable $e) {
                // Left in the trash, where the daily trash purge retries it
                Log::error("Over-quota deletion failed for gallery {$gallery->id}: " . $e->getMessage());
            }
        }

        $this->statisticsService->recalculateUserVideoSeconds($user->id);

        Log::info('[Over Quota] Deleted galleries to bring a Free account within its storage limit.', [
            'user_id' => $user->id,
            'galleries_deleted' => count($deleted),
            'storage_used_bytes' => $user->refresh()->storage_used_bytes,
        ]);

        return $deleted;
    }
}
