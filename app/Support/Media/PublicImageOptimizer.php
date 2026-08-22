<?php

namespace App\Support\Media;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class PublicImageOptimizer
{
    /**
     * @return array{path:string,thumbnail_path:?string,original_bytes:int,optimized_bytes:int,width:int,height:int}
     */
    public function optimize(string $path, string $profile): array
    {
        $path = $this->normalizeLocalPath($path);
        $disk = Storage::disk('public');

        if (! $disk->exists($path)) {
            throw new RuntimeException('File gambar tidak ditemukan: '.$path);
        }

        $settings = config('public_media.profiles.'.$profile);

        if (! is_array($settings)) {
            throw new RuntimeException('Profil optimasi gambar tidak dikenal: '.$profile);
        }

        $absolutePath = $disk->path($path);
        $mime = (string) ($disk->mimeType($path) ?: '');

        if ($mime === 'image/svg+xml') {
            if ($profile !== 'logo') {
                throw new RuntimeException('SVG hanya diperbolehkan untuk logo.');
            }

            return [
                'path' => $path,
                'thumbnail_path' => null,
                'original_bytes' => (int) $disk->size($path),
                'optimized_bytes' => (int) $disk->size($path),
                'width' => 0,
                'height' => 0,
            ];
        }

        $source = $this->decode($absolutePath, $mime);
        $source = $this->applyExifOrientation($source, $absolutePath, $mime);
        $sourceWidth = imagesx($source);
        $sourceHeight = imagesy($source);

        if ($sourceWidth < 1 || $sourceHeight < 1) {
            imagedestroy($source);

            throw new RuntimeException('Dimensi gambar tidak valid.');
        }

        [$targetWidth, $targetHeight] = $this->containedDimensions(
            $sourceWidth,
            $sourceHeight,
            (int) ($settings['width'] ?? 1280),
            (int) ($settings['height'] ?? 1280),
        );

        $optimizedPath = $this->displayPath($path);
        $optimized = $this->resizeContained($source, $targetWidth, $targetHeight);
        $this->writeWebp($optimized, $disk->path($optimizedPath));
        imagedestroy($optimized);

        $thumbnailPath = null;

        if (isset($settings['thumbnail_width'], $settings['thumbnail_height'])) {
            $thumbnailPath = $this->thumbnailPath($optimizedPath);
            $thumbnail = $this->resizeCover(
                $source,
                (int) $settings['thumbnail_width'],
                (int) $settings['thumbnail_height'],
            );
            $this->writeWebp($thumbnail, $disk->path($thumbnailPath));
            imagedestroy($thumbnail);
        }

        imagedestroy($source);

        return [
            'path' => $optimizedPath,
            'thumbnail_path' => $thumbnailPath,
            'original_bytes' => (int) $disk->size($path),
            'optimized_bytes' => (int) $disk->size($optimizedPath)
                + ($thumbnailPath !== null ? (int) $disk->size($thumbnailPath) : 0),
            'width' => $targetWidth,
            'height' => $targetHeight,
        ];
    }

    /**
     * Optimize a newly uploaded local file while keeping test/legacy paths compatible.
     */
    public function optimizeUploadedPath(?string $path, string $profile): ?string
    {
        if (! config('public_media.enabled', true) || blank($path) || $this->isExternalPath((string) $path)) {
            return $path;
        }

        $normalized = $this->normalizeLocalPath((string) $path);

        if (preg_match('/\.(?:avif|gif|jpe?g|png|webp|svg)$/i', $normalized) !== 1) {
            return $path;
        }

        if (! Storage::disk('public')->exists($normalized)) {
            return $path;
        }

        if (Str::endsWith($normalized, '-optimized.webp')) {
            return $normalized;
        }

        return $this->optimize($normalized, $profile)['path'];
    }

    public function optimizeEmbeddedPaths(string $value, string $directory, string $profile = 'content'): string
    {
        if (! config('public_media.enabled', true) || trim($value) === '') {
            return $value;
        }

        $quotedDirectory = preg_quote(trim($directory, '/'), '#');
        preg_match_all(
            '#(?:https?://[^"\'\s]+/storage/|/?storage/)?(?<path>'.$quotedDirectory.'/[^"\'<>\s\\\\]+?\.(?:avif|gif|jpe?g|png|webp))#i',
            $value,
            $matches,
        );

        foreach (array_unique($matches['path'] ?? []) as $path) {
            $optimizedPath = $this->optimizeUploadedPath((string) $path, $profile);

            if (filled($optimizedPath) && $optimizedPath !== $path) {
                $value = str_replace((string) $path, (string) $optimizedPath, $value);
            }
        }

        return $value;
    }

    /**
     * @return array{favicon_path:string,pwa_192_path:string,pwa_512_path:string}
     */
    public function optimizeBrandingIcons(string $path): array
    {
        $path = $this->normalizeLocalPath($path);
        $disk = Storage::disk('public');

        if (! $disk->exists($path)) {
            throw new RuntimeException('File ikon tidak ditemukan: '.$path);
        }

        $mime = (string) ($disk->mimeType($path) ?: '');

        if ($mime === 'image/svg+xml') {
            return [
                'favicon_path' => $path,
                'pwa_192_path' => $path,
                'pwa_512_path' => $path,
            ];
        }

        $source = $this->decode($disk->path($path), $mime);
        $source = $this->applyExifOrientation($source, $disk->path($path), $mime);
        $paths = [
            'favicon_path' => $this->squarePngPath($path, 'favicon'),
            'pwa_192_path' => $this->squarePngPath($path, 'pwa-192'),
            'pwa_512_path' => $this->squarePngPath($path, 'pwa-512'),
        ];

        foreach ([
            'favicon_path' => 64,
            'pwa_192_path' => 192,
            'pwa_512_path' => 512,
        ] as $key => $size) {
            $icon = $this->resizeIntoSquare($source, $size);
            $this->writePng($icon, $disk->path($paths[$key]));
            imagedestroy($icon);
        }

        imagedestroy($source);

        return $paths;
    }

    public function pwaIconUrl(?string $path, int $size): ?string
    {
        if (blank($path)) {
            return null;
        }

        $path = trim((string) $path);

        if ($this->isExternalPath($path)) {
            return $path;
        }

        $path = $this->normalizeLocalPath($path);
        $candidate = $this->squarePngPath($path, $size >= 512 ? 'pwa-512' : 'pwa-192');
        $disk = Storage::disk('public');

        return $disk->url($disk->exists($candidate) ? $candidate : $path);
    }

    public function displayPath(string $path): string
    {
        $path = $this->normalizeLocalPath($path);
        $directory = trim(str_replace('\\', '/', dirname($path)), './');
        $filename = pathinfo($path, PATHINFO_FILENAME);
        $filename = preg_replace('/-(?:optimized|thumb)$/', '', $filename) ?: $filename;
        $target = $filename.'-optimized.webp';

        return $directory !== '' ? $directory.'/'.$target : $target;
    }

    public function thumbnailPath(string $path): string
    {
        $path = $this->normalizeLocalPath($path);
        $directory = trim(str_replace('\\', '/', dirname($path)), './');
        $filename = pathinfo($path, PATHINFO_FILENAME);
        $filename = preg_replace('/-(?:optimized|thumb)$/', '', $filename) ?: $filename;
        $target = $filename.'-thumb.webp';

        return $directory !== '' ? $directory.'/'.$target : $target;
    }

    public function squarePngPath(string $path, string $suffix): string
    {
        $path = $this->normalizeLocalPath($path);
        $directory = trim(str_replace('\\', '/', dirname($path)), './');
        $filename = pathinfo($path, PATHINFO_FILENAME);
        $filename = preg_replace('/-(?:favicon|pwa-192|pwa-512)$/', '', $filename) ?: $filename;
        $target = $filename.'-'.$suffix.'.png';

        return $directory !== '' ? $directory.'/'.$target : $target;
    }

    public function url(?string $path, string $variant = 'display'): ?string
    {
        if (blank($path)) {
            return null;
        }

        $path = trim((string) $path);

        if ($this->isExternalPath($path)) {
            return $path;
        }

        $path = $this->normalizeLocalPath($path);
        $disk = Storage::disk('public');
        $candidate = $variant === 'thumbnail'
            ? $this->thumbnailPath($path)
            : $this->displayPath($path);

        if ($disk->exists($candidate)) {
            return $disk->url($candidate);
        }

        return $disk->url($path);
    }

    public function removeVariants(?string $path): void
    {
        if (blank($path) || $this->isExternalPath((string) $path)) {
            return;
        }

        $path = $this->normalizeLocalPath((string) $path);

        Storage::disk('public')->delete(array_unique([
            $this->displayPath($path),
            $this->thumbnailPath($path),
        ]));
    }

    public function removeAll(?string $path): void
    {
        if (blank($path) || $this->isExternalPath((string) $path)) {
            return;
        }

        $path = $this->normalizeLocalPath((string) $path);
        $disk = Storage::disk('public');

        $disk->delete(array_unique([
            $path,
            $this->displayPath($path),
            $this->thumbnailPath($path),
            $this->squarePngPath($path, 'favicon'),
            $this->squarePngPath($path, 'pwa-192'),
            $this->squarePngPath($path, 'pwa-512'),
        ]));
    }

    protected function decode(string $absolutePath, string $mime): \GdImage
    {
        if (! extension_loaded('gd')) {
            throw new RuntimeException('Ekstensi GD tidak tersedia.');
        }

        $contents = @file_get_contents($absolutePath);
        $image = $contents !== false ? @imagecreatefromstring($contents) : false;

        if (! $image instanceof \GdImage) {
            throw new RuntimeException('Format gambar tidak dapat dibaca'.($mime !== '' ? ' ('.$mime.')' : '').'.');
        }

        return $image;
    }

    protected function applyExifOrientation(\GdImage $image, string $absolutePath, string $mime): \GdImage
    {
        if ($mime !== 'image/jpeg' || ! function_exists('exif_read_data')) {
            return $image;
        }

        $exif = @exif_read_data($absolutePath);
        $orientation = (int) ($exif['Orientation'] ?? 1);
        $angle = match ($orientation) {
            3 => 180,
            6 => -90,
            8 => 90,
            default => 0,
        };

        if ($angle === 0) {
            return $image;
        }

        $rotated = imagerotate($image, $angle, 0);

        if (! $rotated instanceof \GdImage) {
            return $image;
        }

        imagedestroy($image);

        return $rotated;
    }

    /**
     * @return array{0:int,1:int}
     */
    protected function containedDimensions(int $width, int $height, int $maxWidth, int $maxHeight): array
    {
        $scale = min(1, $maxWidth / $width, $maxHeight / $height);

        return [
            max(1, (int) round($width * $scale)),
            max(1, (int) round($height * $scale)),
        ];
    }

    protected function resizeContained(\GdImage $source, int $width, int $height): \GdImage
    {
        $target = $this->transparentCanvas($width, $height);
        imagecopyresampled(
            $target,
            $source,
            0,
            0,
            0,
            0,
            $width,
            $height,
            imagesx($source),
            imagesy($source),
        );

        return $target;
    }

    protected function resizeCover(\GdImage $source, int $width, int $height): \GdImage
    {
        $sourceWidth = imagesx($source);
        $sourceHeight = imagesy($source);
        $sourceRatio = $sourceWidth / $sourceHeight;
        $targetRatio = $width / $height;

        if ($sourceRatio > $targetRatio) {
            $cropHeight = $sourceHeight;
            $cropWidth = (int) round($sourceHeight * $targetRatio);
            $sourceX = (int) floor(($sourceWidth - $cropWidth) / 2);
            $sourceY = 0;
        } else {
            $cropWidth = $sourceWidth;
            $cropHeight = (int) round($sourceWidth / $targetRatio);
            $sourceX = 0;
            $sourceY = (int) floor(($sourceHeight - $cropHeight) / 2);
        }

        $target = $this->transparentCanvas($width, $height);
        imagecopyresampled(
            $target,
            $source,
            0,
            0,
            $sourceX,
            $sourceY,
            $width,
            $height,
            $cropWidth,
            $cropHeight,
        );

        return $target;
    }

    protected function resizeIntoSquare(\GdImage $source, int $size): \GdImage
    {
        [$width, $height] = $this->containedDimensions(
            imagesx($source),
            imagesy($source),
            $size,
            $size,
        );
        $resized = $this->resizeContained($source, $width, $height);
        $target = $this->transparentCanvas($size, $size);
        imagecopy(
            $target,
            $resized,
            (int) floor(($size - $width) / 2),
            (int) floor(($size - $height) / 2),
            0,
            0,
            $width,
            $height,
        );
        imagedestroy($resized);

        return $target;
    }

    protected function transparentCanvas(int $width, int $height): \GdImage
    {
        $image = imagecreatetruecolor($width, $height);

        if (! $image instanceof \GdImage) {
            throw new RuntimeException('Gagal menyiapkan kanvas gambar.');
        }

        imagealphablending($image, false);
        imagesavealpha($image, true);
        $transparent = imagecolorallocatealpha($image, 0, 0, 0, 127);
        imagefilledrectangle($image, 0, 0, $width, $height, $transparent);

        return $image;
    }

    protected function writeWebp(\GdImage $image, string $absolutePath): void
    {
        $directory = dirname($absolutePath);

        if (! is_dir($directory) && ! @mkdir($directory, 0775, true) && ! is_dir($directory)) {
            throw new RuntimeException('Direktori hasil gambar tidak dapat dibuat.');
        }

        $temporaryPath = $absolutePath.'.tmp-'.Str::lower(Str::random(8));
        $quality = min(90, max(50, (int) config('public_media.webp_quality', 78)));

        if (! imagewebp($image, $temporaryPath, $quality)) {
            @unlink($temporaryPath);

            throw new RuntimeException('Gagal menulis gambar WebP.');
        }

        if (is_file($absolutePath) && ! @unlink($absolutePath)) {
            @unlink($temporaryPath);

            throw new RuntimeException('File gambar lama tidak dapat diganti.');
        }

        if (! @rename($temporaryPath, $absolutePath)) {
            @unlink($temporaryPath);

            throw new RuntimeException('File gambar hasil optimasi tidak dapat dipindahkan.');
        }
    }

    protected function writePng(\GdImage $image, string $absolutePath): void
    {
        $directory = dirname($absolutePath);

        if (! is_dir($directory) && ! @mkdir($directory, 0775, true) && ! is_dir($directory)) {
            throw new RuntimeException('Direktori hasil ikon tidak dapat dibuat.');
        }

        $temporaryPath = $absolutePath.'.tmp-'.Str::lower(Str::random(8));

        if (! imagepng($image, $temporaryPath, 9)) {
            @unlink($temporaryPath);

            throw new RuntimeException('Gagal menulis ikon PNG.');
        }

        if (is_file($absolutePath) && ! @unlink($absolutePath)) {
            @unlink($temporaryPath);

            throw new RuntimeException('Ikon lama tidak dapat diganti.');
        }

        if (! @rename($temporaryPath, $absolutePath)) {
            @unlink($temporaryPath);

            throw new RuntimeException('Ikon hasil optimasi tidak dapat dipindahkan.');
        }
    }

    protected function normalizeLocalPath(string $path): string
    {
        $path = str_replace('\\', '/', trim($path));
        $path = preg_replace('#^/?(?:public/)?storage/#', '', $path) ?: $path;
        $path = ltrim($path, '/');

        if ($path === '' || Str::contains($path, ['../', "\0"])) {
            throw new RuntimeException('Path gambar tidak aman.');
        }

        return $path;
    }

    protected function isExternalPath(string $path): bool
    {
        return Str::startsWith(trim($path), ['http://', 'https://', 'data:']);
    }
}
