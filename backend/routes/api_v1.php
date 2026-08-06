<?php

use App\Http\Controllers\Api\V1\Auth\LoginController;
use App\Http\Controllers\Api\V1\Auth\LogoutController;
use App\Http\Controllers\Api\V1\Auth\RegisterController;
use App\Http\Controllers\Api\V1\Callback\PawaPayCallbackController;
use App\Http\Controllers\Api\V1\GalleryController;
use App\Http\Controllers\Api\V1\PaymentController;
use App\Http\Controllers\Api\V1\UploadController;
use App\Http\Controllers\Api\V1\DashboardController;
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

// Public Gallery Endpoints (Rate Limited)
Route::middleware('throttle:60,1')->get('/public/galleries/{slug}', [GalleryController::class, 'showPublic']);
Route::middleware('throttle:60,1')->get('/public/galleries/{slug}/photos', [GalleryController::class, 'publicPhotos']);
Route::middleware('throttle:10,1')->post('/public/galleries/{slug}/unlock', [GalleryController::class, 'unlockPublic']);
Route::middleware('throttle:60,1')->post('/public/galleries/{slug}/download', [GalleryController::class, 'recordDownload']);
Route::middleware('throttle:60,1')->post('/public/galleries/{slug}/favorite', [GalleryController::class, 'toggleFavorite']);

// Authentication Endpoints
Route::post('/auth/register', [RegisterController::class, 'register']);
Route::post('/auth/login', [LoginController::class, 'login']);
Route::post('/auth/logout', [LogoutController::class, 'logout']);

// PawaPay Callback Webhook (CSRF-exempt)
Route::post('/callbacks/pawapay', [PawaPayCallbackController::class, 'handleCallback']);

// Authenticated Routes (Sanctum SPA cookie / Bearer Token)
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/auth/user', function (Request $request) {
        $user = $request->user();
        $storageStats = app(\App\Services\StorageStatisticsService::class)->getStorageStats($user);
        return response()->json([
            'data' => [
                'user' => [
                    'uuid' => $user->uuid,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'plan' => $user->plan->slug ?? 'free',
                    'storage' => $storageStats,
                ],
                'permissions' => $user->role === 'admin'
                    ? ['admin.access', 'galleries.manage']
                    : ['galleries.manage'],
            ]
        ]);
    });

    Route::get('/plans', function () {
        return response()->json([
            'data' => \App\Models\Plan::all()->map(function ($plan) {
                return [
                    'uuid' => $plan->uuid,
                    'slug' => $plan->slug,
                    'name' => $plan->name,
                    'monthly_price' => (float) $plan->monthly_price,
                    'annual_price' => (float) $plan->annual_price,
                    'currency' => $plan->currency,
                    'storage_limit' => $plan->storage_limit,
                    'video_limit' => $plan->video_limit,
                    'gallery_limit' => $plan->gallery_limit,
                    'team_limit' => $plan->team_limit,
                ];
            })
        ]);
    });

    Route::get('/dashboard', [DashboardController::class, 'index']);

    // Admin Panel Routes
    Route::middleware('can:access-admin')->prefix('admin')->group(function () {
        Route::get('/dashboard', [\App\Http\Controllers\Api\V1\Admin\AdminController::class, 'dashboard']);
        Route::get('/queue', [\App\Http\Controllers\Api\V1\Admin\AdminController::class, 'queue']);
        Route::get('/users', [\App\Http\Controllers\Api\V1\Admin\AdminController::class, 'users']);
        Route::get('/galleries', [\App\Http\Controllers\Api\V1\Admin\AdminController::class, 'galleries']);
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

    // Photos CRUD
    Route::delete('/photos/{uuid}', [\App\Http\Controllers\Api\V1\PhotoController::class, 'destroy']);

    // Trash Management
    Route::get('/trash', [\App\Http\Controllers\Api\V1\TrashController::class, 'index']);
    Route::post('/trash/restore', [\App\Http\Controllers\Api\V1\TrashController::class, 'restore']);
    Route::delete('/trash/purge', [\App\Http\Controllers\Api\V1\TrashController::class, 'purge']);
    Route::post('/trash/empty', [\App\Http\Controllers\Api\V1\TrashController::class, 'empty']);

    // MoMo Payments
    Route::post('/payments/initiate', [PaymentController::class, 'initiate']);
    Route::get('/payments/{uuid}/status', [PaymentController::class, 'getStatus']);
});
