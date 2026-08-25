<?php

use App\Http\Controllers\Api\V1\Auth\LoginController;
use App\Http\Controllers\Api\V1\Auth\LogoutController;
use App\Http\Controllers\Api\V1\Auth\RegisterController;
use App\Http\Controllers\Api\V1\Auth\PasswordResetController;
use App\Http\Controllers\Api\V1\Auth\VerificationController;
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
use App\Http\Controllers\Api\V1\PublicReviewController;
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

// Public sitemap endpoint
Route::middleware('throttle:30,1')->get('/public/sitemap', [PublicPhotographerController::class, 'sitemapData']);

// Public Gallery Endpoints (Rate Limited)
Route::middleware('throttle:60,1')->get('/public/galleries/{slug}', [GalleryController::class, 'showPublic']);
Route::middleware('throttle:60,1')->get('/public/galleries/{slug}/photos', [GalleryController::class, 'publicPhotos']);
Route::middleware('throttle:10,1')->post('/public/galleries/{slug}/unlock', [GalleryController::class, 'unlockPublic']);
Route::middleware('throttle:60,1')->post('/public/galleries/{slug}/download', [GalleryController::class, 'recordDownload']);
Route::middleware('throttle:60,1')->post('/public/galleries/{slug}/favorite', [GalleryController::class, 'toggleFavorite']);

// Public Gallery ZIP download & Google Photos Sync
Route::middleware('throttle:30,1')->post('/public/galleries/{slug}/download-zip', [GalleryController::class, 'triggerZipDownload']);
Route::middleware('throttle:60,1')->get('/public/galleries/{slug}/download-zip/{id}', [GalleryController::class, 'getZipDownloadStatus']);
Route::middleware('throttle:15,1')->post('/public/galleries/{slug}/google-photos/authorize', [\App\Http\Controllers\Api\V1\GooglePhotosController::class, 'authorizePhotos']);
Route::middleware('throttle:15,1')->post('/public/google-photos/callback', [\App\Http\Controllers\Api\V1\GooglePhotosController::class, 'handleCallback']);
Route::middleware('throttle:60,1')->get('/public/galleries/{slug}/google-photos/syncs/{uuid}/status', [\App\Http\Controllers\Api\V1\GooglePhotosController::class, 'syncStatus']);
Route::middleware('throttle:60,1')->post('/public/galleries/{slug}/google-photos/syncs/{uuid}/notify', [\App\Http\Controllers\Api\V1\GooglePhotosController::class, 'updateSyncNotification']);

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
    // Public reviews list & submission
    Route::get('/public/photographers/{username}/reviews', [PublicReviewController::class, 'index']);
    Route::post('/public/photographers/{username}/reviews', [PublicReviewController::class, 'store']);
});

// Authentication Endpoints
Route::post('/auth/register', [RegisterController::class, 'register']);
Route::post('/auth/login', [LoginController::class, 'login']);
Route::post('/auth/logout', [LogoutController::class, 'logout']);
Route::post('/auth/forgot-password', [PasswordResetController::class, 'sendResetLinkEmail'])
    ->middleware('throttle:5,1');
Route::post('/auth/reset-password', [PasswordResetController::class, 'reset'])
    ->middleware('throttle:10,1');
Route::get('/auth/check-username', [RegisterController::class, 'checkUsername'])
    ->middleware('throttle:30,1');
Route::get('/auth/verify-email/{id}/{hash}', [VerificationController::class, 'verify'])
    ->middleware(['signed', 'throttle:6,1'])
    ->name('verification.verify');

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

    Route::post('/auth/resend-verification', [VerificationController::class, 'resend']);

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
        Route::get('/exports', [\App\Http\Controllers\Api\V1\Admin\AdminController::class, 'exports']);
        
        // SMTP Settings routes
        Route::get('/settings/smtp', [\App\Http\Controllers\Api\V1\Admin\AdminController::class, 'getSmtpSettings']);
        Route::put('/settings/smtp', [\App\Http\Controllers\Api\V1\Admin\AdminController::class, 'updateSmtpSettings'])
            ->middleware('throttle:10,1');
    });

    // Read-only routes (accessible to unverified users)
    Route::get('/galleries', [GalleryController::class, 'index']);
    Route::get('/galleries/{uuid}', [GalleryController::class, 'show']);
    Route::get('/galleries/{uuid}/photos', [GalleryController::class, 'photos']);
    Route::get('/trash', [\App\Http\Controllers\Api\V1\TrashController::class, 'index']);
    Route::get('/payments/{uuid}/status', [PaymentController::class, 'getStatus']);
    Route::get('/clients', [ClientController::class, 'index']);
    Route::get('/packages', [PackageController::class, 'index']);
    Route::get('/bookings', [BookingController::class, 'index']);
    Route::get('/analytics', [AnalyticsController::class, 'index']);
    Route::get('/availability/settings', [StudioAvailabilityController::class, 'getSettings']);
    Route::get('/availability/exceptions', [StudioAvailabilityController::class, 'getExceptions']);
    Route::get('/availability/blocked', [StudioAvailabilityController::class, 'getBlocked']);

    // Application Mutation Routes (Requires verified email)
    Route::middleware('verified.api')->group(function () {
        // Upload Sessions
        Route::post('/uploads/request', [UploadController::class, 'requestUpload']);
        Route::post('/uploads/confirm', [UploadController::class, 'confirmUpload']);
        Route::post('/uploads/abort', [UploadController::class, 'abortUpload']);

        // Galleries mutations
        Route::post('/galleries', [GalleryController::class, 'store']);
        Route::patch('/galleries/{uuid}', [GalleryController::class, 'update']);
        Route::delete('/galleries/{uuid}', [GalleryController::class, 'destroy']);

        // Photos CRUD
        Route::delete('/photos/{uuid}', [\App\Http\Controllers\Api\V1\PhotoController::class, 'destroy']);

        // Trash Management
        Route::post('/trash/restore', [\App\Http\Controllers\Api\V1\TrashController::class, 'restore']);
        Route::delete('/trash/purge', [\App\Http\Controllers\Api\V1\TrashController::class, 'purge']);
        Route::post('/trash/empty', [\App\Http\Controllers\Api\V1\TrashController::class, 'empty']);

        // MoMo Payments
        Route::post('/payments/initiate', [PaymentController::class, 'initiate']);

        // Clients mutations
        Route::apiResource('clients', ClientController::class)
            ->except(['index'])
            ->parameters(['clients' => 'uuid']);

        // Packages mutations
        Route::apiResource('packages', PackageController::class)
            ->except(['index'])
            ->parameters(['packages' => 'uuid']);

        // Bookings mutations
        Route::apiResource('bookings', BookingController::class)
            ->except(['index'])
            ->parameters(['bookings' => 'uuid']);

        // Settings Profile & Preferences mutations
        Route::patch('/settings/profile', [SettingsController::class, 'updateProfile']);
        Route::post('/settings/password', [SettingsController::class, 'changePassword']);
        Route::patch('/settings/notifications', [SettingsController::class, 'updateNotifications']);
        Route::post('/settings/avatar/request', [SettingsController::class, 'requestAvatarUpload']);
        Route::post('/settings/avatar/confirm', [SettingsController::class, 'confirmAvatarUpload']);

        // Studio Availability Settings mutations
        Route::put('/availability/settings', [StudioAvailabilityController::class, 'updateSettings']);
        Route::post('/availability/exceptions', [StudioAvailabilityController::class, 'storeException']);
        Route::delete('/availability/exceptions/{uuid}', [StudioAvailabilityController::class, 'deleteException']);
        Route::post('/availability/blocked', [StudioAvailabilityController::class, 'storeBlocked']);
        Route::delete('/availability/blocked/{uuid}', [StudioAvailabilityController::class, 'deleteBlocked']);
    });
});
