<?php

namespace App\Support\Sarpras;

use App\Models\SarprasBospInventory;
use Illuminate\Support\Str;

class SarprasBospStickerImage
{
    private const SCALE = 10;

    /**
     * @param  array<string, string>  $settings
     */
    public static function renderDataUri(SarprasBospInventory $record, array $settings, ?string $logoSrc, string $qrImage): string
    {
        $year = $record->tahun_beli ?: now()->year;
        $itemCode = filled($record->kode_barang) ? (string) $record->kode_barang : 'ID-'.$record->getKey();
        $location = filled($record->tempat_stiker)
            ? (string) $record->tempat_stiker
            : (filled($record->lokasi_barang) ? (string) $record->lokasi_barang : 'Tempat belum diisi');

        return static::renderDataUriFromData([
            'year' => (string) $year,
            'inventory_id' => Str::limit($itemCode, 24, ''),
            'location' => Str::limit($location, 28, ''),
        ], $settings, $logoSrc, $qrImage);
    }

    /**
     * @param  array{year: string, inventory_id: string, location: string}  $data
     * @param  array<string, string>  $settings
     */
    public static function renderDataUriFromData(array $data, array $settings, ?string $logoSrc, string $qrImage): string
    {
        $layout = static::layout($settings, $data['year']);
        $scale = static::SCALE;
        $width = static::px($layout['widthMm']);
        $height = static::px($layout['heightMm']);

        $image = imagecreatetruecolor($width, $height);
        imagealphablending($image, true);
        imagesavealpha($image, true);

        $white = imagecolorallocate($image, 255, 255, 255);
        $black = imagecolorallocate($image, 0, 0, 0);
        $text = imagecolorallocate($image, 2, 6, 23);

        imagefilledrectangle($image, 0, 0, $width, $height, $white);

        static::rectangle($image, 0, 0, $layout['frameWidthMm'], $layout['frameHeightMm'], $black, 4);
        static::rectangle($image, 0.8, 0.8, $layout['innerWidthMm'], $layout['innerHeightMm'], $black, 3);
        static::filledRect($image, $layout['logoColumnMm'] + 0.8, 0.8, 0.35, $layout['innerHeightMm'], $black);
        static::filledRect($image, $layout['logoColumnMm'] + $layout['middleColumnMm'] + 0.8, 0.8, 0.35, $layout['innerHeightMm'], $black);
        static::filledRect($image, $layout['logoColumnMm'] + 0.8, $layout['schoolRowMm'] + 0.8, $layout['middleColumnMm'], 0.35, $black);

        $logoBoxX = 0.8 + (($layout['logoColumnMm'] - $layout['logoBoxMm']) / 2);
        $logoBoxY = 0.8 + $layout['logoTopMm'];
        static::drawContainedImage($image, $logoSrc, $logoBoxX, $logoBoxY, $layout['logoBoxMm'], $layout['logoBoxMm']);

        $schoolText = strtoupper(trim((string) ($settings[SarprasStickerSettings::SCHOOL_TEXT] ?? 'SMA AFBS'))) ?: 'SMA AFBS';
        $programText = strtoupper(trim((string) ($settings[SarprasStickerSettings::PROGRAM_TEXT] ?? 'BOSP'))) ?: 'BOSP';
        $font = static::boldFont();

        $middleX = 0.8 + $layout['logoColumnMm'];
        $textOffsetY = $layout['textOffsetYMm'];
        static::drawCenteredText(
            $image,
            $schoolText,
            $middleX,
            0.8 + $textOffsetY,
            $layout['middleColumnMm'],
            $layout['schoolRowMm'],
            $layout['schoolFontPt'],
            $font,
            $text
        );
        static::drawCenteredText(
            $image,
            $programText.' TAHUN '.$data['year'],
            $middleX,
            0.8 + $layout['schoolRowMm'] + $textOffsetY,
            $layout['middleColumnMm'],
            $layout['programRowMm'],
            $layout['programFontPt'],
            $font,
            $text
        );

        $rightX = 0.8 + $layout['logoColumnMm'] + $layout['middleColumnMm'];
        static::drawContainedImage(
            $image,
            $qrImage,
            $rightX + (($layout['qrColumnMm'] - $layout['qrSizeMm']) / 2),
            0.8 + $layout['qrTopMm'],
            $layout['qrSizeMm'],
            $layout['qrSizeMm']
        );

        static::drawCenteredText($image, $programText.' TAHUN '.$data['year'], $rightX + 1, 0.8 + $layout['programLineTopMm'], $layout['qrColumnMm'] - 2, $layout['rightLineHeightMm'], $layout['detailFontPt'], $font, $text);
        static::drawCenteredText($image, 'ID: '.$data['inventory_id'], $rightX + 1, 0.8 + $layout['inventoryLineTopMm'], $layout['qrColumnMm'] - 2, $layout['rightLineHeightMm'], $layout['detailFontPt'], $font, $text);
        static::drawCenteredText($image, strtoupper((string) $data['location']), $rightX + 1, 0.8 + $layout['locationLineTopMm'], $layout['qrColumnMm'] - 2, $layout['rightLineHeightMm'], $layout['detailFontPt'], $font, $text);

        ob_start();
        imagepng($image, null, 9);
        $contents = ob_get_clean() ?: '';
        imagedestroy($image);

        return 'data:image/png;base64,'.base64_encode($contents);
    }

    /**
     * @param  array<string, string>  $settings
     * @return array<string, float>
     */
    public static function layout(array $settings, int|string|null $year = null): array
    {
        $widthMm = static::settingFloat($settings, SarprasStickerSettings::WIDTH_MM, 96, 70, 140);
        $heightMm = static::settingFloat($settings, SarprasStickerSettings::HEIGHT_MM, 25.5, 18, 50);
        $innerWidthMm = max(20, $widthMm - 2.8);
        $innerHeightMm = max(12, $heightMm - 2.8);
        $logoColumnMm = max(21, min(static::settingFloat($settings, SarprasStickerSettings::LOGO_COLUMN_MM, 25, 16, 45), $innerWidthMm * 0.31));
        $qrColumnMm = max(28, min(static::settingFloat($settings, SarprasStickerSettings::QR_COLUMN_MM, 34, 24, 55), $innerWidthMm * 0.34));
        $minMiddleColumnMm = min(36, max(31, $innerWidthMm * 0.36));

        if (($logoColumnMm + $qrColumnMm + $minMiddleColumnMm) > $innerWidthMm) {
            $sideScale = max(0.1, ($innerWidthMm - $minMiddleColumnMm) / max(1, $logoColumnMm + $qrColumnMm));
            $logoColumnMm = max(20, $logoColumnMm * $sideScale);
            $qrColumnMm = max(27, $qrColumnMm * $sideScale);
        }

        $middleColumnMm = max(20, $innerWidthMm - $logoColumnMm - $qrColumnMm);
        $schoolRowMm = round($innerHeightMm * 0.5, 2);
        $programRowMm = round($innerHeightMm - $schoolRowMm, 2);
        $manualLogoSizeMm = static::settingFloat($settings, SarprasStickerSettings::LOGO_SIZE_MM, 0, 0, 35);
        $logoAutoBoxMm = max(10, min($logoColumnMm - 4, $innerHeightMm - 3));
        $logoBoxMm = $manualLogoSizeMm > 0
            ? max(8, min($manualLogoSizeMm, $logoColumnMm - 2.4, $innerHeightMm - 1.6))
            : $logoAutoBoxMm;
        $logoOffsetYMm = static::settingFloat($settings, SarprasStickerSettings::LOGO_OFFSET_Y_MM, 0, -8, 8);
        $logoTopMm = max(0.7, min($innerHeightMm - $logoBoxMm - 0.7, (($innerHeightMm - $logoBoxMm) / 2) + $logoOffsetYMm));
        $manualQrSizeMm = static::settingFloat($settings, SarprasStickerSettings::QR_SIZE_MM, 0, 0, 20);
        $qrAutoSizeMm = max(8.5, min($qrColumnMm - 16, $innerHeightMm - 13.2, 9.8));
        $qrSizeMm = $manualQrSizeMm > 0 ? max(7, min($manualQrSizeMm, $qrColumnMm - 12, $innerHeightMm - 12)) : $qrAutoSizeMm;
        $rightLineHeightMm = static::settingFloat($settings, SarprasStickerSettings::RIGHT_LINE_HEIGHT_MM, 3.05, 2.4, 5);
        $rightGapMm = static::settingFloat($settings, SarprasStickerSettings::RIGHT_GAP_MM, 0.35, 0, 2);
        $rightStackHeightMm = ($rightLineHeightMm * 3) + $qrSizeMm + ($rightGapMm * 3);
        $rightStackTopMm = max(0.8, min(
            $innerHeightMm - $rightStackHeightMm - 0.8,
            (($innerHeightMm - $rightStackHeightMm) / 2) + static::settingFloat($settings, SarprasStickerSettings::QR_GROUP_OFFSET_Y_MM, 0, -6, 6)
        ));

        $schoolText = strtoupper(trim((string) ($settings[SarprasStickerSettings::SCHOOL_TEXT] ?? 'SMA AFBS'))) ?: 'SMA AFBS';
        $programText = strtoupper(trim((string) ($settings[SarprasStickerSettings::PROGRAM_TEXT] ?? 'BOSP'))) ?: 'BOSP';
        $schoolFitPt = ($middleColumnMm * 2.83465 * 1.16) / max(1, mb_strlen($schoolText));
        $programFitPt = ($middleColumnMm * 2.83465 * 1.06) / max(1, mb_strlen($programText.' TAHUN '.($year ?: 2025)));

        return [
            'widthMm' => $widthMm,
            'heightMm' => $heightMm,
            'frameWidthMm' => max(20, $widthMm - 1.2),
            'frameHeightMm' => max(12, $heightMm - 1.2),
            'innerWidthMm' => $innerWidthMm,
            'innerHeightMm' => $innerHeightMm,
            'logoColumnMm' => $logoColumnMm,
            'middleColumnMm' => $middleColumnMm,
            'qrColumnMm' => $qrColumnMm,
            'schoolRowMm' => $schoolRowMm,
            'programRowMm' => $programRowMm,
            'logoBoxMm' => $logoBoxMm,
            'logoTopMm' => $logoTopMm,
            'textOffsetYMm' => static::settingFloat($settings, SarprasStickerSettings::TEXT_OFFSET_Y_MM, 0, -6, 6),
            'qrSizeMm' => $qrSizeMm,
            'rightLineHeightMm' => $rightLineHeightMm,
            'rightGapMm' => $rightGapMm,
            'programLineTopMm' => $rightStackTopMm,
            'qrTopMm' => $rightStackTopMm + $rightLineHeightMm + $rightGapMm,
            'inventoryLineTopMm' => $rightStackTopMm + $rightLineHeightMm + $rightGapMm + $qrSizeMm + $rightGapMm,
            'locationLineTopMm' => $rightStackTopMm + $rightLineHeightMm + $rightGapMm + $qrSizeMm + $rightGapMm + $rightLineHeightMm + ($rightGapMm * 0.55),
            'schoolFontPt' => max(8.2, min(static::settingFloat($settings, SarprasStickerSettings::SCHOOL_FONT_PT, 13.5, 10, 28), $schoolFitPt, 12.0)),
            'programFontPt' => max(6.4, min(static::settingFloat($settings, SarprasStickerSettings::PROGRAM_FONT_PT, 9.5, 8, 22), $programFitPt, 7.8)),
            'detailFontPt' => max(4, min(static::settingFloat($settings, SarprasStickerSettings::DETAIL_FONT_PT, 5.4, 4, 10), 4.6)),
        ];
    }

    private static function drawContainedImage(\GdImage $canvas, ?string $src, float $xMm, float $yMm, float $widthMm, float $heightMm): void
    {
        $source = static::imageFromSource($src);

        if (! $source instanceof \GdImage) {
            return;
        }

        $srcWidth = imagesx($source);
        $srcHeight = imagesy($source);
        $targetWidth = static::px($widthMm);
        $targetHeight = static::px($heightMm);
        $ratio = min($targetWidth / max(1, $srcWidth), $targetHeight / max(1, $srcHeight));
        $drawWidth = max(1, (int) round($srcWidth * $ratio));
        $drawHeight = max(1, (int) round($srcHeight * $ratio));
        $drawX = static::px($xMm) + (int) round(($targetWidth - $drawWidth) / 2);
        $drawY = static::px($yMm) + (int) round(($targetHeight - $drawHeight) / 2);

        imagecopyresampled($canvas, $source, $drawX, $drawY, 0, 0, $drawWidth, $drawHeight, $srcWidth, $srcHeight);
        imagedestroy($source);
    }

    private static function drawCenteredText(\GdImage $image, string $text, float $xMm, float $yMm, float $widthMm, float $heightMm, float $fontPt, string $font, int $color): void
    {
        $fontSize = $fontPt * static::SCALE * 0.352778;
        $maxWidth = static::px($widthMm);

        while ($fontSize > 4) {
            $box = imagettfbbox($fontSize, 0, $font, $text);
            $textWidth = abs(($box[2] ?? 0) - ($box[0] ?? 0));

            if ($textWidth <= $maxWidth - static::px(1)) {
                break;
            }

            $fontSize -= 0.5;
        }

        $box = imagettfbbox($fontSize, 0, $font, $text);
        $textWidth = abs(($box[2] ?? 0) - ($box[0] ?? 0));
        $textHeight = abs(($box[7] ?? 0) - ($box[1] ?? 0));
        $x = static::px($xMm) + (int) round(($maxWidth - $textWidth) / 2) - (int) ($box[0] ?? 0);
        $y = static::px($yMm) + (int) round((static::px($heightMm) - $textHeight) / 2) - (int) ($box[7] ?? 0);

        imagettftext($image, $fontSize, 0, $x, $y, $color, $font, $text);
    }

    private static function imageFromSource(?string $src): ?\GdImage
    {
        if (! filled($src)) {
            return null;
        }

        $contents = null;

        if (Str::startsWith((string) $src, 'data:image')) {
            $contents = base64_decode(Str::after((string) $src, ','), true) ?: null;
        } elseif (is_file((string) $src)) {
            $contents = file_get_contents((string) $src) ?: null;
        }

        return filled($contents) ? @imagecreatefromstring($contents) ?: null : null;
    }

    private static function boldFont(): string
    {
        foreach ([
            'C:\\Windows\\Fonts\\arialbd.ttf',
            'C:\\Windows\\Fonts\\tahomabd.ttf',
            '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
        ] as $font) {
            if (is_file($font)) {
                return $font;
            }
        }

        return 'C:\\Windows\\Fonts\\arialbd.ttf';
    }

    private static function rectangle(\GdImage $image, float $xMm, float $yMm, float $widthMm, float $heightMm, int $color, int $thickness): void
    {
        imagesetthickness($image, $thickness);
        imagerectangle($image, static::px($xMm), static::px($yMm), static::px($xMm + $widthMm), static::px($yMm + $heightMm), $color);
    }

    private static function filledRect(\GdImage $image, float $xMm, float $yMm, float $widthMm, float $heightMm, int $color): void
    {
        imagefilledrectangle($image, static::px($xMm), static::px($yMm), static::px($xMm + $widthMm), static::px($yMm + $heightMm), $color);
    }

    private static function px(float $mm): int
    {
        return (int) round($mm * static::SCALE);
    }

    /**
     * @param  array<string, string>  $settings
     */
    private static function settingFloat(array $settings, string $key, float $default, float $min, float $max): float
    {
        $value = str_replace(',', '.', trim((string) ($settings[$key] ?? '')));

        if ($value === '' || ! is_numeric($value)) {
            return $default;
        }

        return max($min, min($max, (float) $value));
    }
}
