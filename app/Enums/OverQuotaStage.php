<?php

namespace App\Enums;

use Carbon\CarbonInterface;

/**
 * Where a Free-plan account above its storage limit sits on the over-quota
 * timeline (config/billing.php). Each stage keeps the restrictions of the ones before it.
 */
enum OverQuotaStage: string
{
    case Grace = 'grace';           // uploads blocked
    case Restricted = 'restricted'; // + no new galleries, client downloads paused
    case Archived = 'archived';     // + galleries offline to clients
    case Deleting = 'deleting';     // oldest galleries are deleted on the next enforcement run

    public static function since(CarbonInterface $overQuotaSince): self
    {
        $days = (int) $overQuotaSince->diffInDays(now());

        return match (true) {
            $days >= self::deleteAfterDays() => self::Deleting,
            $days >= self::archiveAfterDays() => self::Archived,
            $days >= self::restrictAfterDays() => self::Restricted,
            default => self::Grace,
        };
    }

    /**
     * The day each restriction starts for an account over quota since $overQuotaSince.
     *
     * @return array{restrict: CarbonInterface, archive: CarbonInterface, delete: CarbonInterface}
     */
    public static function dates(CarbonInterface $overQuotaSince): array
    {
        return [
            'restrict' => $overQuotaSince->copy()->addDays(self::restrictAfterDays()),
            'archive' => $overQuotaSince->copy()->addDays(self::archiveAfterDays()),
            'delete' => $overQuotaSince->copy()->addDays(self::deleteAfterDays()),
        ];
    }

    public static function restrictAfterDays(): int
    {
        return (int) config('billing.over_quota.restrict_after_days', 30);
    }

    public static function archiveAfterDays(): int
    {
        return (int) config('billing.over_quota.archive_after_days', 60);
    }

    public static function deleteAfterDays(): int
    {
        return (int) config('billing.over_quota.delete_after_days', 90);
    }

    public function blocksNewGalleries(): bool
    {
        return $this !== self::Grace;
    }

    public function pausesClientDownloads(): bool
    {
        return $this !== self::Grace;
    }

    public function hidesGalleries(): bool
    {
        return $this === self::Archived || $this === self::Deleting;
    }
}
