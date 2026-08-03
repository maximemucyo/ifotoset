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
        $baseCdnUrl = $storageService->getCdnUrl($this->path);

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
            'variants' => [
                'xs' => $storageService->getCdnUrl($this->path, 'xs'),
                'sm' => $storageService->getCdnUrl($this->path, 'sm'),
                'md' => $storageService->getCdnUrl($this->path, 'md'),
                'lg' => $storageService->getCdnUrl($this->path, 'lg'),
                'xl' => $storageService->getCdnUrl($this->path, 'xl'),
            ],
            'taken_at' => $this->taken_at?->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
?>
