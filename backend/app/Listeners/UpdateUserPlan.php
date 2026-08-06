<?php

namespace App\Listeners;

use App\Events\PaymentCompleted;
use App\Models\User;
use App\Services\StorageStatisticsService;
use Illuminate\Support\Facades\Log;

class UpdateUserPlan
{
    /**
     * Handle the event.
     */
    public function handle(PaymentCompleted $event): void
    {
        $payment = $event->payment;
        $user = User::find($payment->user_id);
        
        if ($user) {
            $user->update([
                'plan_id' => $payment->plan_id,
            ]);

            // Clear statistics cache for the user
            StorageStatisticsService::clearCache($user->id);

            Log::info("User plan upgraded successfully via PawaPay payment.", [
                'user_id' => $user->id,
                'plan_id' => $payment->plan_id,
                'payment_id' => $payment->id,
            ]);
        }
    }
}
