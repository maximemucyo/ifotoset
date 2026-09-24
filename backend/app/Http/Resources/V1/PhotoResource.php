<?php

namespace App\Http\Resources\V1;

use App\Services\StorageService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PhotoResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $storageService = app(StorageService::class);
        $baseCdnUrl = $storageService->getCdnUrl($this->path, null, $this->filename);

        $isReady = $this->status === \App\Enums\PhotoStatus::Ready->value;

        $isCover = $this->gallery ? ($this->gallery->cover_photo_id === $this->id) : false;

        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'original_filename' => $this->original_filename ?? $this->filename,
            'filename' => $this->filename,
            'thumbnail_url' => $this->getUrl('sm'),
            'medium_url' => $this->getUrl('md'),
            'large_url' => $this->getUrl('lg'),
            'full_url' => $this->getUrl('xl'),
            'original_url' => $this->getUrl(),
            'is_hidden' => (bool) $this->is_hidden,
            'is_cover' => $isCover,
            'is_video' => $this->isVideo(),
            'media_type' => $this->media_type ?? 'photo',
            'duration_seconds' => $this->duration_seconds,
            'duration_formatted' => $this->duration_formatted,
            'delivery_url' => $this->isVideo() ? $this->getDeliveryUrl() : null,
            'poster_url' => $this->isVideo() ? $this->getPosterUrl('lg') : null,
            'mime_type' => $this->mime_type,
            'size' => $this->size,
            'width' => $this->width,
            'height' => $this->height,
            'checksum' => $this->checksum,
            'blurhash' => $this->blurhash,
            'status' => $this->status,
            'cdn_url' => $baseCdnUrl,
            'variants' => $isReady ? [
                'xs' => $storageService->getCdnUrl($this->path, 'xs', $this->filename),
                'sm' => $storageService->getCdnUrl($this->path, 'sm', $this->filename),
                'md' => $storageService->getCdnUrl($this->path, 'md', $this->filename),
                'lg' => $storageService->getCdnUrl($this->path, 'lg', $this->filename),
                'xl' => $storageService->getCdnUrl($this->path, 'xl', $this->filename),
            ] : null,
            'taken_at' => $this->taken_at?->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
            'deleted_at' => $this->deleted_at?->toIso8601String(),
            'trash_expires_at' => $this->deleted_at ? $this->deleted_at->copy()->addDays(config('filesystems.trash_retention_days', 30))->toIso8601String() : null,
            'days_remaining' => $this->deleted_at ? max(0, (int) ceil(now()->diffInDays($this->deleted_at->copy()->addDays(config('filesystems.trash_retention_days', 30)), false))) : null,
        ];
    }
}
?>
