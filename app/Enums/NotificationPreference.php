<?php

namespace App\Enums;

use App\Models\User;

/**
 * Photographer email notification switches. The single source of truth for
 * the keys stored in users.notification_preferences.
 */
enum NotificationPreference: string
{
    case NewBookings = 'new_bookings';
    case PaymentReceived = 'payment_received';
    case GalleryActivity = 'gallery_activity';

    public function default(): bool
    {
        return true;
    }

    public function title(): string
    {
        return match ($this) {
            self::NewBookings => 'New booking requests',
            self::PaymentReceived => 'Payments received',
            self::GalleryActivity => 'Daily gallery activity',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::NewBookings => 'When a client books a session through your portfolio.',
            self::PaymentReceived => 'When a client pays a booking deposit.',
            self::GalleryActivity => 'One daily summary of client downloads and favorites.',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::NewBookings => 'calendar-check',
            self::PaymentReceived => 'wallet',
            self::GalleryActivity => 'heart',
        };
    }

    /**
     * Whether the user wants this notification, falling back to the default.
     */
    public function enabledFor(User $user): bool
    {
        $prefs = $user->notification_preferences ?? [];

        return (bool) ($prefs[$this->value] ?? $this->default());
    }

    /**
     * @return array<string, bool> every key with the user's value or its default
     */
    public static function resolvedFor(User $user): array
    {
        $resolved = [];
        foreach (self::cases() as $case) {
            $resolved[$case->value] = $case->enabledFor($user);
        }

        return $resolved;
    }
}
