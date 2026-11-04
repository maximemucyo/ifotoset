<?php

namespace App\Queries\Studio;

use App\Models\Booking;
use App\Models\Client;
use App\Models\Gallery;
use App\Models\GalleryFavoriteList;
use App\Models\GalleryInvitation;
use App\Models\Photo;
use App\Models\User;
use App\Services\StorageStatisticsService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class StudioDashboardQuery
{
    /** Each kind of "Needs you" row shows at most this many galleries. */
    private const PER_KIND = 3;

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
        // A slot held for an unpaid deposit isn't a request to review yet
        $pendingBookings = Booking::where('user_id', $user->id)->where('status', 'pending')
            ->where(fn ($q) => $q->whereNull('deposit_status')->orWhere('deposit_status', 'paid'))
            ->count();

        $recentGalleries = Gallery::where('user_id', $user->id)
            ->withLastViewedAt()
            ->with(['coverPhoto', 'stats'])
            ->orderBy('created_at', 'desc')
            ->limit(6)
            ->get();

        $upcomingBookings = Booking::where('user_id', $user->id)
            ->blocking()
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
            'needsYou'         => $this->needsYou($user, $pendingBookings),
            'clientActivity'   => $this->clientActivity($user),
        ];
    }

    /**
     * Things waiting on the photographer, most urgent kind first. Each row is one action.
     *
     * @return Collection<int, array{kind: string, title: string, detail: string, action: string, url: string, gallery?: Gallery, photos?: Collection}>
     */
    public function needsYou(User $user, int $pendingBookings): Collection
    {
        $rows = collect();

        // Picks clients sent that the photographer hasn't opened since (or that changed after they did)
        $lists = GalleryFavoriteList::query()
            ->whereHas('gallery', fn ($q) => $q->where('user_id', $user->id))
            ->whereNotNull('submitted_at')
            ->where('submitted_at', '>=', now()->subDays(30))
            ->where(fn ($q) => $q->whereNull('seen_at')->orWhereColumn('seen_at', '<', 'updated_at'))
            ->withCount('favorites')
            ->with('gallery')
            ->latest('updated_at')
            ->limit(self::PER_KIND)
            ->get();
        $names = $lists->isEmpty() ? collect() : Client::where('user_id', $user->id)
            ->whereIn('email', $lists->pluck('email'))
            ->pluck('name', 'email')
            ->mapWithKeys(fn ($name, $email) => [strtolower($email) => $name]);
        foreach ($lists as $list) {
            $who = $names[strtolower($list->email)] ?? $list->email;
            $changed = $list->seen_at !== null;
            $rows->push([
                'kind' => 'picks',
                'title' => $changed
                    ? "{$who} changed their favorites"
                    : "{$who} sent " . number_format($list->favorites_count) . ' ' . str('favorite')->plural($list->favorites_count),
                'detail' => $list->gallery->title . ', ' . ($changed ? $list->updated_at : $list->submitted_at)->diffForHumans(),
                'action' => 'View',
                'url' => route('studio.galleries.activity', ['uuid' => $list->gallery->uuid, 'view' => 'favorites', 'person' => $list->email]),
                'photos' => $list->photos()->with('gallery')->orderBy('photos.id')->limit(4)->get(),
            ]);
        }

        if ($pendingBookings > 0) {
            $rows->push([
                'kind' => 'bookings',
                'title' => $pendingBookings . ' ' . str('booking request')->plural($pendingBookings) . ' to confirm',
                'detail' => 'Clients are waiting for your answer.',
                'action' => 'Review',
                'url' => route('studio.bookings.index', ['status' => 'pending']),
            ]);
        }

        $expiring = Gallery::where('user_id', $user->id)
            ->lifecycle('expiring')
            ->with('coverPhoto')
            ->orderBy('expires_at')
            ->limit(self::PER_KIND)
            ->get();
        foreach ($expiring as $gallery) {
            $local = $gallery->expires_at->copy()->setTimezone(Gallery::expiryTimezone($user));
            $days = (int) now(Gallery::expiryTimezone($user))->startOfDay()->diffInDays($local->copy()->startOfDay());
            $rows->push([
                'kind' => 'expiring',
                'title' => "“{$gallery->title}” closes " . match ($days) {
                    0 => 'today',
                    1 => 'tomorrow',
                    default => "in {$days} days",
                },
                'detail' => 'Clients can’t open it after ' . $local->format('F j') . '.',
                'action' => 'Change date',
                'url' => route('studio.galleries.edit', $gallery->uuid) . '#expiry',
                'gallery' => $gallery,
            ]);
        }

        // Invites still unopened after 5 days; older than a month, the photographer has moved on
        $invites = GalleryInvitation::query()
            ->whereHas('gallery', fn ($q) => $q->where('user_id', $user->id)->lifecycle('shared'))
            ->whereNull('accepted_at')
            ->whereNull('revoked_at')
            ->whereBetween('created_at', [now()->subDays(30), now()->subDays(5)])
            ->selectRaw('gallery_id, COUNT(*) as unopened, MIN(created_at) as first_sent')
            ->groupBy('gallery_id')
            ->orderBy('first_sent')
            ->limit(self::PER_KIND)
            ->get();
        $inviteGalleries = Gallery::with('coverPhoto')->findMany($invites->pluck('gallery_id'))->keyBy('id');
        foreach ($invites as $row) {
            $gallery = $inviteGalleries[$row->gallery_id];
            $rows->push([
                'kind' => 'invites',
                'title' => $row->unopened . ' ' . str('invite')->plural($row->unopened) . " to “{$gallery->title}” not opened",
                'detail' => 'Sent ' . Carbon::parse($row->first_sent)->diffForHumans() . '.',
                'action' => 'See invites',
                'url' => route('studio.galleries.clients', $gallery->uuid),
                'gallery' => $gallery,
            ]);
        }

        // Created more than a day ago and still empty: the upload was probably forgotten
        $empty = Gallery::where('user_id', $user->id)
            ->where('created_at', '<', now()->subDay())
            ->where('moderation_status', '!=', 'taken_down')
            ->where(fn ($q) => $q->whereDoesntHave('stats')->orWhereHas('stats', fn ($s) => $s->where('photo_count', 0)->where('video_count', 0)))
            ->orderBy('created_at')
            ->limit(self::PER_KIND)
            ->get();
        foreach ($empty as $gallery) {
            $rows->push([
                'kind' => 'empty',
                'title' => "“{$gallery->title}” has no photos",
                'detail' => 'Created ' . $gallery->created_at->diffForHumans() . '.',
                'action' => 'Add photos',
                'url' => route('studio.galleries.show', ['uuid' => $gallery->uuid, 'upload' => 1]),
                'gallery' => $gallery,
            ]);
        }

        return $rows;
    }

    /**
     * What clients did in the last 7 days, one row per gallery, latest first.
     *
     * @return Collection<int, object{gallery: Gallery, views: int, downloads: int, favorites: int, last_at: Carbon}>
     */
    public function clientActivity(User $user, int $limit = 5): Collection
    {
        $rows = DB::table('activity_logs')
            ->whereIn('gallery_id', Gallery::where('user_id', $user->id)->select('id'))
            ->where('created_at', '>=', now()->subWeek())
            ->whereIn('event', ['gallery_viewed', 'photo_downloaded', 'gallery_zip_file_downloaded', 'photo_favorited'])
            ->groupBy('gallery_id')
            ->select([
                'gallery_id',
                DB::raw("SUM(event = 'gallery_viewed') as views"),
                DB::raw("SUM(event IN ('photo_downloaded', 'gallery_zip_file_downloaded')) as downloads"),
                DB::raw("SUM(event = 'photo_favorited') as favorites"),
                DB::raw('MAX(created_at) as last_at'),
            ])
            ->orderByDesc('last_at')
            ->limit($limit)
            ->get();

        $galleries = Gallery::with('coverPhoto')->findMany($rows->pluck('gallery_id'))->keyBy('id');

        return $rows
            ->filter(fn ($row) => $galleries->has($row->gallery_id))
            ->map(fn ($row) => (object) [
                'gallery' => $galleries[$row->gallery_id],
                'views' => (int) $row->views,
                'downloads' => (int) $row->downloads,
                'favorites' => (int) $row->favorites,
                'last_at' => Carbon::parse($row->last_at),
            ])
            ->values();
    }
}
