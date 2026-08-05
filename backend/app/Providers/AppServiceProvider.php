<?php

namespace App\Providers;

use App\Contracts\PaymentGateway;
use App\Services\PawaPayGateway;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Bind the payment gateway abstraction to PawaPay implementation
        $this->app->bind(PaymentGateway::class, PawaPayGateway::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        \App\Models\Photo::observe(\App\Observers\PhotoObserver::class);
        \App\Models\Gallery::observe(\App\Observers\GalleryObserver::class);

        // Bind queue lifecycle events to QueueJobTracker
        \Illuminate\Support\Facades\Queue::before(function (\Illuminate\Queue\Events\JobProcessing $event) {
            \App\Listeners\QueueJobTracker::handleProcessing($event);
        });
        \Illuminate\Support\Facades\Queue::after(function (\Illuminate\Queue\Events\JobProcessed $event) {
            \App\Listeners\QueueJobTracker::handleProcessed($event);
        });
        \Illuminate\Support\Facades\Queue::failing(function (\Illuminate\Queue\Events\JobFailed $event) {
            \App\Listeners\QueueJobTracker::handleFailed($event);
        });

        // Define access Gate for administrative panels
        \Illuminate\Support\Facades\Gate::define('access-admin', function (\App\Models\User $user) {
            return $user->role === 'admin';
        });

        // Automatically clear admin dashboard cache on model changes
        $clearCache = fn () => \Illuminate\Support\Facades\Cache::forget('admin_dashboard_stats');
        
        \App\Models\User::saved($clearCache);
        \App\Models\User::deleted($clearCache);
        \App\Models\Gallery::saved($clearCache);
        \App\Models\Gallery::deleted($clearCache);
        \App\Models\Payment::saved($clearCache);
        \App\Models\Payment::deleted($clearCache);
        \App\Models\MediaJob::saved($clearCache);
        \App\Models\MediaJob::deleted($clearCache);
    }
}
?>
