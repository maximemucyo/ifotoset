<?php

namespace App\Support;

class ExifReader
{
    /**
     * Extract camera, exposure, orientation and capture time from a local image file.
     * A truncated file works too, as long as it holds the EXIF header.
     */
    public static function read(string $tempFilePath): array
    {
        $metadata = [
            'camera' => null,
            'lens' => null,
            'iso' => null,
            'shutter_speed' => null,
            'aperture' => null,
            'focal_length' => null,
            'flash' => null,
            'orientation' => 1,
            'taken_at' => null,
        ];

        if (!function_exists('exif_read_data')) {
            return $metadata;
        }

        $exif = @exif_read_data($tempFilePath);
        if (!$exif || !is_array($exif)) {
            return $metadata;
        }

        // 1. Camera
        $make = $exif['Make'] ?? null;
        $model = $exif['Model'] ?? null;
        if ($make && $model) {
            $metadata['camera'] = trim($make) . ' ' . trim($model);
        } elseif ($model) {
            $metadata['camera'] = trim($model);
        }

        // 2. Lens
        $lens = $exif['LensModel'] ?? $exif['UndefinedTag:0xA434'] ?? null;
        if ($lens) {
            $metadata['lens'] = trim($lens);
        }

        // 3. ISO
        if (isset($exif['ISOSpeedRatings'])) {
            $metadata['iso'] = is_array($exif['ISOSpeedRatings']) ? ($exif['ISOSpeedRatings'][0] ?? null) : $exif['ISOSpeedRatings'];
        }

        // 4. Shutter Speed
        if (isset($exif['ExposureTime'])) {
            $metadata['shutter_speed'] = $exif['ExposureTime'];
        }

        // 5. Aperture
        if (isset($exif['FNumber'])) {
            $fNumber = $exif['FNumber'];
            if (str_contains($fNumber, '/')) {
                [$num, $den] = explode('/', $fNumber);
                if ($den > 0) {
                    $metadata['aperture'] = 'f/' . round($num / $den, 1);
                }
            } else {
                $metadata['aperture'] = 'f/' . $fNumber;
            }
        }

        // 6. Focal Length
        if (isset($exif['FocalLength'])) {
            $focal = $exif['FocalLength'];
            if (str_contains($focal, '/')) {
                [$num, $den] = explode('/', $focal);
                if ($den > 0) {
                    $metadata['focal_length'] = round($num / $den, 1) . 'mm';
                }
            } else {
                $metadata['focal_length'] = $focal . 'mm';
            }
        }

        // 7. Flash
        if (isset($exif['Flash'])) {
            $metadata['flash'] = ($exif['Flash'] & 1) ? 'Flash fired' : 'Flash did not fire';
        }

        // 8. Orientation
        if (isset($exif['Orientation'])) {
            $metadata['orientation'] = (int) $exif['Orientation'];
        }

        // 9. Taken At
        $dateStr = $exif['DateTimeOriginal'] ?? $exif['DateTimeDigitized'] ?? $exif['DateTime'] ?? null;
        // Cameras with an unset clock write zeros; NUL padding is common too
        $dateStr = is_string($dateStr) ? trim($dateStr, " \0") : null;
        if ($dateStr && !str_starts_with($dateStr, '0000')) {
            try {
                $metadata['taken_at'] = \Illuminate\Support\Carbon::createFromFormat('Y:m:d H:i:s', $dateStr);
            } catch (\Exception $e) {
                // Ignore parsing errors
            }
        }

        return $metadata;
    }
}
