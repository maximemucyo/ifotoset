<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Matches free-text profile locations to the directory cities in config/directory.php.
 */
class Directory
{
    /**
     * City slug for a typed location, or null when no known city or alias appears in it.
     * When several match ("Musanze, near Kigali"), the one written first wins.
     */
    public static function matchCity(?string $location): ?string
    {
        $text = self::normalize((string) $location);
        if (trim($text) === '') {
            return null;
        }

        $best = null;
        $bestPosition = PHP_INT_MAX;

        foreach (config('directory.cities') as $slug => $city) {
            foreach ([$city['name'], ...$city['aliases']] as $term) {
                $position = strpos($text, self::normalize($term));
                if ($position !== false && $position < $bestPosition) {
                    $best = $slug;
                    $bestPosition = $position;
                }
            }
        }

        return $best;
    }

    public static function cityName(?string $slug): ?string
    {
        return $slug ? (config("directory.cities.{$slug}.name")) : null;
    }

    /**
     * "Kigali, Rwanda" style suggestions for the location field.
     *
     * @return array<int, string>
     */
    public static function locationSuggestions(): array
    {
        return collect(config('directory.cities'))
            ->map(fn (array $city) => $city['name'] . ', ' . $city['country'])
            ->values()
            ->all();
    }

    /**
     * Lowercase ASCII words separated by single spaces, padded so terms only match whole words.
     */
    private static function normalize(string $text): string
    {
        $words = preg_replace('/[^a-z0-9]+/', ' ', strtolower(Str::ascii($text)));

        return ' ' . trim($words) . ' ';
    }
}
