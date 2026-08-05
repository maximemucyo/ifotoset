<?php

namespace App\Http\Resources\V1\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MediaJobResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'photo_uuid' => $this->photo->uuid ?? null,
            'filename' => $this->photo->filename ?? 'Unknown',
            'original_filename' => $this->photo->original_filename ?? 'Unknown',
            'stored_filename' => $this->photo->stored_filename ?? 'Unknown',
            'gallery_title' => $this->photo->gallery->title ?? 'Unknown',
            'studio_name' => $this->photo->gallery->user->name ?? 'Unknown',
            'job_uuid' => $this->job_uuid,
            'job_type' => $this->job_type,
            'queue' => $this->queue,
            'status' => $this->status,
            'attempts' => $this->attempts,
            'max_attempts' => $this->max_attempts,
            'progress' => $this->progress,
            'started_at' => $this->started_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'failed_at' => $this->failed_at?->toIso8601String(),
            'duration_ms' => $this->duration_ms,
            'error_message' => $this->error_message,
        ];
    }
}
