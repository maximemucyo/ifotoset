<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Converts any plans stored using decimal commercial gigabytes (10^9)
     * to binary gigabytes (1024^3), ensuring a 10 GB plan has 10 GiB of upload quota
     * and displays cleanly as 10 GB rather than 9 GB.
     */
    public function up(): void
    {
        $plans = DB::table('plans')->get();

        foreach ($plans as $plan) {
            $limit = $plan->storage_limit;

            if (! $limit) {
                continue;
            }

            // If stored as decimal gigabytes (e.g., 10,000,000,000 or 50,000,000,000)
            // and not already binary (divisible by 1024*1024)
            if ($limit % 1000000000 === 0 && ($limit % (1024 * 1024) !== 0)) {
                $gb = (int) round($limit / 1000000000);
                $binaryBytes = (int) round($gb * 1024 * 1024 * 1024);

                DB::table('plans')
                    ->where('id', $plan->id)
                    ->update(['storage_limit' => $binaryBytes]);
            }
        }

        // Invalidate cached user storage statistics so the new limits take effect immediately
        try {
            Cache::flush();
        } catch (\Throwable $e) {
            // Ignore cache flush failures during migration
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Reversible if needed
        $plans = DB::table('plans')->get();

        foreach ($plans as $plan) {
            $limit = $plan->storage_limit;

            if (! $limit || $plan->slug === 'free') {
                continue;
            }

            if ($limit % (1024 * 1024 * 1024) === 0) {
                $gb = (int) round($limit / (1024 * 1024 * 1024));
                $decimalBytes = (int) ($gb * 1000000000);

                DB::table('plans')
                    ->where('id', $plan->id)
                    ->update(['storage_limit' => $decimalBytes]);
            }
        }
    }
};
