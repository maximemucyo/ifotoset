<?php

namespace App\Console\Commands;

use App\Actions\Billing\DeleteOverQuotaGalleries;
use App\Enums\OverQuotaStage;
use App\Mail\OverQuotaNoticeMail;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class EnforceFreeStorageQuota extends Command
{
    protected $signature = 'storage:enforce-free-quota';

    protected $description = 'Move Free-plan accounts over their storage limit through the grace, restrict, archive and delete timeline';

    /**
     * Notices in send order, keyed by name => days from over_quota_since. Only
     * the latest one due is sent, so a missed run never sends a backlog.
     */
    public static function notices(): array
    {
        return [
            'started' => 0,
            'restrict_soon' => OverQuotaStage::restrictAfterDays() - 7,
            'archive_soon' => OverQuotaStage::archiveAfterDays() - 7,
            'delete_soon' => OverQuotaStage::deleteAfterDays() - 7,
            'delete_tomorrow' => OverQuotaStage::deleteAfterDays() - 1,
        ];
    }

    public function handle(DeleteOverQuotaGalleries $deleteGalleries): int
    {
        $cleared = $flagged = $notified = $purged = 0;

        // 1. Accounts that upgraded or freed up space leave the timeline
        User::whereNotNull('over_quota_since')->with('plan')->chunkById(100, function ($users) use (&$cleared) {
            foreach ($users as $user) {
                if (!$user->isOverFreeQuota()) {
                    $user->forceFill(['over_quota_since' => null, 'over_quota_notice' => null])->save();
                    $cleared++;
                }
            }
        });

        // 2. Free accounts newly found over the limit start it
        $freePlan = Plan::where('slug', 'free')->first();
        if ($freePlan && $freePlan->storage_limit > 0) {
            User::whereNull('over_quota_since')
                ->where('plan_id', $freePlan->id)
                ->where('role', '!=', 'admin')
                ->where('storage_used_bytes', '>', $freePlan->storage_limit)
                ->chunkById(100, function ($users) use (&$flagged) {
                    foreach ($users as $user) {
                        $user->forceFill(['over_quota_since' => now()])->save();
                        $flagged++;
                    }
                });
        }

        // 3. Send the notice that is due, and delete galleries for accounts past the deadline
        User::whereNotNull('over_quota_since')->with('plan')->chunkById(100, function ($users) use ($deleteGalleries, &$notified, &$purged) {
            foreach ($users as $user) {
                $stage = $user->overQuotaStage();
                if (!$stage) {
                    continue;
                }

                if ($stage === OverQuotaStage::Deleting) {
                    $this->deleteGalleries($user, $deleteGalleries);
                    $purged++;
                    continue;
                }

                if ($this->sendDueNotice($user)) {
                    $notified++;
                }
            }
        });

        $this->info("Over-quota enforcement: {$flagged} flagged, {$cleared} cleared, {$notified} notified, {$purged} purged.");

        return self::SUCCESS;
    }

    private function sendDueNotice(User $user): bool
    {
        $notices = self::notices();
        $days = (int) $user->over_quota_since->diffInDays(now());
        $due = collect($notices)->filter(fn ($day) => $days >= $day)->keys()->last();

        $order = array_keys($notices);
        $lastSent = $user->over_quota_notice ? array_search($user->over_quota_notice, $order, true) : -1;
        if ($due === null || array_search($due, $order, true) <= $lastSent) {
            return false;
        }

        // Claim first so overlapping runs never double-send
        $claimed = User::whereKey($user->id)
            ->where(fn ($q) => $user->over_quota_notice
                ? $q->where('over_quota_notice', $user->over_quota_notice)
                : $q->whereNull('over_quota_notice'))
            ->update(['over_quota_notice' => $due]);

        if (!$claimed || !$user->email || !$user->is_active) {
            return false;
        }

        Mail::to($user->email)->queue(new OverQuotaNoticeMail($user, $due));

        return true;
    }

    private function deleteGalleries(User $user, DeleteOverQuotaGalleries $deleteGalleries): void
    {
        $deleted = $deleteGalleries->execute($user);
        $user->refresh();

        if ($user->isOverFreeQuota()) {
            Log::warning("[Over Quota] User {$user->id} is still over the Free limit after deleting galleries.");
            return;
        }

        $user->forceFill(['over_quota_since' => null, 'over_quota_notice' => null])->save();

        if ($deleted && $user->email && $user->is_active) {
            Mail::to($user->email)->queue(new OverQuotaNoticeMail($user, 'deleted', $deleted));
        }
    }
}
