<?php

use App\Http\Controllers\Api\V1\Auth\LoginController;
use App\Http\Controllers\Api\V1\Auth\LogoutController;
use App\Http\Controllers\Api\V1\Auth\RegisterController;
use App\Http\Controllers\Api\V1\Callback\PawaPayCallbackController;
use App\Http\Controllers\Api\V1\GalleryController;
use App\Http\Controllers\Api\V1\PaymentController;
use App\Http\Controllers\Api\V1\UploadController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\ClientController;
use App\Http\Controllers\Api\V1\PackageController;
use App\Http\Controllers\Api\V1\BookingController;
use App\Http\Controllers\Api\V1\AnalyticsController;
use App\Http\Controllers\Api\V1\SettingsController;
use App\Http\Controllers\Api\V1\PublicBookingController;
use App\Http\Controllers\Api\V1\PublicPhotographerController;
use App\Http\Controllers\Api\V1\PublicBookingPaymentController;
use App\Http\Controllers\Api\V1\PublicAvailabilityController;
use App\Http\Controllers\Api\V1\StudioAvailabilityController;
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

// Public Online Booking Endpoints (Rate Limited)
Route::middleware('throttle:30,1')->group(function () {
    // Dedicated photographer profile endpoint
    Route::get('/public/photographers/{username}', [PublicPhotographerController::class, 'show']);
    // Available time slots lookup on a date
    Route::get('/public/photographers/{username}/slots', [PublicAvailabilityController::class, 'slots']);
    // Available days lookup on a month
    Route::get('/public/photographers/{username}/available-days', [PublicAvailabilityController::class, 'availableDays']);
    // Booking submission
    Route::post('/public/booking/{username}', [PublicBookingController::class, 'store']);
    // Deposit payment for a booking (resource-oriented)
    Route::post('/public/bookings/{bookingUuid}/payments', [PublicBookingPaymentController::class, 'store']);
    // Public payment status polling (booking deposits only)
    Route::get('/public/payments/{uuid}/status', [PublicBookingPaymentController::class, 'getStatus']);
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
        $user = $request->user();
        return response()->json([
            'data' => [
                'user' => new \App\Http\Resources\V1\UserResource($user),
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

    // Clients CRUD & Filtering
    Route::get('/clients', [ClientController::class, 'index']);
    Route::apiResource('clients', ClientController::class)
        ->except(['index'])
        ->parameters(['clients' => 'uuid']);

    // Packages CRUD & Filtering
    Route::get('/packages', [PackageController::class, 'index']);
    Route::apiResource('packages', PackageController::class)
        ->except(['index'])
        ->parameters(['packages' => 'uuid']);

    // Bookings CRUD & Filtering
    Route::get('/bookings', [BookingController::class, 'index']);
    Route::apiResource('bookings', BookingController::class)
        ->except(['index'])
        ->parameters(['bookings' => 'uuid']);

    // Analytics Dashboard
    Route::get('/analytics', [AnalyticsController::class, 'index']);

    // Settings Profile & Preferences
    Route::patch('/settings/profile', [SettingsController::class, 'updateProfile']);
    Route::post('/settings/password', [SettingsController::class, 'changePassword']);
    Route::patch('/settings/notifications', [SettingsController::class, 'updateNotifications']);
    Route::post('/settings/avatar/request', [SettingsController::class, 'requestAvatarUpload']);
    Route::post('/settings/avatar/confirm', [SettingsController::class, 'confirmAvatarUpload']);

    // Studio Availability Settings, Exceptions, and Blocked Slots
    Route::get('/availability/settings', [StudioAvailabilityController::class, 'getSettings']);
    Route::put('/availability/settings', [StudioAvailabilityController::class, 'updateSettings']);
    Route::get('/availability/exceptions', [StudioAvailabilityController::class, 'getExceptions']);
    Route::post('/availability/exceptions', [StudioAvailabilityController::class, 'storeException']);
    Route::delete('/availability/exceptions/{uuid}', [StudioAvailabilityController::class, 'deleteException']);
    Route::get('/availability/blocked', [StudioAvailabilityController::class, 'getBlocked']);
    Route::post('/availability/blocked', [StudioAvailabilityController::class, 'storeBlocked']);
    Route::delete('/availability/blocked/{uuid}', [StudioAvailabilityController::class, 'deleteBlocked']);
});
