<?php

namespace App\Support\Gallery;

use GdImage;
use RuntimeException;

/**
 * Draws a studio's watermark onto a photo (GD, in place). The mark is sized from the photo, so a
 * preview made at 2560px and scaled down keeps the same proportions at every size.
 */
class WatermarkRenderer
{
    /** Text is drawn large and scaled down, which keeps its edges smooth */
    private const TEXT_SIZE = 160;

    public static function fontPath(): string
    {
        return resource_path('fonts/InstrumentSerif-Regular.ttf');
    }

    /**
     * @param  GdImage|null  $logo  the portfolio logo, when the watermark uses it
     */
    public function apply(GdImage $photo, Watermark $watermark, ?GdImage $logo = null): void
    {
        $mark = $watermark->type() === 'logo' && $logo
            ? $this->logoMark($logo, $watermark->owner()->portfolio()->logoTone() !== null)
            : $this->textMark($watermark->text());

        $width = imagesx($photo);
        $height = imagesy($photo);
        $short = min($width, $height);

        [$maxWidth, $maxHeight] = match ($watermark->position()) {
            'corner' => [$width * 0.24, $height * 0.1],
            'tiled' => [$short * 0.3, $short * 0.09],
            default => [$width * 0.5, $height * 0.22],
        };
        $mark = $this->fit($mark, $maxWidth, $maxHeight);
        $this->fade($mark, $watermark->opacity() / 100);

        imagealphablending($photo, true);
        $markWidth = imagesx($mark);
        $markHeight = imagesy($mark);

        switch ($watermark->position()) {
            case 'corner':
                $margin = (int) round($short * 0.04);
                imagecopy($photo, $mark, $width - $markWidth - $margin, $height - $markHeight - $margin, 0, 0, $markWidth, $markHeight);
                break;
            case 'tiled':
                // Tilted and staggered, so no part of the photo is left clean to crop
                $tile = imagerotate($mark, 25, imagecolorallocatealpha($mark, 0, 0, 0, 127));
                imagesavealpha($tile, true);
                $tileWidth = imagesx($tile);
                $tileHeight = imagesy($tile);
                $stepX = (int) max(1, $tileWidth * 1.5);
                $stepY = (int) max(1, $tileHeight * 1.3);
                for ($row = 0, $y = -$tileHeight / 2; $y < $height; $row++, $y += $stepY) {
                    for ($x = ($row % 2) * $stepX / 2 - $tileWidth / 2; $x < $width; $x += $stepX) {
                        imagecopy($photo, $tile, (int) $x, (int) $y, 0, 0, $tileWidth, $tileHeight);
                    }
                }
                break;
            default:
                imagecopy($photo, $mark, (int) (($width - $markWidth) / 2), (int) (($height - $markHeight) / 2), 0, 0, $markWidth, $markHeight);
        }
    }

    /**
     * White text with a faint shadow, so it reads on light and dark photos.
     */
    protected function textMark(string $text): GdImage
    {
        if (!function_exists('imagettftext')) {
            throw new RuntimeException('GD was built without FreeType, so text watermarks cannot be drawn.');
        }

        $box = imagettfbbox(self::TEXT_SIZE, 0, self::fontPath(), $text);
        $pad = (int) (self::TEXT_SIZE * 0.2);
        $width = $box[2] - $box[0] + 2 * $pad;
        $height = $box[1] - $box[7] + 2 * $pad;

        $canvas = $this->blank($width, $height);
        imagealphablending($canvas, true);
        $x = $pad - $box[0];
        $y = $pad - $box[7];
        $shadowOffset = (int) max(1, self::TEXT_SIZE * 0.012);
        imagettftext($canvas, self::TEXT_SIZE, 0, $x + $shadowOffset, $y + $shadowOffset, imagecolorallocatealpha($canvas, 0, 0, 0, 108), self::fontPath(), $text);
        imagettftext($canvas, self::TEXT_SIZE, 0, $x, $y, imagecolorallocatealpha($canvas, 255, 255, 255, 0), self::fontPath(), $text);

        return $canvas;
    }

    /**
     * A cut-out logo (see LogoTone) is drawn in white like the text; one with its own background as it is.
     */
    protected function logoMark(GdImage $logo, bool $cutOut): GdImage
    {
        $width = imagesx($logo);
        $height = imagesy($logo);
        $mark = $this->blank($width, $height);
        imagecopy($mark, $logo, 0, 0, 0, 0, $width, $height);

        if ($cutOut) {
            for ($x = 0; $x < $width; $x++) {
                for ($y = 0; $y < $height; $y++) {
                    $alpha = (imagecolorat($mark, $x, $y) >> 24) & 0x7F;
                    imagesetpixel($mark, $x, $y, ($alpha << 24) | 0xFFFFFF);
                }
            }
        }

        return $mark;
    }

    protected function fit(GdImage $mark, float $maxWidth, float $maxHeight): GdImage
    {
        $scale = min($maxWidth / imagesx($mark), $maxHeight / imagesy($mark));
        $width = (int) max(1, round(imagesx($mark) * $scale));
        $height = (int) max(1, round(imagesy($mark) * $scale));

        $fitted = $this->blank($width, $height);
        imagecopyresampled($fitted, $mark, 0, 0, 0, 0, $width, $height, imagesx($mark), imagesy($mark));

        return $fitted;
    }

    /**
     * Multiply the mark's opacity; GD can't copy a transparent image at partial opacity.
     */
    protected function fade(GdImage $mark, float $opacity): void
    {
        imagealphablending($mark, false);
        for ($x = 0, $width = imagesx($mark), $height = imagesy($mark); $x < $width; $x++) {
            for ($y = 0; $y < $height; $y++) {
                $color = imagecolorat($mark, $x, $y);
                $alpha = 127 - (int) round((127 - (($color >> 24) & 0x7F)) * $opacity);
                imagesetpixel($mark, $x, $y, ($alpha << 24) | ($color & 0xFFFFFF));
            }
        }
    }

    protected function blank(int $width, int $height): GdImage
    {
        $image = imagecreatetruecolor($width, $height);
        imagealphablending($image, false);
        imagesavealpha($image, true);
        imagefill($image, 0, 0, imagecolorallocatealpha($image, 0, 0, 0, 127));

        return $image;
    }
}
