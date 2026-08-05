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

        return [
            'uuid' => $this->uuid,
            'filename' => $this->filename,
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
