<?php

namespace App\Http\Controllers\Web\Studio;

use App\Http\Controllers\Controller;
use App\Http\Resources\PhotoResource;
use App\Models\Gallery;
use App\Services\UploadService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class UploadController extends Controller
{
    public function __construct(
        protected UploadService $uploadService
    ) {}

    /**
     * Request presigned upload URL.
     * POST /api/v1/uploads/request
     */
    public function requestUpload(Request $request): JsonResponse
    {
        // Support parameter aliases across Web & API clients
        if (!$request->has('gallery_id') && $request->has('gallery_uuid')) {
            $request->merge(['gallery_id' => $request->input('gallery_uuid')]);
        }
        if (!$request->has('file_size') && $request->has('size_bytes')) {
            $request->merge(['file_size' => $request->input('size_bytes')]);
        }
        if (!$request->filled('sha256')) {
            $request->merge(['sha256' => hash('sha256', ($request->input('gallery_id') ?? '') . ($request->input('filename') ?? '') . microtime(true))]);
        }

        $validated = $request->validate([
            'gallery_id' => ['required', 'string'],
            'filename' => ['required', 'string', 'max:255'],
            'file_size' => ['required', 'integer', 'min:1'],
            'mime_type' => ['required', 'string', 'max:100'],
            'sha256' => ['required', 'string', 'size:64'],
        ]);

        if (\App\Support\MediaTypes::forGalleryUpload($validated['filename'], $validated['mime_type']) === null) {
            return response()->json([
                'code' => 'UNSUPPORTED_FILE_TYPE',
                'message' => 'Only JPEG, PNG, WebP and AVIF photos, or MP4, MOV and WebM videos can be uploaded.',
                'errors' => ['filename' => ['This file type is not supported.']],
            ], 422);
        }

        $idempotencyKey = $request->header('Idempotency-Key') ?? md5($validated['gallery_id'] . $validated['filename'] . $validated['sha256']);

        $user = $request->account();
        $gallery = Gallery::where('uuid', $validated['gallery_id'])
            ->where('user_id', $user->id)
            ->firstOrFail();

        $declaredDuration = $request->input('duration_seconds') ?? $request->input('duration');
        if ($declaredDuration !== null) {
            $declaredDuration = (int) $declaredDuration;
        }

        // Members can't open Billing: send no upgrade link and point them at the owner instead.
        $canManageBilling = Gate::allows('studio.billing');
        $upgradeUrl = $canManageBilling ? route('studio.billing.index') : null;
        $planMessage = fn (Exception $e) => $canManageBilling
            ? $e->getMessage()
            : rtrim(preg_replace('/\s*Please upgrade.*$/', '', $e->getMessage()), '.') . '. Ask the studio owner or a manager to upgrade the plan.';

        try {
            $session = $this->uploadService->createUploadSession(
                $user,
                $gallery,
                $validated['filename'],
                $validated['file_size'],
                $validated['mime_type'],
                $validated['sha256'],
                $idempotencyKey,
                $declaredDuration
            );

            return response()->json($session, 201);
        } catch (\App\Exceptions\VideoNotSupportedOnPlanException $e) {
            return response()->json([
                'code' => 'VIDEO_NOT_SUPPORTED_ON_PLAN',
                'is_video_error' => true,
                'message' => $planMessage($e),
                'upgrade_url' => $upgradeUrl,
            ], 409);
        } catch (\App\Exceptions\VideoQuotaExceededException $e) {
            return response()->json([
                'code' => 'VIDEO_QUOTA_EXCEEDED',
                'is_video_error' => true,
                'message' => $planMessage($e),
                'required_seconds' => $e->requiredSeconds,
                'available_seconds' => $e->availableSeconds,
                'limit_seconds' => $e->limitSeconds,
                'used_seconds' => $e->usedSeconds,
                'reserved_seconds' => $e->reservedSeconds,
                'upgrade_url' => $upgradeUrl,
            ], 409);
        } catch (\App\Exceptions\VideoFileSizeExceededException $e) {
            return response()->json([
                'code' => 'VIDEO_FILE_SIZE_EXCEEDED',
                'is_video_error' => true,
                'message' => $planMessage($e),
                'file_size_bytes' => $e->fileSizeBytes,
                'max_allowed_bytes' => $e->maxAllowedBytes,
                'upgrade_url' => $upgradeUrl,
            ], 413);
        } catch (\App\Exceptions\StorageQuotaExceededException $e) {
            return response()->json([
                'code' => 'STORAGE_QUOTA_EXCEEDED',
                'is_quota_error' => true,
                'message' => 'Storage limit exceeded for your current plan.',
                'required_bytes' => $e->requiredBytes,
                'available_bytes' => $e->availableBytes,
                'limit_bytes' => $e->limitBytes,
                'used_bytes' => $e->usedBytes,
                'reserved_bytes' => $e->reservedBytes,
                'upgrade_url' => $upgradeUrl,
            ], 409);
        } catch (Exception $e) {
            return response()->json([
                'code' => 'UPLOAD_REQUEST_FAILED',
                'message' => mb_convert_encoding($e->getMessage(), 'UTF-8', 'UTF-8'),
            ], 400);
        }
    }

    /**
     * Confirm successful browser upload.
     * POST /api/v1/uploads/confirm
     */
    public function confirmUpload(Request $request): JsonResponse
    {
        if (!$request->has('upload_session_id') && $request->has('session_id')) {
            $request->merge(['upload_session_id' => $request->input('session_id')]);
        }

        $validated = $request->validate([
            'upload_session_id' => ['required', 'string'],
        ]);

        try {
            $photo = $this->uploadService->confirmUpload(
                $request->account(),
                $validated['upload_session_id']
            );

            return (new PhotoResource($photo))->response();
        } catch (Exception $e) {
            return response()->json([
                'code' => 'UPLOAD_CONFIRMATION_FAILED',
                'message' => mb_convert_encoding($e->getMessage(), 'UTF-8', 'UTF-8'),
            ], 422);
        }
    }

    /**
     * Abort in-progress upload session.
     * POST /api/v1/uploads/abort
     */
    public function abortUpload(Request $request): JsonResponse
    {
        if (!$request->has('upload_session_id') && $request->has('session_id')) {
            $request->merge(['upload_session_id' => $request->input('session_id')]);
        }

        $validated = $request->validate([
            'upload_session_id' => ['required', 'string'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $this->uploadService->abortUpload(
            $request->account(),
            $validated['upload_session_id'],
            $validated['reason'] ?? null
        );

        return response()->json(['message' => 'Upload session aborted successfully.'], 200);
    }
}
?>
