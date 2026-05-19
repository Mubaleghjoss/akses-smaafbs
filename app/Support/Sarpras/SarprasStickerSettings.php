<?php

namespace App\Support\Sarpras;

use App\Models\Pengaturan;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SarprasStickerSettings
{
    public const LOGO_PATH = 'sarpras_sticker_logo_path';
    public const SCHOOL_TEXT = 'sarpras_sticker_school_text';
    public const PROGRAM_TEXT = 'sarpras_sticker_program_text';
    public const WIDTH_MM = 'sarpras_sticker_width_mm';
    public const HEIGHT_MM = 'sarpras_sticker_height_mm';
    public const LOGO_COLUMN_MM = 'sarpras_sticker_logo_column_mm';
    public const QR_COLUMN_MM = 'sarpras_sticker_qr_column_mm';
    public const SCHOOL_FONT_PT = 'sarpras_sticker_school_font_pt';
    public const PROGRAM_FONT_PT = 'sarpras_sticker_program_font_pt';
    public const DETAIL_FONT_PT = 'sarpras_sticker_detail_font_pt';
    public const LOGO_SIZE_MM = 'sarpras_sticker_logo_size_mm';
    public const LOGO_OFFSET_Y_MM = 'sarpras_sticker_logo_offset_y_mm';
    public const TEXT_OFFSET_Y_MM = 'sarpras_sticker_text_offset_y_mm';
    public const QR_GROUP_OFFSET_Y_MM = 'sarpras_sticker_qr_group_offset_y_mm';
    public const QR_SIZE_MM = 'sarpras_sticker_qr_size_mm';
    public const RIGHT_LINE_HEIGHT_MM = 'sarpras_sticker_right_line_height_mm';
    public const RIGHT_GAP_MM = 'sarpras_sticker_right_gap_mm';

    /**
     * @return array<string, string>
     */
    public static function defaults(): array
    {
        return [
            self::LOGO_PATH => '',
            self::SCHOOL_TEXT => 'SMA AFBS',
            self::PROGRAM_TEXT => 'BOSP',
            self::WIDTH_MM => '96',
            self::HEIGHT_MM => '25.5',
            self::LOGO_COLUMN_MM => '25',
            self::QR_COLUMN_MM => '34',
            self::SCHOOL_FONT_PT => '13.5',
            self::PROGRAM_FONT_PT => '9.5',
            self::DETAIL_FONT_PT => '5.4',
            self::LOGO_SIZE_MM => '0',
            self::LOGO_OFFSET_Y_MM => '0',
            self::TEXT_OFFSET_Y_MM => '0',
            self::QR_GROUP_OFFSET_Y_MM => '0',
            self::QR_SIZE_MM => '0',
            self::RIGHT_LINE_HEIGHT_MM => '3.05',
            self::RIGHT_GAP_MM => '0.35',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function all(): array
    {
        $keys = array_keys(static::defaults());
        $values = Pengaturan::values($keys, static::defaults());

        return collect(static::defaults())
            ->map(fn (string $default, string $key): string => filled($values[$key] ?? null) ? (string) $values[$key] : $default)
            ->all();
    }

    public static function resolvedLogoPath(?string $fallbackLogo = null): ?string
    {
        $settings = static::all();

        return static::printableAssetSource($settings[self::LOGO_PATH] ?? null)
            ?? static::printableAssetSource('images/bosp-logo.png')
            ?? static::printableAssetSource('images/bosp-logo.jpg')
            ?? static::printableAssetSource('images/bosp-logo.jpeg')
            ?? static::printableAssetSource('images/bosp.png')
            ?? static::printableAssetSource('images/bosp.jpg')
            ?? static::printableAssetSource('images/bosp.jpeg')
            ?? $fallbackLogo;
    }

    public static function imageDataUri(?string $value): ?string
    {
        $asset = trim((string) $value);

        if ($asset === '') {
            return null;
        }

        if (Str::startsWith($asset, 'data:')) {
            return $asset;
        }

        $path = static::printableAssetSource($asset);

        if (! is_string($path) || preg_match('#^https?://#i', $path) === 1 || ! is_file($path)) {
            return $path ?: null;
        }

        $contents = file_get_contents($path);

        if ($contents === false) {
            return null;
        }

        $mime = mime_content_type($path) ?: 'image/png';

        return 'data:'.$mime.';base64,'.base64_encode($contents);
    }

    public static function number(string $key): float
    {
        $value = static::all()[$key] ?? static::defaults()[$key] ?? '0';

        return (float) str_replace(',', '.', (string) $value);
    }

    public static function text(string $key): string
    {
        $value = trim((string) (static::all()[$key] ?? static::defaults()[$key] ?? ''));

        return $value !== '' ? $value : (static::defaults()[$key] ?? '');
    }

    public static function upsert(string $key, mixed $value): void
    {
        Pengaturan::query()->updateOrCreate(
            ['nama_pengaturan' => $key],
            ['nilai_pengaturan' => filled($value) ? trim((string) $value) : null]
        );
    }

    private static function printableAssetSource(?string $value): ?string
    {
        $asset = trim((string) $value);

        if ($asset === '') {
            return null;
        }

        if (Str::startsWith($asset, ['/storage/', 'storage/'])) {
            $relative = Str::startsWith($asset, '/storage/')
                ? Str::after($asset, '/storage/')
                : Str::after($asset, 'storage/');

            $storagePath = Storage::disk('public')->path($relative);

            return is_file($storagePath) ? $storagePath : null;
        }

        if (! Str::startsWith($asset, ['/', '\\']) && Storage::disk('public')->exists($asset)) {
            return Storage::disk('public')->path($asset);
        }

        if (! Str::startsWith($asset, ['/', '\\']) && is_file(public_path('storage/'.$asset))) {
            return public_path('storage/'.$asset);
        }

        if (! Str::startsWith($asset, ['/', '\\']) && is_file(public_path($asset))) {
            return public_path($asset);
        }

        return is_file($asset) ? $asset : null;
    }
}
