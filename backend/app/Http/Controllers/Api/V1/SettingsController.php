<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\ChangePasswordAction;
use App\Actions\UploadAvatarAction;
use App\Actions\UpdateUserProfileAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\V1\ChangePasswordRequest;
use App\Http\Requests\V1\UpdateNotificationsRequest;
use App\Http\Requests\V1\UpdateProfileRequest;
use App\Http\Resources\V1\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    /**
     * Update user profile.
     */
    public function updateProfile(UpdateProfileRequest $request, UpdateUserProfileAction $action): JsonResponse
    {
        $user = $action->execute($request->user(), $request->validated());

        return (new UserResource($user))->response();
    }

    /**
     * Change user password.
     */
    public function changePassword(ChangePasswordRequest $request, ChangePasswordAction $action): JsonResponse
    {
        $action->execute($request->user(), $request->validated());

        return response()->json([
            'message' => 'Password updated successfully.',
        ]);
    }

    /**
     * Update notification preferences.
     */
    public function updateNotifications(UpdateNotificationsRequest $request): JsonResponse
    {
        $user = $request->user();
        $user->update([
            'notification_preferences' => $request->validated(),
        ]);

        return response()->json([
            'message' => 'Notification preferences updated successfully.',
        ]);
    }

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
