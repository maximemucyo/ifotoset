<?php

namespace App\Console\Commands;

use App\Actions\Billing\ExpireSubscriptions as ExpireSubscriptionsAction;
use Illuminate\Console\Command;

class ExpireSubscriptions extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'subscriptions:expire';

    /**
     * The console command description.
     */
    protected $description = 'Expire overdue active subscriptions and revert users to Free entitlement';

    /**
     * Execute the console command.
     */
    public function handle(ExpireSubscriptionsAction $action): int
    {
        $this->info("Checking for overdue subscriptions...");

        $expiredCount = $action->execute();

        $this->info("Subscription expiration cycle complete: {$expiredCount} subscriptions expired.");
        return Command::SUCCESS;
    }
}
