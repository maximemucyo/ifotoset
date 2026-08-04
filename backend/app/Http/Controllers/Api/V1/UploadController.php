<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\PhotoResource;
use App\Models\Gallery;
use App\Services\UploadService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UploadController extends Controller
{
    public function __construct(
        protected UploadService $uploadService
    ) {}

    /**
     * Request presigned B2 upload URL.
     * POST /api/v1/uploads/request
     */
    public function requestUpload(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'gallery_id' => ['required', 'string'],
            'filename' => ['required', 'string', 'max:255'],
            'file_size' => ['required', 'integer', 'min:1'],
            'mime_type' => ['required', 'string', 'max:100'],
            'sha256' => ['required', 'string', 'size:64'],
        ]);

        $idempotencyKey = $request->header('Idempotency-Key') ?? md5($validated['gallery_id'] . $validated['filename'] . $validated['sha256']);

        $user = $request->user();
        $gallery = Gallery::where('uuid', $validated['gallery_id'])
            ->where('user_id', $user->id)
            ->firstOrFail();

        try {
            $session = $this->uploadService->createUploadSession(
                $user,
                $gallery,
                $validated['filename'],
                $validated['file_size'],
                $validated['mime_type'],
                $validated['sha256'],
                $idempotencyKey
            );

            return response()->json($session, 201);
        } catch (Exception $e) {
            return response()->json([
                'code' => 'UPLOAD_REQUEST_FAILED',
                'message' => mb_convert_encoding($e->getMessage(), 'UTF-8', 'UTF-8'),
            ], 400);
        }
    }

    /**
     * Confirm successful browser upload to B2.
     * POST /api/v1/uploads/confirm
     */
    public function confirmUpload(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'upload_session_id' => ['required', 'string'],
        ]);

        try {
            $photo = $this->uploadService->confirmUpload(
                $request->user(),
                $validated['upload_session_id']
            );

            return response()->json(new PhotoResource($photo), 200);
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
        $validated = $request->validate([
            'upload_session_id' => ['required', 'string'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $this->uploadService->abortUpload(
            $request->user(),
            $validated['upload_session_id'],
            $validated['reason'] ?? null
        );

        return response()->json(['message' => 'Upload session aborted successfully.'], 200);
    }
}
?>
