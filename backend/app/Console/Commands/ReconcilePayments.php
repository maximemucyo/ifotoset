<?php

namespace App\Console\Commands;

use App\Actions\Billing\FinalizePawaPayPayment;
use App\Contracts\PaymentGateway;
use App\Models\Payment;
use Exception;
use Illuminate\Console\Command;

class ReconcilePayments extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'payments:reconcile {--hours=48 : Lookback window in hours}';

    /**
     * The console command description.
     */
    protected $description = 'Reconcile and synchronize pending PawaPay payments with the provider API';

    /**
     * Execute the console command.
     */
    public function handle(PaymentGateway $gateway, FinalizePawaPayPayment $finalizer): int
    {
        $hours = (int) $this->option('hours');
        $this->info("Scanning pending PawaPay transactions from the last {$hours} hours...");

        $pendingPayments = Payment::where('status', 'pending')
            ->whereNotNull('pawapay_deposit_id')
            ->where('created_at', '>=', now()->subHours($hours))
            ->get();

        if ($pendingPayments->isEmpty()) {
            $this->info("No pending payments found in the reconciliation window.");
            return Command::SUCCESS;
        }

        $this->info("Found {$pendingPayments->count()} pending payments. Reconciling...");
        $completed = 0;
        $failed = 0;
        $unchanged = 0;

        foreach ($pendingPayments as $payment) {
            try {
                $statusData = $gateway->verifyDepositStatus($payment->pawapay_deposit_id);

                if (!$statusData->found) {
                    $this->warn("Deposit {$payment->pawapay_deposit_id} not found on PawaPay API.");
                    $unchanged++;
                    continue;
                }

                if ($statusData->isCompleted() || $statusData->isFailed()) {
                    $updated = $finalizer->execute($payment->pawapay_deposit_id, $statusData);
                    if ($updated->status === 'completed') {
                        $this->line("Payment #{$payment->id} ({$payment->pawapay_deposit_id}) finalized as COMPLETED.");
                        $completed++;
                    } else {
                        $this->warn("Payment #{$payment->id} ({$payment->pawapay_deposit_id}) finalized as {$updated->status}.");
                        $failed++;
                    }
                } else {
                    $unchanged++;
                }
            } catch (Exception $e) {
                $this->error("Error reconciling payment #{$payment->id}: " . $e->getMessage());
            }
        }

        $this->info("Reconciliation complete: {$completed} completed, {$failed} failed/verification_failed, {$unchanged} still pending.");
        return Command::SUCCESS;
    }
}
