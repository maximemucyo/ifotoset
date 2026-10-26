<?php

namespace App\Jobs;

use App\Models\Gallery;
use App\Models\User;
use App\Services\StorageService;
use App\Services\TrashService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Permanently removes a soft-deleted account's content (galleries, photos,
 * B2 objects, avatar, CRM data) and anonymises the user row. Payment and
 * subscription rows are kept, linked to the anonymised user, for accounting.
 */
class PurgeDeletedUserJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 1800;
    public array $backoff = [60, 300, 900];

    public function __construct(public int $userId) {}

    public function handle(TrashService $trashService, StorageService $storageService): void
    {
        $user = User::onlyTrashed()->find($this->userId);
        if (!$user || $user->purged_at) {
            return;
        }

        // 1. Galleries, photos and their storage objects
        Gallery::withTrashed()->where('user_id', $user->id)->orderBy('id')
            ->each(function (Gallery $gallery) use ($trashService) {
                if (!$gallery->trashed()) {
                    $gallery->delete();
                }
                $trashService->purgeGallery($gallery->id, recalculateStats: false);
            });

        // 2. Avatar and portfolio branding images
        foreach (array_filter([$user->avatar_path, $user->logo_path, $user->cover_path, $user->share_image_path]) as $path) {
            try {
                $storageService->delete($path);
            } catch (\Throwable $e) {
                Log::warning("Profile image delete failed while purging user {$user->id}: " . $e->getMessage());
            }
        }

        // 3. CRM data and personal details
        DB::transaction(function () use ($user) {
            // Payment rows referencing these use ON DELETE SET NULL
            foreach (['bookings', 'clients', 'packages'] as $table) {
                DB::table($table)->where('user_id', $user->id)->delete();
            }

            $user->forceFill([
                'name' => 'Deleted user',
                'email' => "deleted-{$user->id}@deleted.invalid",
                'username' => "deleted-{$user->id}",
                'password' => Hash::make(Str::random(64)),
                'phone' => null,
                'location' => null,
                'city' => null,
                'specialties' => null,
                'website' => null,
                'bio' => null,
                'avatar_path' => null,
                'logo_path' => null,
                'cover_path' => null,
                'share_image_path' => null,
                'portfolio_settings' => null,
                'notification_preferences' => null,
                'remember_token' => null,
                'is_active' => false,
                'profile_public' => false,
                'storage_used_bytes' => 0,
                'storage_reserved_bytes' => 0,
                'purged_at' => now(),
            ])->saveQuietly();
        });

        Log::info('[Account] Deleted account purged.', ['user_id' => $user->id]);
    }
}
