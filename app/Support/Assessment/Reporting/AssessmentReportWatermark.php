<?php

namespace App\Support\Assessment\Reporting;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class AssessmentReportWatermark
{
    /**
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    public function optimizeSettings(array $settings): array
    {
        $enabled = (bool) data_get($settings, 'watermark_enabled', false);
        $path = trim((string) data_get($settings, 'watermark_path'));
        $opacity = min(25, max(5, (int) data_get($settings, 'watermark_opacity', 10)));
        $position = (string) data_get($settings, 'watermark_position', 'center');
        $position = in_array($position, ['top', 'center', 'bottom'], true) ? $position : 'center';
        $width = min(90, max(20, (int) data_get($settings, 'watermark_width', 60)));
        data_set($settings, 'watermark_opacity', $opacity);
        data_set($settings, 'watermark_position', $position);
        data_set($settings, 'watermark_width', $width);

        if (! $enabled || $path === '') {
            return $settings;
        }

        $disk = Storage::disk('local');
        if (! $disk->exists($path)) {
            throw ValidationException::withMessages([
                'data.settings.watermark_path' => 'File watermark privat tidak ditemukan.',
            ]);
        }

        $absolute = $disk->path($path);
        $imageInfo = @getimagesize($absolute);
        $mime = is_array($imageInfo) ? (string) ($imageInfo['mime'] ?? '') : '';

        if (! in_array($mime, ['image/png', 'image/jpeg', 'image/webp'], true)) {
            throw ValidationException::withMessages([
                'data.settings.watermark_path' => 'Watermark harus berupa PNG, JPEG, atau WebP yang valid.',
            ]);
        }

        // File pada folder optimized sudah menjadi aset versi template. Jangan
        // menulis ulang atau menghapusnya karena versi template lama mungkin
        // masih mereferensikan path privat yang sama.
        if (str_starts_with($path, 'assessment-report-template-assets/optimized/')) {
            return $settings;
        }

        if (! function_exists('imagecreatetruecolor')) {
            throw ValidationException::withMessages([
                'data.settings.watermark_path' => 'Optimasi watermark membutuhkan ekstensi GD.',
            ]);
        }

        $source = match ($mime) {
            'image/png' => @imagecreatefrompng($absolute),
            'image/jpeg' => @imagecreatefromjpeg($absolute),
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($absolute) : false,
        };

        if (! $source) {
            throw ValidationException::withMessages([
                'data.settings.watermark_path' => 'Watermark tidak dapat dibaca atau rusak.',
            ]);
        }

        $width = imagesx($source);
        $height = imagesy($source);
        $scale = min(1, 1600 / max(1, $width), 1600 / max(1, $height));
        $targetWidth = max(1, (int) round($width * $scale));
        $targetHeight = max(1, (int) round($height * $scale));
        $target = imagecreatetruecolor($targetWidth, $targetHeight);
        imagealphablending($target, false);
        imagesavealpha($target, true);
        $transparent = imagecolorallocatealpha($target, 255, 255, 255, 127);
        imagefilledrectangle($target, 0, 0, $targetWidth, $targetHeight, $transparent);
        imagecopyresampled($target, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);

        $optimizedPath = 'assessment-report-template-assets/optimized/'.Str::uuid().'.png';
        $temporary = tempnam(sys_get_temp_dir(), 'assessment-watermark-');
        if (! is_string($temporary) || ! imagepng($target, $temporary, 8)) {
            imagedestroy($source);
            imagedestroy($target);
            throw ValidationException::withMessages([
                'data.settings.watermark_path' => 'Watermark gagal dioptimalkan.',
            ]);
        }

        imagedestroy($source);
        imagedestroy($target);
        $contents = file_get_contents($temporary);
        @unlink($temporary);

        if (! is_string($contents) || ! $disk->put($optimizedPath, $contents)) {
            throw ValidationException::withMessages([
                'data.settings.watermark_path' => 'Watermark gagal disimpan pada storage privat.',
            ]);
        }

        if (str_starts_with($path, 'assessment-report-template-assets/uploads/')) {
            $disk->delete($path);
        }

        data_set($settings, 'watermark_path', $optimizedPath);

        return $settings;
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    public function freezeSettings(array $settings): array
    {
        $path = trim((string) data_get($settings, 'watermark_path'));
        unset($settings['watermark_data_uri']);

        if (! (bool) data_get($settings, 'watermark_enabled', false) || $path === '') {
            unset($settings['watermark_path']);

            return $settings;
        }

        $disk = Storage::disk('local');
        if (! $disk->exists($path)) {
            throw ValidationException::withMessages([
                'watermark' => 'Watermark template tidak ditemukan pada storage privat.',
            ]);
        }

        $contents = $disk->get($path);
        data_set($settings, 'watermark_data_uri', 'data:image/png;base64,'.base64_encode($contents));
        unset($settings['watermark_path']);

        return $settings;
    }
}
