<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\PackageResource;
use App\Models\Gallery;
use App\Models\Photo;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class PublicPhotographerController extends Controller
{
    /**
     * Show public photographer profile with featured galleries and packages.
     *
     * GET /api/v1/public/photographers/{username}
     */
    public function show(string $username): JsonResponse
    {
        $photographer = User::where('username', strtolower($username))->firstOrFail();

        // Active packages, sorted by display order
        $packages = $photographer->packages()
            ->active()
            ->orderBy('sort_order', 'asc')
            ->get();

        // Photographer-curated featured galleries (featured_order IS NOT NULL)
        $featuredGalleries = Gallery::where('user_id', $photographer->id)
            ->featured()
            ->with(['coverPhoto', 'stats'])
            ->whereNull('deleted_at')
            ->limit(12)
            ->get();

        $cdnDomain = config('filesystems.disks.b2.cdn_domain', 'cdn.ifotoset.com');

        $serializeGallery = function (Gallery $gallery) use ($cdnDomain) {
            $coverUrl = null;
            if ($gallery->coverPhoto) {
                $coverUrl = app(\App\Services\StorageService::class)->getCdnUrl(
                    $gallery->coverPhoto->path,
                    'sm',
                    $gallery->coverPhoto->filename
                );
            }

            return [
                'uuid' => $gallery->uuid,
                'title' => $gallery->title,
                'slug' => $gallery->slug,
                'client_name' => $gallery->client_name,
                'event_date' => $gallery->event_date?->toDateString(),
                'cover_url' => $coverUrl,
                'photo_count' => $gallery->stats?->photo_count ?? 0,
                'featured_order' => $gallery->featured_order,
            ];
        };

        $avatarUrl = $photographer->avatar_path
            ? 'https://' . $cdnDomain . '/' . ltrim($photographer->avatar_path, '/')
            : null;

        return response()->json([
            'photographer' => [
                'name' => $photographer->name,
                'username' => $photographer->username,
                'bio' => $photographer->bio,
                'avatar_url' => $avatarUrl,
                'location' => $photographer->location,
                'website' => $photographer->website,
                'phone' => $photographer->phone,
            ],
            'packages' => PackageResource::collection($packages),
            'featured_galleries' => $featuredGalleries->map($serializeGallery)->values(),
        ]);
    }
}
