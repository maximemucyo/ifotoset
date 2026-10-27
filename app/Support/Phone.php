<?php

namespace App\Support;

use App\Models\User;
use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;

/**
 * Studio owner phone numbers, stored in E.164 (+250788123456) so the same number
 * typed as "0788 123 456" or "+250 788-123-456" is recognised as one.
 */
class Phone
{
    public const DEFAULT_COUNTRY = 'RW';

    // Without OTP this only stops casual repeat sign-ups, not determined abuse
    public const MAX_ACCOUNTS = 5;

    /**
     * E.164 form of a number typed in the given country, or null when it is not a valid number.
     */
    public static function normalize(?string $raw, ?string $country = null): ?string
    {
        if (blank($raw)) {
            return null;
        }

        $util = PhoneNumberUtil::getInstance();

        try {
            $number = $util->parse($raw, strtoupper($country ?: self::DEFAULT_COUNTRY));
        } catch (NumberParseException) {
            return null;
        }

        return $util->isValidNumber($number) ? $util->format($number, PhoneNumberFormat::E164) : null;
    }

    /**
     * Country and national number to pre-fill the phone field with.
     *
     * @return array{country: string, national: string}
     */
    public static function split(?string $phone): array
    {
        $util = PhoneNumberUtil::getInstance();

        try {
            $number = $util->parse((string) $phone, self::DEFAULT_COUNTRY);
            $country = $util->getRegionCodeForNumber($number);

            if ($country) {
                return ['country' => $country, 'national' => $util->format($number, PhoneNumberFormat::NATIONAL)];
            }
        } catch (NumberParseException) {
        }

        return ['country' => self::DEFAULT_COUNTRY, 'national' => (string) $phone];
    }

    /**
     * Studio accounts already registered with this number, excluding $ignore.
     */
    public static function accountsUsing(string $phone, ?User $ignore = null): int
    {
        return User::where('phone', $phone)
            ->whereNotNull('username')
            ->when($ignore, fn ($query) => $query->whereKeyNot($ignore->getKey()))
            ->count();
    }

    /**
     * Selectable countries, sorted by name.
     *
     * @return array<string, array{name: string, dial: string, flag: string}>
     */
    public static function countries(): array
    {
        static $countries;

        if ($countries === null) {
            $util = PhoneNumberUtil::getInstance();
            $countries = [];

            foreach ($util->getSupportedRegions() as $code) {
                $countries[$code] = [
                    'name' => class_exists(\Locale::class) ? \Locale::getDisplayRegion('-' . $code, 'en') : $code,
                    'dial' => '+' . $util->getCountryCodeForRegion($code),
                    'flag' => self::flag($code),
                ];
            }

            uasort($countries, fn ($a, $b) => strcmp($a['name'], $b['name']));
        }

        return $countries;
    }

    private static function flag(string $code): string
    {
        return implode('', array_map(fn ($letter) => mb_chr(0x1F1E6 + ord($letter) - ord('A')), str_split($code)));
    }
}
