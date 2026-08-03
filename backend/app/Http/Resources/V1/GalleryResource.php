<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GalleryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'title' => $this->title,
            'slug' => $this->slug,
            'client_name' => $this->client_name,
            'event_date' => $this->event_date?->toDateString(),
            'visibility' => $this->visibility,
            'has_password' => !empty($this->password_hash),
            'password_hint' => $this->password_hint,
            'version' => $this->version,
            'stats' => [
                'photo_count' => $this->stats->photo_count ?? 0,
                'video_count' => $this->stats->video_count ?? 0,
                'downloads_count' => $this->stats->downloads_count ?? 0,
                'favorites_count' => $this->stats->favorites_count ?? 0,
                'total_bytes' => $this->stats->total_bytes ?? 0,
            ],
            'cover_photo' => new PhotoResource($this->whenLoaded('coverPhoto')),
            'photos' => PhotoResource::collection($this->whenLoaded('photos')),
            'expires_at' => $this->expires_at?->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
?>
