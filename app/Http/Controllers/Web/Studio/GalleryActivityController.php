<?php

namespace App\Http\Controllers\Web\Studio;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Gallery;
use App\Models\GalleryDownload;
use App\Models\GalleryFavoriteList;
use App\Models\Photo;
use App\Queries\GalleryPhotoQuery;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Activity tab: what clients did in a gallery. Favorites per person (as photos, file names for Lightroom and a CSV),
 * downloads, and the quick share links made for chosen photos.
 */
class GalleryActivityController extends Controller
{
    public const VIEWS = ['favorites', 'downloads', 'links'];

    public const DOWNLOAD_EVENTS = ['photo_downloaded', 'gallery_zip_file_downloaded'];

    public function __construct(protected GalleryPhotoQuery $photoQuery) {}

    public function index(Request $request, string $uuid): View
    {
        $gallery = $this->gallery($uuid);
        $view = in_array($request->query('view'), self::VIEWS, true) ? $request->query('view') : 'favorites';

        return view('studio.galleries.activity', [
            'gallery' => $gallery,
            'view' => $view,
            'counts' => [
                'favorites' => $gallery->favoriteLists()->has('favorites')->count(),
                'downloads' => $this->downloadEvents($gallery)->count(),
                'links' => $gallery->shareLinks()->count(),
            ],
        ] + match ($view) {
            'downloads' => $this->downloads($gallery),
            'links' => ['links' => $gallery->shareLinks()->withCount('photos')->with(['photos' => fn ($q) => $q->limit(4)])->get()],
            default => $this->favorites($request, $gallery),
        });
    }

    /**
     * Links in emails from before the Activity tab point here.
     */
    public function favoritesRedirect(Request $request, string $uuid): RedirectResponse
    {
        return redirect()->route('studio.galleries.activity', ['uuid' => $uuid, 'view' => 'favorites'] + $request->only('person'));
    }

    /**
     * Remove one person's picks, such as a test with your own email.
     */
    public function destroyFavorites(string $uuid, int $id): RedirectResponse
    {
        $gallery = $this->gallery($uuid);
        $list = $gallery->favoriteLists()->withCount('favorites')->whereKey($id)->firstOrFail();

        $list->delete();
        if ($list->favorites_count > 0) {
            $gallery->stats()->update(['favorites_count' => DB::raw('GREATEST(favorites_count - ' . (int) $list->favorites_count . ', 0)')]);
        }

        return redirect()->route('studio.galleries.activity', ['uuid' => $uuid, 'view' => 'favorites'])
            ->with('success', "Removed {$list->email}'s favorites.");
    }

    private function favorites(Request $request, Gallery $gallery): array
    {
        $lists = $this->lists($gallery);
        $person = $this->person($request, $lists);
        $photos = $this->photos($gallery, $person)->get();

        return [
            'lists' => $lists,
            'person' => $person,
            'photos' => $photos,
            'everyoneCount' => $person ? $gallery->photos()->whereHas('favorites')->count() : $photos->count(),
            'names' => $this->clientNames($gallery, $lists),
            'filenames' => $photos->map(fn (Photo $photo) => pathinfo((string) $photo->original_filename, PATHINFO_FILENAME))
                ->filter()->unique()->values(),
        ];
    }

    private function downloadEvents(Gallery $gallery): \Illuminate\Database\Query\Builder
    {
        return DB::table('activity_logs')
            ->where('gallery_id', $gallery->id)
            ->whereIn('event', self::DOWNLOAD_EVENTS);
    }

    /**
     * Newest first. A whole-gallery download takes its email from the ZIP request.
     */
    private function downloads(Gallery $gallery): array
    {
        $page = $this->downloadEvents($gallery)->orderByDesc('id')->paginate(50, ['id', 'event', 'properties', 'created_at'])->withQueryString();
        $rows = collect($page->items())->map(function ($row) {
            $props = json_decode($row->properties ?? '{}', true) ?: [];

            return (object) [
                'zip' => $row->event === 'gallery_zip_file_downloaded',
                'email' => $props['email'] ?? null,
                'downloadId' => $props['download_id'] ?? null,
                'filename' => $props['filename'] ?? null,
                'photos' => $props['total_photos'] ?? null,
                'viaLink' => isset($props['share_link_id']),
                'at' => \Illuminate\Support\Carbon::parse($row->created_at),
            ];
        });

        $zipEmails = GalleryDownload::whereIn('id', $rows->pluck('downloadId')->filter())->pluck('email', 'id');
        $rows->each(fn ($row) => $row->email ??= $row->downloadId ? ($zipEmails[$row->downloadId] ?? null) : null);

        return ['downloads' => $rows, 'downloadsPage' => $page, 'names' => $this->clientNames($gallery, $rows->pluck('email')->filter()->map(fn ($e) => (object) ['email' => $e]))];
    }

    public function export(Request $request, string $uuid): StreamedResponse
    {
        $gallery = $this->gallery($uuid);
        $lists = $this->lists($gallery);
        if ($person = $this->person($request, $lists)) {
            $lists = collect([$person]);
        }
        $names = $this->clientNames($gallery, $lists);

        $filename = str($gallery->title)->slug()->append('-favorites.csv')->toString();

        return response()->streamDownload(function () use ($gallery, $lists, $names) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Email', 'Name', 'File name', 'Set', 'Picked on', 'Sent on']);
            foreach ($lists as $list) {
                $photos = $this->photoQuery->applyOrder(
                    $gallery->photos()->whereIn('id', $list->favorites()->select('photo_id'))->with('set:id,name'),
                    $gallery
                )->get();
                $pickedAt = $list->favorites()->pluck('created_at', 'photo_id');
                foreach ($photos as $photo) {
                    $at = $pickedAt[$photo->id] ?? null;
                    fputcsv($out, [
                        $list->email,
                        $names[$list->email] ?? '',
                        $photo->original_filename,
                        $photo->set?->name ?? '',
                        $at ? \Illuminate\Support\Carbon::parse($at)->toDateTimeString() : '',
                        $list->submitted_at?->toDateTimeString() ?? '',
                    ]);
                }
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    private function gallery(string $uuid): Gallery
    {
        $gallery = Gallery::where('uuid', $uuid)->firstOrFail();
        $this->authorize('update', $gallery);

        return $gallery;
    }

    /**
     * Clients with picks, those who sent theirs first.
     *
     * @return Collection<int, GalleryFavoriteList>
     */
    private function lists(Gallery $gallery): Collection
    {
        return $gallery->favoriteLists()
            ->withCount('favorites')
            ->get()
            ->filter(fn (GalleryFavoriteList $list) => $list->favorites_count > 0 || $list->submitted_at)
            ->sortBy([
                fn ($a, $b) => ($b->submitted_at !== null) <=> ($a->submitted_at !== null),
                fn ($a, $b) => $b->updated_at <=> $a->updated_at,
            ])
            ->values();
    }

    private function person(Request $request, Collection $lists): ?GalleryFavoriteList
    {
        $email = strtolower(trim((string) $request->query('person')));

        return $email === '' ? null : $lists->firstWhere('email', $email);
    }

    /**
     * One client's picks, or every photo anyone picked with how many people picked it. In the gallery's order.
     */
    private function photos(Gallery $gallery, ?GalleryFavoriteList $person): HasMany
    {
        $query = $person
            ? $gallery->photos()->whereIn('id', $person->favorites()->select('photo_id'))
            : $gallery->photos()->whereHas('favorites')->withCount('favorites');

        return $this->photoQuery->applyOrder($query, $gallery);
    }

    /**
     * Names from the studio's Clients for emails it already knows.
     *
     * @return array<string, string>
     */
    private function clientNames(Gallery $gallery, Collection $lists): array
    {
        if ($lists->isEmpty()) {
            return [];
        }

        return Client::where('user_id', $gallery->user_id)
            ->whereIn('email', $lists->pluck('email'))
            ->pluck('name', 'email')
            ->mapWithKeys(fn ($name, $email) => [strtolower($email) => $name])
            ->all();
    }
}
