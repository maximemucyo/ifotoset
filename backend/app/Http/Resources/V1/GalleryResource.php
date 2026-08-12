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
            'has_explicit_cover' => (bool) $this->has_explicit_cover,
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
            'deleted_at' => $this->deleted_at?->toIso8601String(),
            'trash_expires_at' => $this->deleted_at ? $this->deleted_at->copy()->addDays(config('filesystems.trash_retention_days', 30))->toIso8601String() : null,
            'days_remaining' => $this->deleted_at ? max(0, (int) ceil(now()->diffInDays($this->deleted_at->copy()->addDays(config('filesystems.trash_retention_days', 30)), false))) : null,
            'invitations' => $this->whenLoaded('invitations', function() {
                return $this->invitations->map(function ($invitation) {
                    return [
                        'id' => $invitation->id,
                        'email' => $invitation->email,
                        'accepted' => !empty($invitation->accepted_at),
                        'accepted_at' => $invitation->accepted_at?->toIso8601String(),
                        'expired' => $invitation->expires_at ? $invitation->expires_at->isPast() : false,
                        'expires_at' => $invitation->expires_at?->toIso8601String(),
                        'revoked' => !empty($invitation->revoked_at),
                        'revoked_at' => $invitation->revoked_at?->toIso8601String(),
                        'created_at' => $invitation->created_at->toIso8601String(),
                    ];
                });
            }),
        ];
    }
}
?>
