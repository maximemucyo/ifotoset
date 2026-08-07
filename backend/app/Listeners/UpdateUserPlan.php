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
        
        if ($payment->purpose === 'booking_deposit' && $payment->booking_id) {
            $booking = \App\Models\Booking::find($payment->booking_id);
            if ($booking && $booking->status === \App\Enums\BookingStatus::Pending) {
                $booking->update(['status' => \App\Enums\BookingStatus::Confirmed]);
                Log::info("Booking deposit paid successfully via PawaPay.", [
                    'booking_id' => $booking->id,
                    'payment_id' => $payment->id,
                ]);
            }
            return;
        }

        $user = User::find($payment->user_id);
        
        if ($user && $payment->plan_id) {
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
