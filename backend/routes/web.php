<?php

use App\Http\Controllers\Web\Admin\AnalyticsController as AdminAnalyticsController;
use App\Http\Controllers\Web\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Web\Admin\GalleryController as AdminGalleryController;
use App\Http\Controllers\Web\Admin\JobMonitorController;
use App\Http\Controllers\Web\Admin\ModerationController as AdminModerationController;
use App\Http\Controllers\Web\Admin\PaymentController as AdminPaymentController;
use App\Http\Controllers\Web\Admin\PlanController as AdminPlanController;
use App\Http\Controllers\Web\Admin\SettingsController as AdminSettingsController;
use App\Http\Controllers\Web\Admin\SupportController as AdminSupportController;
use App\Http\Controllers\Web\Admin\UserController as AdminUserController;
use App\Http\Controllers\Web\Auth\LoginController;
use App\Http\Controllers\Web\Auth\PasswordResetController;
use App\Http\Controllers\Web\Auth\RegisterController;
use App\Http\Controllers\Web\HomeController;
use App\Http\Controllers\Web\PublicGalleryController;
use App\Http\Controllers\Web\PublicPhotographerController;
use App\Http\Controllers\Web\Studio\AnalyticsController as StudioAnalyticsController;
use App\Http\Controllers\Web\Studio\AvailabilityController as StudioAvailabilityController;
use App\Http\Controllers\Web\Studio\BillingController as StudioBillingController;
use App\Http\Controllers\Web\Studio\BookingController as StudioBookingController;
use App\Http\Controllers\Web\Studio\ClientController as StudioClientController;
use App\Http\Controllers\Web\Studio\DashboardController as StudioDashboardController;
use App\Http\Controllers\Web\Studio\GalleryController as StudioGalleryController;
use App\Http\Controllers\Web\Studio\PackageController as StudioPackageController;
use App\Http\Controllers\Web\Studio\SettingsController as StudioSettingsController;
use App\Http\Controllers\Web\Studio\TrashController as StudioTrashController;
use App\Http\Controllers\Api\V1\UploadController;
use App\Models\Gallery;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes (Laravel Full-Stack Monolith)
|--------------------------------------------------------------------------
*/

// Subdomain Photographer & Gallery Routes ({username}.{rootDomain}/{slug})
$rootHost = config('app.public_root_host', 'localhost');
$reservedWords = config('reserved_usernames', []);
$escapedReserved = !empty($reservedWords)
    ? implode('|', array_map(fn ($u) => preg_quote($u, '#'), $reservedWords))
    : 'www|api|admin|studio|root';

// 1. Canonical Redirect for www.{rootHost} -> {rootHost}
Route::domain('www.' . $rootHost)->group(function () {
    Route::any('{any?}', function (Request $request) {
        $rootHost = config('app.public_root_host', 'ifotoset.com');
        $protocol = config('app.public_protocol', $request->getScheme());
        $port = config('app.public_root_port');
        $portSuffix = ($port && !in_array((int) $port, [80, 443], true)) ? ":{$port}" : '';
        $uri = $request->getRequestUri();
        return redirect("{$protocol}://{$rootHost}{$portSuffix}{$uri}", 301);
    })->where('any', '.*');
});

// 2. Subdomain Photographer & Gallery Routes ({username}.{rootDomain}/{slug})
Route::domain('{username}.' . $rootHost)
    ->where(['username' => '(?!(?:' . $escapedReserved . ')(?:\.|$))[a-zA-Z0-9_\-]+'])
    ->group(function () {
        Route::get('/{slug}/photos/{uuid}/download', [PublicGalleryController::class, 'downloadPhoto'])->name('subdomain.gallery.photo.download');
        Route::get('/{slug}/photos', [PublicGalleryController::class, 'photos'])->name('subdomain.gallery.photos');
        Route::get('/{slug}/export', [PublicGalleryController::class, 'export'])->name('subdomain.gallery.export');
        Route::post('/{slug}/unlock', [PublicGalleryController::class, 'unlock'])->middleware('throttle:10,1')->name('subdomain.gallery.unlock');
        Route::get('/{slug}', [PublicGalleryController::class, 'show'])->name('subdomain.gallery');

        Route::post('/book', [PublicPhotographerController::class, 'book'])->name('subdomain.photographer.book');
        Route::get('/', [PublicPhotographerController::class, 'show'])->name('subdomain.photographer');
    });

// Public Landing Page
Route::get('/', [HomeController::class, 'index'])->name('home');

// Guest Authentication Routes
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [LoginController::class, 'login']);

    Route::get('/register', [RegisterController::class, 'showRegistrationForm'])->name('register');
    Route::post('/register', [RegisterController::class, 'register']);

    Route::get('/forgot-password', [PasswordResetController::class, 'showLinkRequestForm'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'sendResetLinkEmail'])->name('password.email');

    Route::get('/reset-password/{token?}', [PasswordResetController::class, 'showResetForm'])->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'reset'])->name('password.update');
});

// Authenticated Logout & Email Verification
Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');
    Route::post('/auth/impersonation/leave', [\App\Http\Controllers\Web\Auth\ImpersonationController::class, 'leave'])->name('impersonation.leave');

    Route::get('/email/verify', function () {
        return auth()->user()->hasVerifiedEmail()
            ? redirect()->route('studio.dashboard')
            : view('auth.verify-email');
    })->name('verification.notice');

    Route::post('/email/verification-notification', function (Request $request) {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->route('studio.dashboard');
        }
        $request->user()->sendEmailVerificationNotification();
        return back()->with('status', 'verification-link-sent');
    })->middleware(['throttle:6,1'])->name('verification.send');
});

// Public Photographer & Gallery Routes (Apex Domain Fallbacks / 302 to Canonical Subdomain)
Route::get('/p/{username}', function (Request $request, string $username) {
    $target = app(\App\Services\PublicUrlService::class)->photographerUrl($username);
    $query = $request->getQueryString();
    return redirect($query ? "{$target}?{$query}" : $target, 302);
})->name('public.photographer');

Route::post('/p/{username}/book', [PublicPhotographerController::class, 'book'])->name('public.photographer.book');

Route::get('/p/{username}/{slug}', function (Request $request, string $username, string $slug) {
    $target = app(\App\Services\PublicUrlService::class)->galleryUrl($username, $slug);
    $query = $request->getQueryString();
    return redirect($query ? "{$target}?{$query}" : $target, 302);
})->name('public.gallery');

Route::get('/p/{username}/{slug}/photos', [PublicGalleryController::class, 'photos'])->name('public.gallery.photos');
Route::get('/p/{username}/{slug}/photos/{uuid}/download', [PublicGalleryController::class, 'downloadPhoto'])->name('public.gallery.photo.download');
Route::get('/p/{username}/{slug}/export', [PublicGalleryController::class, 'export'])->name('public.gallery.export');
Route::post('/p/{username}/{slug}/unlock', [PublicGalleryController::class, 'unlock'])->middleware('throttle:10,1')->name('public.gallery.unlock');

// Shortlink Redirect (/g/{slug} -> canonical subdomain URL)
Route::get('/g/{slug}', function (Request $request, string $slug) {
    $gallery = Gallery::where('slug', $slug)->with('user')->firstOrFail();
    $target = app(\App\Services\PublicUrlService::class)->galleryUrl($gallery->user->username, $gallery->slug);
    $query = $request->getQueryString();
    return redirect($query ? "{$target}?{$query}" : $target, 302);
});

// Convenience Root Redirects
Route::middleware('auth')->group(function () {
    Route::get('/studio', fn () => redirect()->route('studio.dashboard'));
    Route::get('/admin', fn () => redirect()->route('admin.dashboard'));
});

// Studio Portal Routes (Authenticated Photographers & Media Houses)
Route::middleware(['auth'])->prefix('studio')->as('studio.')->group(function () {
    Route::get('/dashboard', [StudioDashboardController::class, 'index'])->name('dashboard');

    // Galleries CRUD & Photo Management
    Route::get('/galleries', [StudioGalleryController::class, 'index'])->name('galleries.index');
    Route::get('/galleries/create', [StudioGalleryController::class, 'create'])->name('galleries.create');
    Route::post('/galleries', [StudioGalleryController::class, 'store'])->name('galleries.store');
    Route::get('/galleries/invitations/template', [StudioGalleryController::class, 'downloadInvitationTemplate'])->name('galleries.invitations.template');
    Route::get('/galleries/{uuid}', [StudioGalleryController::class, 'show'])->name('galleries.show');
    Route::get('/galleries/{uuid}/edit', [StudioGalleryController::class, 'edit'])->name('galleries.edit');
    Route::patch('/galleries/{uuid}', [StudioGalleryController::class, 'update'])->name('galleries.update');
    Route::delete('/galleries/{uuid}', [StudioGalleryController::class, 'destroy'])->name('galleries.destroy');
    Route::post('/galleries/{uuid}/cover', [StudioGalleryController::class, 'setCover'])->name('galleries.cover');
    Route::get('/galleries/{uuid}/photos', [StudioGalleryController::class, 'photos'])->name('galleries.photos');
    Route::patch('/galleries/{uuid}/photos/{photoUuid}/hide', [StudioGalleryController::class, 'toggleHidePhoto'])->name('galleries.photos.hide');
    Route::delete('/galleries/{uuid}/photos/{photoUuid}', [StudioGalleryController::class, 'destroyPhoto'])->name('galleries.photos.destroy');
    Route::post('/galleries/{uuid}/invitations', [StudioGalleryController::class, 'addInvitations'])->name('galleries.invitations.store');
    Route::post('/galleries/{uuid}/invitations/{id}/resend', [StudioGalleryController::class, 'resendInvitation'])->name('galleries.invitations.resend');
    Route::post('/galleries/{uuid}/invitations/{id}/revoke', [StudioGalleryController::class, 'revokeInvitation'])->name('galleries.invitations.revoke');

    // Direct Browser Photo Uploads
    Route::post('/uploads/request', [UploadController::class, 'requestUpload'])->name('uploads.request');
    Route::post('/uploads/confirm', [UploadController::class, 'confirmUpload'])->name('uploads.confirm');
    Route::post('/uploads/abort', [UploadController::class, 'abortUpload'])->name('uploads.abort');
    Route::get('/storage/stats', function (\Illuminate\Http\Request $request, \App\Services\StorageStatisticsService $service) {
        return response()->json($service->getStorageStats($request->user()));
    })->name('storage.stats');

    // Bookings
    Route::get('/bookings', [StudioBookingController::class, 'index'])->name('bookings.index');
    Route::patch('/bookings/{uuid}/status', [StudioBookingController::class, 'updateStatus'])->name('bookings.status');

    // Availability & Scheduling Suite
    Route::get('/availability', [StudioAvailabilityController::class, 'index'])->name('availability.index');
    Route::post('/availability/settings', [StudioAvailabilityController::class, 'updateSettings'])->name('availability.settings');
    Route::post('/availability/exceptions', [StudioAvailabilityController::class, 'storeException'])->name('availability.exceptions.store');
    Route::post('/availability/exceptions/{uuid}/delete', [StudioAvailabilityController::class, 'deleteException'])->name('availability.exceptions.destroy');
    Route::post('/availability/blocked', [StudioAvailabilityController::class, 'storeBlocked'])->name('availability.blocked.store');
    Route::post('/availability/blocked/{uuid}/delete', [StudioAvailabilityController::class, 'deleteBlocked'])->name('availability.blocked.destroy');

    // Clients CRM
    Route::get('/clients', [StudioClientController::class, 'index'])->name('clients.index');
    Route::post('/clients', [StudioClientController::class, 'store'])->name('clients.store');
    Route::patch('/clients/{uuid}', [StudioClientController::class, 'update'])->name('clients.update');
    Route::post('/clients/{uuid}/delete', [StudioClientController::class, 'destroy'])->name('clients.destroy');

    // Photography Packages
    Route::get('/packages', [StudioPackageController::class, 'index'])->name('packages.index');
    Route::post('/packages', [StudioPackageController::class, 'store'])->name('packages.store');
    Route::patch('/packages/{uuid}', [StudioPackageController::class, 'update'])->name('packages.update');
    Route::post('/packages/{uuid}/toggle', [StudioPackageController::class, 'toggle'])->name('packages.toggle');
    Route::post('/packages/{uuid}/delete', [StudioPackageController::class, 'destroy'])->name('packages.destroy');

    // Studio Analytics
    Route::get('/analytics', [StudioAnalyticsController::class, 'index'])->name('analytics.index');

    // Subscription Billing & Checkout Suite
    Route::get('/billing', [StudioBillingController::class, 'index'])->name('billing.index');
    Route::get('/billing/checkout/{plan:slug}', [StudioBillingController::class, 'checkout'])->name('billing.checkout');
    Route::post('/billing/initiate', [StudioBillingController::class, 'initiate'])->name('billing.initiate');
    Route::get('/billing/check/{uuid}', [StudioBillingController::class, 'check'])->name('billing.check');
    Route::get('/billing/receipt/{uuid}', [StudioBillingController::class, 'receipt'])->name('billing.receipt');

    // Settings
    Route::get('/settings', [StudioSettingsController::class, 'index'])->name('settings.index');
    Route::post('/settings/profile', [StudioSettingsController::class, 'updateProfile'])->name('settings.profile');
    Route::post('/settings/password', [StudioSettingsController::class, 'updatePassword'])->name('settings.password');
    Route::post('/settings/notifications', [StudioSettingsController::class, 'updateNotifications'])->name('settings.notifications');

    // Trash & Lifecycle
    Route::get('/trash', [StudioTrashController::class, 'index'])->name('trash.index');
    Route::match(['POST', 'DELETE'], '/trash/restore', [StudioTrashController::class, 'restore'])->name('trash.restore');
    Route::match(['POST', 'DELETE'], '/trash/purge', [StudioTrashController::class, 'purge'])->name('trash.purge');
    Route::match(['POST', 'DELETE'], '/trash/empty', [StudioTrashController::class, 'empty'])->name('trash.empty');
});

// Admin Panel Routes (Superadmin Only - Guarded by AdminMiddleware & Impersonation Prevention)
Route::middleware(['auth', 'admin', 'impersonation.prevent_admin'])->prefix('admin')->as('admin.')->group(function () {
    Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');

    // User Directory & Role/Status/Lifecycle Management
    Route::get('/users', [AdminUserController::class, 'index'])->name('users.index');
    Route::get('/users-legacy', [AdminUserController::class, 'index'])->name('users');
    Route::get('/users/{id}/details', [AdminUserController::class, 'details'])->name('users.details');
    Route::post('/users/{id}/status', [AdminUserController::class, 'toggleStatus'])->name('users.status');
    Route::post('/users/{id}/role', [AdminUserController::class, 'changeRole'])->name('users.role');
    Route::post('/users/{id}/plan', [AdminUserController::class, 'assignPlan'])->name('users.plan');
    Route::post('/users/{id}/plan/revoke', [AdminUserController::class, 'revokePlan'])->name('users.plan.revoke');
    Route::post('/users/{id}/email/verify', [AdminUserController::class, 'verifyEmail'])->name('users.email.verify');
    Route::post('/users/{id}/email/unverify', [AdminUserController::class, 'unverifyEmail'])->name('users.email.unverify');
    Route::post('/users/{id}/email/resend', [AdminUserController::class, 'resendVerification'])->name('users.email.resend');
    Route::post('/users/{id}/password-reset', [AdminUserController::class, 'passwordReset'])->name('users.password_reset');
    Route::post('/users/{id}/impersonate', [AdminUserController::class, 'impersonate'])->name('users.impersonate');
    Route::delete('/users/{id}', [AdminUserController::class, 'destroy'])->name('users.destroy');
    Route::post('/users/{id}/notes', [AdminUserController::class, 'addNote'])->name('users.notes');
    Route::post('/audit-logs/{id}/reveal-ip', [AdminUserController::class, 'revealIp'])->name('audit.reveal_ip');

    // Galleries Overview & Moderation Controls
    Route::get('/galleries', [AdminGalleryController::class, 'index'])->name('galleries.index');
    Route::get('/galleries-legacy', [AdminGalleryController::class, 'index'])->name('galleries');
    Route::get('/galleries/{uuid}/preview', [AdminGalleryController::class, 'preview'])->name('galleries.preview');
    Route::post('/galleries/{uuid}/visibility', [AdminGalleryController::class, 'toggleVisibility'])->name('galleries.visibility');
    Route::post('/galleries/{uuid}/takedown', [AdminGalleryController::class, 'takeDown'])->name('galleries.takedown');
    Route::post('/galleries/{uuid}/restore', [AdminGalleryController::class, 'restore'])->name('galleries.restore');

    // Financial Transactions & Revenue Tracking
    Route::get('/payments', [AdminPaymentController::class, 'index'])->name('payments.index');
    Route::post('/payments/{id}/sync', [AdminPaymentController::class, 'syncStatus'])->name('payments.sync');

    // Subscription Plans & Quota Configuration
    Route::get('/plans', [AdminPlanController::class, 'index'])->name('plans.index');
    Route::put('/plans/{plan:slug}', [AdminPlanController::class, 'update'])->name('plans.update');

    // Platform Analytics & Metrics
    Route::get('/analytics', [AdminAnalyticsController::class, 'index'])->name('analytics.index');

    // Operational Processing Queue & Exports Monitor
    Route::get('/queue', [JobMonitorController::class, 'index'])->name('jobs.index');
    Route::get('/queue/status', [JobMonitorController::class, 'status'])->name('jobs.status');
    Route::post('/queue/retry/{id}', [JobMonitorController::class, 'retry'])->name('jobs.retry');
    Route::post('/queue/retry-failed', [JobMonitorController::class, 'retryFailed'])->name('jobs.retry.failed');
    Route::post('/queue/retry-all-queued', [JobMonitorController::class, 'retryAllQueued'])->name('jobs.retry.queued');
    Route::post('/queue/retry-gallery/{galleryId}', [JobMonitorController::class, 'retryGallery'])->name('jobs.retry.gallery');
    Route::post('/queue/restart-workers', [JobMonitorController::class, 'restartWorkers'])->name('jobs.restart.workers');

    // Content Moderation Queue
    Route::get('/moderation', [AdminModerationController::class, 'index'])->name('moderation.index');
    Route::post('/moderation/{uuid}', [AdminModerationController::class, 'moderate'])->name('moderation.action');

    // Customer Service & Support Desk
    Route::get('/support', [AdminSupportController::class, 'index'])->name('support.index');

    // Platform Configuration & Encrypted SMTP
    Route::get('/settings', [AdminSettingsController::class, 'index'])->name('settings.index');
    Route::put('/settings/smtp', [AdminSettingsController::class, 'updateSmtp'])->name('settings.smtp');
    Route::post('/settings/smtp/test', [AdminSettingsController::class, 'testSmtp'])->name('settings.smtp.test');
    Route::put('/settings/general', [AdminSettingsController::class, 'updateGeneral'])->name('settings.general');
    Route::put('/settings/password', [AdminSettingsController::class, 'updatePassword'])->name('settings.password');
    Route::post('/settings/email/request-code', [AdminSettingsController::class, 'requestEmailVerificationCode'])->name('settings.email.request');
    Route::put('/settings/email/verify', [AdminSettingsController::class, 'verifyAndChangeEmail'])->name('settings.email.verify');
});
