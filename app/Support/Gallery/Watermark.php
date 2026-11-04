<?php

namespace App\Support\Gallery;

use App\Models\User;

/**
 * A studio's watermark, read from users.watermark with defaults filled in. Designed once in Studio
 * settings; each gallery only turns it on or off.
 *
 * Watermarked previews are separate files named after version(), so changing the design makes new
 * files (and new URLs) instead of overwriting ones the CDN has cached.
 */
class Watermark
{
    public const TYPES = ['text' => 'Text', 'logo' => 'Logo'];

    public const POSITIONS = [
        'center' => 'Middle',
        'corner' => 'Corner',
        'tiled' => 'All over',
    ];

    /** Plan feature code; watermarks come with the paid plans */
    public const FEATURE = 'watermark';

    public const MIN_OPACITY = 10;
    public const MAX_OPACITY = 90;
    public const DEFAULT_OPACITY = 40;
    public const MAX_TEXT = 40;

    /** Bump when the drawing changes, so every watermarked preview is made again */
    private const RENDER_VERSION = 1;

    public function __construct(protected array $data, protected User $owner) {}

    public static function for(User $owner, ?array $data = null): self
    {
        return new self($data ?? $owner->watermark ?? [], $owner);
    }

    /**
     * Whether the studio's plan makes new watermarks. Previews already made stay in place after a plan lapses,
     * so clients never suddenly see clean proofs; only new photos and new designs stop getting one.
     */
    public static function allowedFor(User $owner): bool
    {
        return $owner->hasFeature(self::FEATURE);
    }

    public function owner(): User
    {
        return $this->owner;
    }

    /**
     * The logo is used only while the portfolio has one; otherwise the text stands in.
     */
    public function type(): string
    {
        return ($this->data['type'] ?? null) === 'logo' && $this->owner->logo_path ? 'logo' : 'text';
    }

    public function storedType(): string
    {
        return ($this->data['type'] ?? null) === 'logo' ? 'logo' : 'text';
    }

    public function text(): string
    {
        $text = trim((string) ($this->data['text'] ?? ''));

        return mb_substr($text !== '' ? $text : $this->owner->name, 0, self::MAX_TEXT);
    }

    public function storedText(): ?string
    {
        $text = trim((string) ($this->data['text'] ?? ''));

        return $text !== '' ? $text : null;
    }

    public function position(): string
    {
        $position = $this->data['position'] ?? null;

        return isset(self::POSITIONS[$position]) ? $position : 'center';
    }

    /**
     * 10 to 90 percent.
     */
    public function opacity(): int
    {
        $opacity = (int) ($this->data['opacity'] ?? self::DEFAULT_OPACITY);

        return max(self::MIN_OPACITY, min(self::MAX_OPACITY, $opacity));
    }

    public function version(): string
    {
        return substr(md5(json_encode([
            self::RENDER_VERSION,
            $this->type(),
            $this->type() === 'logo' ? $this->owner->logo_path : $this->text(),
            $this->position(),
            $this->opacity(),
        ])), 0, 8);
    }

    /**
     * Queue new previews in every gallery that shows this studio's watermark, after its design changed.
     */
    public static function refreshGalleries(User $owner): void
    {
        // Closed galleries can't be opened, so they keep the design they had rather than costing a new one
        $owner->galleries()
            ->where('watermark', true)
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->each(fn ($gallery) => \App\Jobs\WatermarkGalleryJob::dispatch($gallery));
    }

    /**
     * @return array{done: int, total: int}
     */
    public function progress(\App\Models\Gallery $gallery): array
    {
        $total = $gallery->photos()
            ->where('status', \App\Enums\PhotoStatus::Ready->value)
            ->where(fn ($q) => $q->where('media_type', 'photo')->orWhereNull('media_type'))
            ->count();

        return ['done' => $total - \App\Jobs\WatermarkGalleryJob::pending($gallery, $this->version())->count(), 'total' => $total];
    }

    public function summary(): string
    {
        return ($this->type() === 'logo' ? 'Logo' : '“' . $this->text() . '”') . ', ' . strtolower(self::POSITIONS[$this->position()]);
    }
}
