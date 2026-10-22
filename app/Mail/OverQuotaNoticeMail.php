<?php

namespace App\Mail;

use App\Enums\OverQuotaStage;
use App\Models\User;
use Illuminate\Support\Number;

/**
 * Over-quota timeline notices for Free-plan accounts above their storage limit.
 * `$kind` is one of the keys in EnforceFreeStorageQuota::notices(), or 'deleted'.
 */
class OverQuotaNoticeMail extends NoticeMail
{
    /**
     * @param  list<string>  $deletedGalleries  Titles of the galleries deleted, for the 'deleted' notice
     */
    public function __construct(public User $user, public string $kind, public array $deletedGalleries = []) {}

    protected function subjectLine(): string
    {
        return match ($this->kind) {
            'restrict_soon' => 'New galleries and client downloads pause in 7 days',
            'archive_soon' => 'Your galleries go offline in 7 days',
            'delete_soon' => 'Your oldest galleries will be deleted in 7 days',
            'delete_tomorrow' => 'Your oldest galleries will be deleted tomorrow',
            'deleted' => 'We deleted galleries to bring you within the Free plan',
            default => 'Your account is over the Free plan storage limit',
        };
    }

    protected function notice(): array
    {
        $user = $this->user->loadMissing('plan');
        $limit = Number::fileSize((int) $user->plan?->storage_limit, 1);
        $used = Number::fileSize((int) $user->storage_used_bytes, 1);
        $dates = OverQuotaStage::dates($user->over_quota_since ?? now());
        $format = fn ($date) => $date->format('F j, Y');

        if ($this->kind === 'deleted') {
            return [
                'eyebrow' => 'Storage',
                'title' => 'Galleries deleted',
                'preheader' => 'Your oldest galleries were permanently deleted to bring your account within the Free plan.',
                'greeting' => $user->name,
                'paragraphs' => [
                    "Your account stayed above the Free plan's {$limit} for " . OverQuotaStage::deleteAfterDays() . ' days, so we permanently deleted your oldest galleries until it was back within the limit.',
                    'Your remaining galleries are online again and your clients can view and download them.',
                ],
                'detailsTitle' => 'Deleted galleries',
                'details' => collect($this->deletedGalleries)->mapWithKeys(fn ($title, $i) => [(string) ($i + 1) => $title])->all(),
                'button' => ['label' => 'Go to your galleries', 'url' => route('studio.galleries.index')],
            ];
        }

        $title = match ($this->kind) {
            'restrict_soon' => 'New galleries and client downloads pause in 7 days',
            'archive_soon' => 'Your galleries go offline in 7 days',
            'delete_soon' => 'Galleries will be deleted in 7 days',
            'delete_tomorrow' => 'Galleries will be deleted tomorrow',
            default => 'You are over the Free plan storage limit',
        };

        return [
            'eyebrow' => 'Storage',
            'title' => $title,
            'preheader' => "You are using {$used} of the Free plan's {$limit}. Upgrade or free up space to keep your galleries.",
            'greeting' => $user->name,
            'paragraphs' => [
                "Your account is on the Free plan and is using {$used}, more than its {$limit}. New uploads are paused.",
                'Upgrade to a paid plan, or delete photos or galleries until you are within the limit, and everything goes back to normal right away. Otherwise:',
            ],
            'detailsTitle' => 'What happens next',
            'details' => [
                $format($dates['restrict']) => 'New galleries and client downloads pause',
                $format($dates['archive']) => 'Your galleries go offline for clients',
                $format($dates['delete']) => 'Your oldest galleries are permanently deleted until you are within the limit',
            ],
            'button' => ['label' => 'Choose a plan', 'url' => route('studio.billing.index')],
            'footnotes' => ['Until the deletion date you can still sign in, view your galleries and download your originals from the studio.'],
        ];
    }
}
