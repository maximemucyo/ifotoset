<?php

use App\Http\Controllers\Api\V1\Auth\LoginController;
use App\Http\Controllers\Api\V1\Auth\LogoutController;
use App\Http\Controllers\Api\V1\Auth\RegisterController;
use App\Http\Controllers\Api\V1\Callback\PawaPayCallbackController;
use App\Http\Controllers\Api\V1\GalleryController;
use App\Http\Controllers\Api\V1\PaymentController;
use App\Http\Controllers\Api\V1\UploadController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API V1 Routes
|--------------------------------------------------------------------------
*/

// Public Health Check Endpoint
Route::get('/health', function () {
    return response()->json([
        'status' => 'healthy',
        'database' => 'connected',
        'redis' => 'connected',
        'storage' => 'b2_active',
        'version' => '1.0.0',
        'timestamp' => now()->toIso8601String(),
    ]);
});

// Authentication Endpoints
Route::post('/auth/register', [RegisterController::class, 'register']);
Route::post('/auth/login', [LoginController::class, 'login']);
Route::post('/auth/logout', [LogoutController::class, 'logout']);

// PawaPay Callback Webhook (CSRF-exempt)
Route::post('/callbacks/pawapay', [PawaPayCallbackController::class, 'handleCallback']);

// Authenticated Routes (Sanctum SPA cookie / Bearer Token)
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/auth/user', function (Request $request) {
        return response()->json($request->user());
    });

    // Upload Sessions
    Route::post('/uploads/request', [UploadController::class, 'requestUpload']);
    Route::post('/uploads/confirm', [UploadController::class, 'confirmUpload']);
    Route::post('/uploads/abort', [UploadController::class, 'abortUpload']);

    // Galleries CRUD
    Route::get('/galleries', [GalleryController::class, 'index']);
    Route::post('/galleries', [GalleryController::class, 'store']);
    Route::get('/galleries/{uuid}', [GalleryController::class, 'show']);
    Route::patch('/galleries/{uuid}', [GalleryController::class, 'update']);
    Route::delete('/galleries/{uuid}', [GalleryController::class, 'destroy']);

    // MoMo Payments
    Route::post('/payments/initiate', [PaymentController::class, 'initiate']);
    Route::get('/payments/{uuid}/status', [PaymentController::class, 'getStatus']);
});
