<?php

namespace App\Support;

use App\Models\SystemSetting;
use App\Models\User;

/**
 * Platform switches for client exports, set in Admin → Settings → Client exports.
 *
 * Each gallery still has its own setting; these decide whether the feature exists
 * at all. Google Photos can be limited to a few studios so the Google OAuth demo
 * can be recorded in production before the app is verified.
 */
class ClientExports
{
    public const GOOGLE_OFF = 'off';
    public const GOOGLE_STUDIOS = 'studios';
    public const GOOGLE_EVERYONE = 'everyone';

    public const GOOGLE_MODES = [self::GOOGLE_OFF, self::GOOGLE_STUDIOS, self::GOOGLE_EVERYONE];

    public static function zipEnabled(): bool
    {
        return SystemSetting::getOption('zip_downloads_enabled', '1') === '1';
    }

    /**
     * Until an admin saves the setting, GOOGLE_PHOTOS_EXPORT decides.
     */
    public static function googlePhotosMode(): string
    {
        $mode = SystemSetting::getOption('google_photos_mode');

        if (in_array($mode, self::GOOGLE_MODES, true)) {
            return $mode;
        }

        return config('services.google.photos_export') ? self::GOOGLE_EVERYONE : self::GOOGLE_OFF;
    }

    /**
     * Usernames allowed to offer Google Photos while the mode is "studios".
     *
     * @return list<string>
     */
    public static function googlePhotosStudios(): array
    {
        return self::parseUsernames((string) SystemSetting::getOption('google_photos_studios', ''));
    }

    public static function googlePhotosEnabledFor(?User $owner): bool
    {
        return match (self::googlePhotosMode()) {
            self::GOOGLE_EVERYONE => true,
            self::GOOGLE_STUDIOS => $owner !== null && in_array(strtolower((string) $owner->username), self::googlePhotosStudios(), true),
            default => false,
        };
    }

    /**
     * Whether marketing pages may advertise the feature.
     */
    public static function googlePhotosPublic(): bool
    {
        return self::googlePhotosMode() === self::GOOGLE_EVERYONE;
    }

    /**
     * @return list<string>
     */
    public static function parseUsernames(string $value): array
    {
        $names = preg_split('/[\s,]+/', strtolower($value), -1, PREG_SPLIT_NO_EMPTY);

        return array_values(array_unique(array_map(fn ($name) => ltrim($name, '@'), $names)));
    }
}
