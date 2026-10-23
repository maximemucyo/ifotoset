<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Bookings made before this release stored the client's local wall-clock time
 * ("14:00" in Kigali) as if it were UTC. Times are now stored in UTC and shown in the
 * booking's timezone, so shift the old rows to the instant the client actually meant.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('bookings')
            ->whereNotNull('timezone')
            ->where('timezone', '!=', 'UTC')
            ->orderBy('id')
            ->chunkById(200, function ($rows) {
                foreach ($rows as $row) {
                    try {
                        $toUtc = fn (?string $value) => $value ? Carbon::parse($value, $row->timezone)->utc()->format('Y-m-d H:i:s') : null;

                        DB::table('bookings')->where('id', $row->id)->update([
                            'starts_at' => $toUtc($row->starts_at),
                            'ends_at' => $toUtc($row->ends_at),
                        ]);
                    } catch (\Throwable $e) {
                        // Unknown timezone name: leave the row as it was
                        report($e);
                    }
                }
            });
    }

    public function down(): void
    {
        DB::table('bookings')
            ->whereNotNull('timezone')
            ->where('timezone', '!=', 'UTC')
            ->orderBy('id')
            ->chunkById(200, function ($rows) {
                foreach ($rows as $row) {
                    try {
                        $toWall = fn (?string $value) => $value ? Carbon::parse($value, 'UTC')->setTimezone($row->timezone)->format('Y-m-d H:i:s') : null;

                        DB::table('bookings')->where('id', $row->id)->update([
                            'starts_at' => $toWall($row->starts_at),
                            'ends_at' => $toWall($row->ends_at),
                        ]);
                    } catch (\Throwable $e) {
                        report($e);
                    }
                }
            });
    }
};
