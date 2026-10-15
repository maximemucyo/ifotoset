<?php

namespace App\Console\Commands;

use App\Mail\AdminAlertMail;
use App\Support\AdminAlerts;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

/**
 * Emails admins when background work is going wrong. Each alert is sent at
 * most once an hour.
 */
class CheckPlatformHealth extends Command
{
    protected $signature = 'ops:check-health
        {--failed-jobs=10 : Alert when more jobs than this failed in the last hour}
        {--queue-depth=500 : Alert when a queue holds more pending jobs than this}
        {--stuck-payment-minutes=120 : Alert on payments still pending after this long}';

    protected $description = 'Alert admins about failed-job spikes, queue backlogs and stuck payments';

    public function handle(): int
    {
        $problems = 0;

        $failedLastHour = DB::table('failed_jobs')->where('failed_at', '>=', now()->subHour())->count();
        if ($failedLastHour > (int) $this->option('failed-jobs')) {
            $problems++;
            $latest = DB::table('failed_jobs')->latest('failed_at')->first();
            AdminAlerts::send(new AdminAlertMail(
                headline: "{$failedLastHour} jobs failed in the last hour",
                summary: 'Background jobs are failing faster than usual. Check the job monitor and the logs.',
                details: array_filter([
                    'Failed in last hour' => (string) $failedLastHour,
                    'Total failed' => (string) DB::table('failed_jobs')->count(),
                    'Latest' => $latest ? (json_decode($latest->payload, true)['displayName'] ?? 'unknown') : null,
                    'Latest error' => $latest ? \Illuminate\Support\Str::limit(strtok((string) $latest->exception, "\n"), 200) : null,
                ]),
                actionUrl: route('admin.jobs.index'),
                actionLabel: 'Open job monitor',
            ), 'failed-jobs');
        }

        foreach (['photos', 'default', 'media'] as $queue) {
            try {
                $size = Queue::size($queue);
            } catch (\Throwable $e) {
                continue;
            }

            if ($size > (int) $this->option('queue-depth')) {
                $problems++;
                AdminAlerts::send(new AdminAlertMail(
                    headline: "Queue '{$queue}' has {$size} pending jobs",
                    summary: "The {$queue} queue is backing up. Check that its pm2 workers are running.",
                    details: ['Queue' => $queue, 'Pending jobs' => (string) $size],
                    actionUrl: route('admin.jobs.index'),
                    actionLabel: 'Open job monitor',
                ), "queue-depth:{$queue}");
            }
        }

        $stuck = DB::table('payments')
            ->where('status', 'pending')
            ->where('created_at', '<=', now()->subMinutes((int) $this->option('stuck-payment-minutes')))
            ->where('created_at', '>=', now()->subDays(2))
            ->count();
        if ($stuck > 0) {
            $problems++;
            AdminAlerts::send(new AdminAlertMail(
                headline: "{$stuck} payments stuck in pending",
                summary: 'These payments have not been confirmed or failed by PawaPay. payments:reconcile retries them every 10 minutes; if this persists, check the callback relay and the PawaPay dashboard.',
                details: ['Stuck payments' => (string) $stuck],
                actionUrl: route('admin.payments.index'),
                actionLabel: 'View payments',
            ), 'stuck-payments', 360);
        }

        $this->info($problems ? "{$problems} problem(s) reported." : 'All checks passed.');

        return self::SUCCESS;
    }
}
