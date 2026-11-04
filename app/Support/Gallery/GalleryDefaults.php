<?php

namespace App\Support\Gallery;

use App\Models\Gallery;
use App\Models\User;

/**
 * What a studio's new galleries start with, read from users.gallery_defaults with today's defaults filled in.
 * Chosen once in Studio settings; each gallery can still be changed on its own.
 */
class GalleryDefaults
{
    /** How long a shared gallery stays open, in months; null is never */
    public const EXPIRY_MONTHS = [
        '' => 'Never',
        '1' => '1 month',
        '3' => '3 months',
        '6' => '6 months',
        '12' => '1 year',
    ];

    public function __construct(protected array $data) {}

    public static function for(?User $user): self
    {
        return new self($user?->gallery_defaults ?? []);
    }

    public function visibility(): string
    {
        return $this->pick('visibility', array_keys(Gallery::VISIBILITIES), 'public');
    }

    public function downloadPin(): bool
    {
        return (bool) ($this->data['download_pin'] ?? false);
    }

    public function expiryMonths(): ?int
    {
        $months = (string) ($this->data['expiry_months'] ?? '');

        return array_key_exists($months, self::EXPIRY_MONTHS) && $months !== '' ? (int) $months : null;
    }

    public function photoOrder(): string
    {
        return $this->pick('photo_order', array_keys(Gallery::PHOTO_ORDERS), 'taken');
    }

    public function template(): string
    {
        return $this->pick('template', array_keys(Gallery::TEMPLATES), 'full');
    }

    public function colorMode(): string
    {
        return $this->pick('color_mode', array_keys(Gallery::COLOR_MODES), 'light');
    }

    public function watermark(): bool
    {
        return (bool) ($this->data['watermark'] ?? false);
    }

    public function useBranding(): bool
    {
        return (bool) ($this->data['use_branding'] ?? false);
    }

    /**
     * Columns a new gallery starts with. The grid is the layout's usual one.
     */
    public function attributes(): array
    {
        return [
            'visibility' => $this->visibility(),
            ...Gallery::downloadAttributes($this->downloadPin() ? str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT) : null),
            'photo_order' => $this->photoOrder(),
            'template' => $this->template(),
            'grid_style' => Gallery::TEMPLATES[$this->template()]['grid'],
            'color_mode' => $this->colorMode(),
            'use_branding' => $this->useBranding(),
            'watermark' => $this->watermark(),
        ];
    }

    /**
     * The "Expires on" date a draft's Share panel starts with, counted from today in the studio's timezone.
     */
    public function expiryDateFromToday(?User $user): ?string
    {
        $months = $this->expiryMonths();

        return $months ? now(Gallery::expiryTimezone($user))->addMonthsNoOverflow($months)->format('Y-m-d') : null;
    }

    private function pick(string $key, array $allowed, string $default): string
    {
        $value = $this->data[$key] ?? null;

        return in_array($value, $allowed, true) ? $value : $default;
    }
}
