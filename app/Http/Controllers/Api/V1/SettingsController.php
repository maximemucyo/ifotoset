<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\UploadAvatarAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    /**
     * Request a presigned upload URL for avatar.
     */
    public function requestAvatarUpload(Request $request, UploadAvatarAction $action): JsonResponse
    {
        $validated = $request->validate([
            'filename' => ['required', 'string', 'max:255'],
            'sha256' => ['required', 'string', 'size:64'],
        ]);

        $payload = $action->requestUpload(
            $request->user(),
            $validated['filename'],
            $validated['sha256']
        );

        return response()->json($payload, 201);
    }

    /**
     * Confirm successful avatar upload.
     */
    public function confirmAvatarUpload(Request $request, UploadAvatarAction $action): JsonResponse
    {
        $validated = $request->validate([
            'object_key' => ['required', 'string'],
        ]);

        try {
            $avatarUrl = $action->confirmUpload(
                $request->user(),
                $validated['object_key']
            );

            return response()->json([
                'message' => 'Avatar updated successfully.',
                'avatar_url' => $avatarUrl,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'code' => 'AVATAR_CONFIRMATION_FAILED',
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}
