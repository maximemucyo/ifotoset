<?php

namespace App\Mail;

use App\Models\User;

class GalleryActivityDigestMail extends NoticeMail
{
    /**
     * @param list<array{title: string, downloads: int, favorites: int, views: int}> $galleries
     */
    public function __construct(public User $user, public array $galleries) {}

    protected function fromNotifications(): bool
    {
        return true;
    }

    protected function subjectLine(): string
    {
        $downloads = array_sum(array_column($this->galleries, 'downloads'));
        $favorites = array_sum(array_column($this->galleries, 'favorites'));

        return "Yesterday on ifotoset: {$downloads} downloads, {$favorites} favorites";
    }

    protected function notice(): array
    {
        $details = [];
        foreach (array_slice($this->galleries, 0, 10) as $gallery) {
            $details[$gallery['title']] = "{$gallery['downloads']} downloads · {$gallery['favorites']} favorites · {$gallery['views']} views";
        }

        return [
            'eyebrow' => 'Daily activity',
            'title' => 'Your galleries in the last 24 hours',
            'preheader' => $this->subjectLine(),
            'greeting' => $this->user->name,
            'paragraphs' => ['Here is what your clients did in your galleries yesterday.'],
            'detailsTitle' => 'By gallery',
            'details' => $details,
            'button' => ['label' => 'Open analytics', 'url' => route('studio.analytics.index')],
            'footnotes' => ['You can turn this email off in Studio → Settings → Notifications.'],
        ];
    }
}
