<?php

namespace App\Http\Resources\V1\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminDashboardResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'stats' => $this['stats'],
            'queue_stats' => $this['queue_stats'],
            'recent_users' => UserResource::collection($this['recent_users']),
            'recent_galleries' => AdminGalleryResource::collection($this['recent_galleries']),
            'queue_jobs' => MediaJobResource::collection($this['queue_jobs']),
        ];
    }
}
