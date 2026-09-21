<?php

namespace App\Services;

use App\Jobs\StorageQuotaAlertJob;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class StorageQuotaNotifierService
{
    /**
     * Evaluate a user's current storage against notification threshold boundaries.
     * Uses pessimistic row locking to prevent race conditions during concurrent recalculations.
     */
    public function evaluateUsage(int $userId): void
    {
        $user = User::with('plan')->find($userId);
        if (!$user || !$user->plan) {
            return;
        }

        $limitBytes = $user->plan->storage_limit;
        if ($limitBytes === null || $limitBytes <= 0) {
            // Unlimited plan: no quota warnings needed
            return;
        }

        $usedBytes = (int) $user->storage_used_bytes;
        $pct = ($limitBytes > 0) ? ($usedBytes / $limitBytes) * 100 : 0;

        // Run atomic state transition inside transaction with row lock
        DB::transaction(function () use ($userId, $usedBytes, $limitBytes, $pct) {
            $lockedUser = User::where('id', $userId)->lockForUpdate()->first();
            if (!$lockedUser) {
                return;
            }

            // Boundary 1: >= 100%
            if ($pct >= 100) {
                if (!$lockedUser->storage_warning_100_active) {
                    $lockedUser->storage_warning_100_active = true;
                    $lockedUser->storage_warning_75_active = true;
                    $lockedUser->save();

                    StorageQuotaAlertJob::dispatch(
                        userId: $lockedUser->id,
                        threshold: 100,
                        generation: $lockedUser->storage_warning_generation,
                        usedBytes: $usedBytes,
                        limitBytes: $limitBytes
                    );
                }
            }
            // Boundary 2: 75% - 99%
            elseif ($pct >= 75) {
                // If usage fell below 100% into 75-99%, reset the 100% threshold for future crossings
                $needsSave = false;
                if ($lockedUser->storage_warning_100_active) {
                    $lockedUser->storage_warning_100_active = false;
                    $needsSave = true;
                }

                if (!$lockedUser->storage_warning_75_active) {
                    $lockedUser->storage_warning_75_active = true;
                    $needsSave = true;

                    StorageQuotaAlertJob::dispatch(
                        userId: $lockedUser->id,
                        threshold: 75,
                        generation: $lockedUser->storage_warning_generation,
                        usedBytes: $usedBytes,
                        limitBytes: $limitBytes
                    );
                }

                if ($needsSave) {
                    $lockedUser->save();
                }
            }
            // Boundary 3: < 75%
            else {
                if ($lockedUser->storage_warning_75_active || $lockedUser->storage_warning_100_active) {
                    $lockedUser->storage_warning_75_active = false;
                    $lockedUser->storage_warning_100_active = false;
                    $lockedUser->storage_warning_generation += 1;
                    $lockedUser->save();
                }
            }
        });
    }
}
