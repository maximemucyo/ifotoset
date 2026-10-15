<?php

use App\Http\Controllers\Api\V1\Auth\VerificationController;
use App\Http\Controllers\Api\V1\Callback\PawaPayCallbackController;
use App\Http\Controllers\Api\V1\GalleryController;
use App\Http\Controllers\Api\V1\GooglePhotosController;
use App\Http\Controllers\Api\V1\SettingsController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API V1 Routes
|--------------------------------------------------------------------------
|
| Only the endpoints the Blade frontend calls from JavaScript, plus the
| email-verification link and the PawaPay webhook. Everything else is
| served by the web routes.
|
*/

// Public gallery endpoints (gallery page + export page). Slugs are only
// unique per photographer, so every path carries the username.
Route::prefix('public/galleries/{username}/{slug}')
    ->name('api.public.gallery.')
    ->group(function () {
        Route::post('/download', [GalleryController::class, 'recordDownload'])->middleware('throttle:60,1')->name('download');
        Route::post('/favorite', [GalleryController::class, 'toggleFavorite'])->middleware('throttle:60,1')->name('favorite');

        Route::post('/download-zip', [GalleryController::class, 'triggerZipDownload'])->middleware('throttle:30,1')->name('zip');
        Route::get('/download-zip/{id}', [GalleryController::class, 'getZipDownloadStatus'])->middleware('throttle:60,1')->whereNumber('id')->name('zip.status');
        Route::get('/download-zip/{id}/download', [GalleryController::class, 'downloadZipFile'])->middleware('throttle:60,1')->whereNumber('id')->name('zip.file');

        Route::post('/google-photos/authorize', [GooglePhotosController::class, 'authorizePhotos'])->middleware('throttle:15,1')->name('google.authorize');
        Route::get('/google-photos/syncs/{uuid}/status', [GooglePhotosController::class, 'syncStatus'])->middleware('throttle:60,1')->name('google.status');
        Route::post('/google-photos/syncs/{uuid}/notify', [GooglePhotosController::class, 'updateSyncNotification'])->middleware('throttle:60,1')->name('google.notify');
    });

// Signed link from the verification email
Route::get('/auth/verify-email/{id}/{hash}', [VerificationController::class, 'verify'])
    ->middleware(['signed', 'throttle:6,1'])
    ->name('verification.verify');

// PawaPay Callback Webhook (CSRF-exempt)
Route::post('/callbacks/pawapay', [PawaPayCallbackController::class, 'handleCallback'])->middleware('throttle:120,1');

// Studio avatar upload (session-authenticated via Sanctum's stateful API)
Route::middleware(['auth:sanctum', 'verified.api', 'active'])->prefix('settings/avatar')->group(function () {
    Route::post('/request', [SettingsController::class, 'requestAvatarUpload']);
    Route::post('/confirm', [SettingsController::class, 'confirmAvatarUpload']);
});
