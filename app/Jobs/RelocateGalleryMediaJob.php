<?php

namespace App\Jobs;

use App\Models\Gallery;
use App\Models\GalleryDownload;
use App\Models\Photo;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Moves a gallery's media between `galleries/{uuid}` and `protected-galleries/{uuid}` so the storage
 * prefix matches the gallery's current privacy. The edge worker only serves `protected-*` objects with a
 * signed token, so PIN/private galleries whose files sit under `galleries/` are reachable without the PIN.
 *
 * The target is read from the gallery when the job runs, so repeated privacy toggles converge, and every
 * step is idempotent: a failed run can simply be retried.
 */
class RelocateGalleryMediaJob implements ShouldQueue, ShouldBeUniqueUntilProcessing
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 3600;

    /**
     * Object columns on photos that hold full storage keys.
     */
    protected const PHOTO_PATH_COLUMNS = ['path', 'original_path', 'delivery_path', 'hls_path', 'poster_path'];

    public function __construct(
        public int $galleryId
    ) {
        $this->onQueue('media');
    }

    public function backoff(): array
    {
        return [60, 300];
    }

    public function uniqueId(): string
    {
        return 'relocate_gallery_media_' . $this->galleryId;
    }

    public function middleware(): array
    {
        return [(new WithoutOverlapping('relocate_gallery_media_' . $this->galleryId))->releaseAfter(60)->expireAfter($this->timeout)];
    }

    public function handle(): void
    {
        $gallery = Gallery::find($this->galleryId);
        if (!$gallery) {
            return; // Trashed or purged: nothing to serve
        }

        $target = $gallery->mediaPrefix();
        $source = $gallery->mediaPrefix(!$gallery->hasProtectedMedia());
        $disk = Storage::disk('b2');

        // 1. Server-side copy of every object (ZIPs are dropped instead: they are regenerated on demand)
        $moved = [];
        foreach ($disk->allFiles($source) as $key) {
            if (str_starts_with($key, "{$source}/downloads/")) {
                continue;
            }

            $destination = $target . substr($key, strlen($source));
            if (!$disk->exists($destination)) {
                $disk->copy($key, $destination);
            }
            $moved[] = $key;
        }

        // 2. Point the database at the new keys
        DB::transaction(function () use ($gallery, $source, $target) {
            Photo::withTrashed()
                ->where('gallery_id', $gallery->id)
                ->where(function ($q) use ($source) {
                    foreach (self::PHOTO_PATH_COLUMNS as $column) {
                        $q->orWhere($column, 'like', "{$source}/%");
                    }
                })
                ->lazyById()
                ->each(function (Photo $photo) use ($source, $target) {
                    foreach (self::PHOTO_PATH_COLUMNS as $column) {
                        $value = (string) $photo->{$column};
                        if (str_starts_with($value, "{$source}/")) {
                            $photo->{$column} = $target . substr($value, strlen($source));
                        }
                    }
                    $photo->saveQuietly();
                });

            GalleryDownload::where('gallery_id', $gallery->id)
                ->where('storage_path', 'like', "{$source}/%")
                ->update(['status' => 'expired', 'expired_at' => now(), 'storage_path' => null]);
        });

        // 3. Remove the old copies, then evict them from the edge cache
        $disk->deleteDirectory($source);
        $this->purgeEdgeCache($moved);

        Log::info('Gallery media relocated.', [
            'gallery_id' => $gallery->id,
            'from' => $source,
            'to' => $target,
            'objects' => count($moved),
        ]);
    }

    /**
     * The worker caches media for a year by URL, so old URLs keep serving after the objects are gone.
     *
     * @param array<string> $keys
     */
    protected function purgeEdgeCache(array $keys): void
    {
        $zoneId = config('services.cloudflare.zone_id');
        $apiToken = config('services.cloudflare.api_token');
        if (!$zoneId || !$apiToken || empty($keys)) {
            return;
        }

        $base = \App\Support\Cdn::scheme() . '://' . config('filesystems.disks.b2.cdn_domain', 'cdn.ifotoset.com');

        foreach (array_chunk($keys, 30) as $chunk) {
            try {
                Http::withToken($apiToken)
                    ->post("https://api.cloudflare.com/client/v4/zones/{$zoneId}/purge_cache", [
                        'files' => array_map(fn ($key) => "{$base}/{$key}", $chunk),
                    ])
                    ->throw();
            } catch (\Throwable $e) {
                Log::warning("Edge cache purge failed after relocating gallery {$this->galleryId}: " . $e->getMessage());
            }
        }
    }

    public function failed(\Throwable $e): void
    {
        Log::error("Gallery media relocation failed for gallery {$this->galleryId}: " . $e->getMessage());
    }
}
