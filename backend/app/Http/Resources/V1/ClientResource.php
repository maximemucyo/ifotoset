<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClientResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'company_name' => $this->company_name,
            'location' => $this->location,
            'instagram' => $this->instagram,
            'notes' => $this->notes,
            'tags' => $this->tags ?? [],
            'last_contacted_at' => $this->last_contacted_at?->toIso8601String(),
            'bookings_count' => $this->whenCounted('bookings'),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
