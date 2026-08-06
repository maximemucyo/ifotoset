<?php

namespace App\Http\Resources\V1;

use App\Services\StorageStatisticsService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        $storageStats = app(StorageStatisticsService::class)->getStorageStats($this->resource);
        $cdnDomain = config('filesystems.disks.b2.cdn_domain', 'cdn.ifotoset.com');
        $avatarUrl = $this->avatar_path ? "https://{$cdnDomain}/" . ltrim($this->avatar_path, '/') : null;

        return [
            'uuid' => $this->uuid,
            'name' => $this->name,
            'username' => $this->username,
            'email' => $this->email,
            'role' => $this->role,
            'plan' => $this->plan->slug ?? 'free',
            'phone' => $this->phone,
            'location' => $this->location,
            'website' => $this->website,
            'bio' => $this->bio,
            'avatar_url' => $avatarUrl,
            'notification_preferences' => $this->notification_preferences ?? [
                'new_bookings' => true,
                'new_messages' => true,
                'gallery_activity' => true,
                'payment_received' => true,
            ],
            'storage' => $storageStats,
        ];
    }
}
