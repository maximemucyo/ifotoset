<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Photo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PhotoController extends Controller
{
    /**
     * Delete a photo.
     * DELETE /api/v1/photos/{uuid}
     */
    public function destroy(Request $request, string $uuid): JsonResponse
    {
        $photo = Photo::with('gallery')->where('uuid', $uuid)->firstOrFail();

        $this->authorize('delete', $photo);

        $photo->delete();

        return response()->json(null, 204);
    }
}
