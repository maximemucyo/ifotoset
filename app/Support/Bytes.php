<?php

namespace App\Support;

/**
 * The one storage-size formatter. Plans are sold as "N GB" with 1 GB = 1024^3 bytes,
 * and "1.5 TB" means 1,500 of those GB, so TB is 1000 GB rather than 1024 GB.
 * Using this everywhere keeps a 1.5 TB plan reading as 1.5 TB on every page.
 */
final class Bytes
{
    public const KB = 1024;
    public const MB = 1024 ** 2;
    public const GB = 1024 ** 3;
    public const TB = 1000 * self::GB;

    public static function format(int|float|null $bytes): string
    {
        $bytes = max(0, (float) $bytes);

        foreach (['TB' => self::TB, 'GB' => self::GB, 'MB' => self::MB, 'KB' => self::KB] as $unit => $size) {
            if ($bytes >= $size) {
                $value = $bytes / $size;
                $decimals = $value < 10 ? 2 : ($value < 100 ? 1 : 0);
                $out = number_format($value, $decimals);

                return ($decimals > 0 ? rtrim(rtrim($out, '0'), '.') : $out) . ' ' . $unit;
            }
        }

        return (int) $bytes . ' B';
    }
}
