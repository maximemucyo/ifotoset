<?php

namespace App\Console\Commands;

use App\Enums\NotificationPreference;
use App\Mail\GalleryActivityDigestMail;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class SendGalleryActivityDigest extends Command
{
    protected $signature = 'notifications:gallery-activity-digest';

    protected $description = 'Email each photographer one summary of the last 24 hours of downloads and favorites';

    public function handle(): int
    {
        $since = now()->subDay();

        $rows = DB::table('activity_logs')
            ->join('galleries', 'galleries.id', '=', 'activity_logs.gallery_id')
            ->where('activity_logs.created_at', '>=', $since)
            ->whereIn('activity_logs.event', ['photo_downloaded', 'gallery_zip_file_downloaded', 'photo_favorited', 'gallery_viewed'])
            ->groupBy('galleries.user_id', 'galleries.id', 'galleries.title')
            ->select([
                'galleries.user_id',
                'galleries.title',
                DB::raw("SUM(activity_logs.event IN ('photo_downloaded', 'gallery_zip_file_downloaded')) as downloads"),
                DB::raw("SUM(activity_logs.event = 'photo_favorited') as favorites"),
                DB::raw("SUM(activity_logs.event = 'gallery_viewed') as views"),
            ])
            ->get()
            ->groupBy('user_id');

        $sent = 0;
        foreach ($rows as $userId => $galleries) {
            $galleries = $galleries
                ->map(fn ($row) => ['title' => $row->title, 'downloads' => (int) $row->downloads, 'favorites' => (int) $row->favorites, 'views' => (int) $row->views])
                ->filter(fn ($row) => $row['downloads'] + $row['favorites'] > 0)
                ->sortByDesc(fn ($row) => $row['downloads'] + $row['favorites'])
                ->values()
                ->all();

            if (!$galleries) {
                continue;
            }

            $user = User::where('is_active', true)->find($userId);
            if (!$user?->email || !NotificationPreference::GalleryActivity->enabledFor($user)) {
                continue;
            }

            Mail::to($user->email)->queue(new GalleryActivityDigestMail($user, $galleries));
            $sent++;
        }

        $this->info("Queued {$sent} activity digests.");

        return self::SUCCESS;
    }
}
