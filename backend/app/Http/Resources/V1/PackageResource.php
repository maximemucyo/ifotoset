<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PackageResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        $minutes = $this->duration_minutes;
        if ($minutes < 60) {
            $durationLabel = "{$minutes} min";
        } elseif ($minutes < 1440) {
            $hours = $minutes / 60;
            $formattedHours = is_int($hours) || $hours == round($hours, 1) ? $hours : round($hours, 1);
            $durationLabel = $formattedHours . ($formattedHours == 1 ? " hour" : " hours");
        } else {
            $days = $minutes / 1440;
            $formattedDays = is_int($days) || $days == round($days, 1) ? $days : round($days, 1);
            $durationLabel = $formattedDays . ($formattedDays == 1 ? " day" : " days");
        }

        return [
            'uuid' => $this->uuid,
            'name' => $this->name,
            'description' => $this->description,
            'price' => (float) $this->price,
            'currency' => $this->currency,
            'duration_minutes' => $this->duration_minutes,
            'duration_label' => $durationLabel,
            'deliverables' => $this->deliverables ?? [],
            'sort_order' => $this->sort_order,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
