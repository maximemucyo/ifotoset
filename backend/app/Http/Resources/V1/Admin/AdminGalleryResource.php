<?php

namespace App\Http\Resources\V1\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminGalleryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'title' => $this->title,
            'slug' => $this->slug,
            'owner' => $this->user->name ?? 'Unknown',
            'client_name' => $this->client_name,
            'event_date' => $this->event_date?->toDateString(),
            'visibility' => $this->visibility,
            'images' => (int) ($this->stats->photo_count ?? 0),
            'total_bytes' => (int) ($this->stats->total_bytes ?? 0),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
