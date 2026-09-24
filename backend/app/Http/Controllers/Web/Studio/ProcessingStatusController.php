<?php

namespace App\Http\Controllers\Web\Studio;

use App\Http\Controllers\Controller;
use App\Models\Gallery;
use App\Models\Photo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProcessingStatusController extends Controller
{
    /**
     * Poll processing status for a specific media item.
     * GET /studio/galleries/{uuid}/photos/{photoUuid}/status
     */
    public function show(Request $request, string $uuid, string $photoUuid): JsonResponse
    {
        $gallery = Gallery::where('uuid', $uuid)->firstOrFail();
        $this->authorize('view', $gallery);

        $photo = Photo::where('gallery_id', $gallery->id)
            ->where('uuid', $photoUuid)
            ->firstOrFail();

        return response()->json([
            'id' => $photo->id,
            'uuid' => $photo->uuid,
            'status' => $photo->status, // 'uploading', 'processing', 'ready', 'failed'
            'media_type' => $photo->media_type ?? 'photo',
            'duration' => $photo->duration_formatted,
            'duration_seconds' => $photo->duration_seconds,
            'width' => $photo->width,
            'height' => $photo->height,
            'thumbnail_url' => $photo->thumbnail_url,
            'delivery_url' => $photo->getDeliveryUrl(),
            'error' => $photo->processing_error,
            'processed_at' => $photo->processed_at?->toIso8601String(),
        ]);
    }
}
