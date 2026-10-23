<?php

namespace App\Console\Commands;

use App\Actions\Billing\FinalizePawaPayPayment;
use App\Contracts\PaymentGateway;
use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Payment;
use Illuminate\Console\Command;

class ExpireBookingHolds extends Command
{
    protected $signature = 'bookings:expire-holds';

    protected $description = 'Release booking slots whose Mobile Money deposit was not paid in time';

    public function handle(PaymentGateway $gateway, FinalizePawaPayPayment $finalizer): int
    {
        $released = 0;

        Booking::query()
            ->where('status', BookingStatus::Pending->value)
            ->whereIn('deposit_status', ['pending', 'failed'])
            ->where('hold_expires_at', '<=', now())
            ->chunkById(100, function ($bookings) use ($gateway, $finalizer, &$released) {
                foreach ($bookings as $booking) {
                    // Settle any payment still in flight first so a paid deposit is never released
                    $pending = Payment::where('booking_id', $booking->id)
                        ->where('purpose', 'booking_deposit')
                        ->where('status', 'pending')
                        ->get();

                    foreach ($pending as $payment) {
                        try {
                            $statusData = $gateway->verifyDepositStatus($payment->pawapay_deposit_id);
                            if ($statusData->found && ($statusData->isCompleted() || $statusData->isFailed())) {
                                $finalizer->execute($payment->pawapay_deposit_id, $statusData);
                            }
                        } catch (\Exception $e) {
                            report($e);
                        }
                    }

                    // Abandoned checkout: the client and photographer were never told about it, so release quietly
                    $released += Booking::whereKey($booking->id)
                        ->where('status', BookingStatus::Pending->value)
                        ->whereIn('deposit_status', ['pending', 'failed'])
                        ->update([
                            'status' => BookingStatus::Cancelled->value,
                            'deposit_status' => 'expired',
                        ]);
                }
            });

        $this->info("Released {$released} unpaid booking holds.");

        return Command::SUCCESS;
    }
}
