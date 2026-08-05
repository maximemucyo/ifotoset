<?php

namespace App\Http\Resources\V1\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role,
            'plan' => $this->plan->name ?? 'Starter',
            'storage_used_bytes' => (int) $this->storage_used_bytes,
            'is_active' => (bool) $this->is_active,
            'galleries_count' => $this->galleries_count ?? $this->galleries()->count(),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
