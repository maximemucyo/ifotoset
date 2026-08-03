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
        //
    }
}
?>
