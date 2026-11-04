<?php

namespace App\Support\Portfolio;

use GdImage;

/**
 * Whether a logo is cut out (transparent around it) and, if so, whether its marks are dark or light.
 *
 * Client galleries use this to keep a logo readable: a cut-out dark logo turns white over a cover
 * photo or in dark mode, and a cut-out light one turns black on a light page. A logo with its own
 * background (null) is always shown as it is.
 */
class LogoTone
{
    public const DARK = 'dark';
    public const LIGHT = 'light';

    /** Samples per side; enough to judge a wordmark without reading every pixel */
    private const GRID = 40;

    public static function detect(GdImage $image): ?string
    {
        $width = imagesx($image);
        $height = imagesy($image);
        if ($width < 2 || $height < 2) {
            return null;
        }

        // A logo with a filled background has solid corners
        foreach ([[0, 0], [$width - 1, 0], [0, $height - 1], [$width - 1, $height - 1]] as [$x, $y]) {
            if (self::alpha($image, $x, $y) < 0.9) {
                return null;
            }
        }

        $total = 0.0;
        $weight = 0.0;
        for ($i = 0; $i < self::GRID; $i++) {
            for ($j = 0; $j < self::GRID; $j++) {
                $x = (int) (($i + 0.5) * $width / self::GRID);
                $y = (int) (($j + 0.5) * $height / self::GRID);
                $opacity = 1 - self::alpha($image, $x, $y);
                if ($opacity < 0.25) {
                    continue;
                }
                $rgb = imagecolorsforindex($image, imagecolorat($image, $x, $y));
                $total += $opacity * (0.2126 * $rgb['red'] + 0.7152 * $rgb['green'] + 0.0722 * $rgb['blue']) / 255;
                $weight += $opacity;
            }
        }

        if ($weight === 0.0) {
            return null;
        }

        return $total / $weight > 0.6 ? self::LIGHT : self::DARK;
    }

    /**
     * Transparency at a pixel, from 0 (opaque) to 1 (fully transparent).
     */
    private static function alpha(GdImage $image, int $x, int $y): float
    {
        return imagecolorsforindex($image, imagecolorat($image, $x, $y))['alpha'] / 127;
    }
}
