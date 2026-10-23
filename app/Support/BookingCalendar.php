<?php

namespace App\Support;

use App\Models\Booking;
use Illuminate\Mail\Attachment;

/**
 * iCalendar (.ics) export of a booking so clients can add the session to their calendar.
 */
class BookingCalendar
{
    public static function ics(Booking $booking): string
    {
        $booking->loadMissing(['user', 'package']);
        $photographer = $booking->user;
        $starts = $booking->starts_at->copy()->utc();
        $ends = ($booking->ends_at ?? $booking->starts_at->copy()->addHour())->copy()->utc();
        $summary = ($booking->package?->name ?? 'Photo session') . ($photographer ? " with {$photographer->name}" : '');

        $description = array_filter([
            $booking->package?->name ? "Package: {$booking->package->name}" : null,
            $photographer?->email ? "Contact: {$photographer->email}" : null,
            $booking->notes ? "Notes: {$booking->notes}" : null,
        ]);

        $host = parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'ifotoset.com';

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//ifotoset//Bookings//EN',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'BEGIN:VEVENT',
            'UID:booking-' . $booking->uuid . '@' . $host,
            'DTSTAMP:' . now()->utc()->format('Ymd\THis\Z'),
            'DTSTART:' . $starts->format('Ymd\THis\Z'),
            'DTEND:' . $ends->format('Ymd\THis\Z'),
            'SUMMARY:' . self::escape($summary),
            'STATUS:CONFIRMED',
        ];
        if ($booking->location) {
            $lines[] = 'LOCATION:' . self::escape($booking->location);
        }
        if ($description) {
            $lines[] = 'DESCRIPTION:' . self::escape(implode("\n", $description));
        }
        if ($photographer?->email) {
            $lines[] = 'ORGANIZER;CN=' . self::escape($photographer->name) . ':mailto:' . $photographer->email;
        }
        $lines[] = 'END:VEVENT';
        $lines[] = 'END:VCALENDAR';

        return implode("\r\n", array_map([self::class, 'fold'], $lines)) . "\r\n";
    }

    public static function attachment(Booking $booking): Attachment
    {
        return Attachment::fromData(fn () => self::ics($booking), 'session.ics')
            ->withMime('text/calendar; charset=utf-8; method=PUBLISH');
    }

    protected static function escape(string $value): string
    {
        return str_replace(["\\", ';', ',', "\r\n", "\n"], ["\\\\", '\;', '\,', '\n', '\n'], $value);
    }

    /**
     * Lines longer than 75 octets continue on the next line after a space (RFC 5545 §3.1).
     */
    protected static function fold(string $line): string
    {
        if (strlen($line) <= 75) {
            return $line;
        }

        $out = [];
        while (strlen($line) > 75) {
            $cut = 75;
            // Don't split a multi-byte UTF-8 character
            while ($cut > 0 && (ord($line[$cut]) & 0xC0) === 0x80) {
                $cut--;
            }
            $out[] = substr($line, 0, $cut);
            $line = ' ' . substr($line, $cut);
        }
        $out[] = $line;

        return implode("\r\n", $out);
    }
}
