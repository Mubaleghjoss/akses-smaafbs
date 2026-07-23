<?php

namespace App\Support\Perpustakaan;

use App\Contracts\SiteSettingsAccessor;
use App\Models\PerpustakaanLiterasiMaterial;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class LiteracySocialThumbnail
{
    public const WIDTH = 1200;

    public const HEIGHT = 630;

    public function __construct(
        protected SiteSettingsAccessor $siteSettings,
    ) {}

    public function url(PerpustakaanLiterasiMaterial $material): string
    {
        return route('library.literacy.social-thumbnail', [
            'slug' => $material->slug,
            'v' => $material->updated_at?->timestamp ?? $material->getKey(),
        ]);
    }

    public function response(PerpustakaanLiterasiMaterial $material): BinaryFileResponse|RedirectResponse
    {
        $source = $this->sourceFor($material);

        if ($source === null || ! extension_loaded('gd')) {
            return redirect()->away($this->fallbackUrl($material));
        }

        $cacheDirectory = storage_path('app/private/literacy-social-thumbnails');
        File::ensureDirectoryExists($cacheDirectory);

        $sourceVersion = is_file($source['path']) ? (int) filemtime($source['path']) : 0;
        $cacheKey = implode('-', [
            $material->getKey(),
            $material->updated_at?->timestamp ?? 0,
            $sourceVersion,
            $source['mode'],
        ]);
        $cachePath = $cacheDirectory.'/'.sha1($cacheKey).'.jpg';

        if (! is_file($cachePath)) {
            $this->generate($source['path'], $source['mode'], $cachePath);
        }

        if (! is_file($cachePath)) {
            return redirect()->away($this->fallbackUrl($material));
        }

        return response()->file($cachePath, [
            'Cache-Control' => 'public, max-age=604800, immutable',
            'Content-Type' => 'image/jpeg',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * @return array{path: string, mode: 'cover'|'contain'}|null
     */
    protected function sourceFor(PerpustakaanLiterasiMaterial $material): ?array
    {
        $materialPath = $this->publicStoragePath($material->image_path, 'literasi/materials');

        if ($materialPath !== null) {
            return ['path' => $materialPath, 'mode' => 'cover'];
        }

        $logoPath = $this->publicStoragePath($this->siteSettings->logoPath());

        if ($logoPath !== null) {
            return ['path' => $logoPath, 'mode' => 'contain'];
        }

        $fallbackPath = $this->publicStoragePath(
            $this->siteSettings->defaultOgImage() ?? $this->siteSettings->faviconPath()
        );

        return $fallbackPath !== null
            ? ['path' => $fallbackPath, 'mode' => 'contain']
            : null;
    }

    protected function publicStoragePath(mixed $value, string $defaultDirectory = ''): ?string
    {
        $path = PerpustakaanLiterasiMaterial::normalizeImagePath($value, $defaultDirectory);

        if ($path === null || Str::startsWith($path, ['http://', 'https://'])) {
            return null;
        }

        $path = Str::startsWith($path, '/storage/')
            ? Str::after($path, '/storage/')
            : (Str::startsWith($path, 'storage/') ? Str::after($path, 'storage/') : $path);

        $absolutePath = Storage::disk('public')->path(ltrim($path, '/'));

        return is_file($absolutePath) && is_readable($absolutePath) ? $absolutePath : null;
    }

    protected function fallbackUrl(PerpustakaanLiterasiMaterial $material): string
    {
        $configured = $material->imageUrl()
            ?? $this->siteSettings->logoPath()
            ?? $this->siteSettings->defaultOgImage()
            ?? $this->siteSettings->faviconPath();

        if (filled($configured)) {
            $configured = (string) $configured;

            if (Str::startsWith($configured, ['http://', 'https://'])) {
                return $configured;
            }

            if (Str::startsWith($configured, '/')) {
                return url($configured);
            }

            return asset('storage/'.ltrim($configured, '/'));
        }

        return asset('favicon.ico');
    }

    /**
     * @param  'cover'|'contain'  $mode
     */
    protected function generate(string $sourcePath, string $mode, string $targetPath): void
    {
        $contents = @file_get_contents($sourcePath);
        $source = is_string($contents) ? @imagecreatefromstring($contents) : false;

        if ($source === false) {
            return;
        }

        $sourceWidth = imagesx($source);
        $sourceHeight = imagesy($source);
        $canvas = imagecreatetruecolor(self::WIDTH, self::HEIGHT);

        if ($canvas === false || $sourceWidth < 1 || $sourceHeight < 1) {
            imagedestroy($source);

            return;
        }

        $background = imagecolorallocate($canvas, 248, 250, 252);
        imagefill($canvas, 0, 0, $background);

        if ($mode === 'cover') {
            $sourceRatio = $sourceWidth / $sourceHeight;
            $targetRatio = self::WIDTH / self::HEIGHT;

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

            imagecopyresampled(
                $canvas,
                $source,
                0,
                0,
                $sourceX,
                $sourceY,
                self::WIDTH,
                self::HEIGHT,
                $cropWidth,
                $cropHeight,
            );
        } else {
            $scale = min(
                (self::WIDTH * 0.66) / $sourceWidth,
                (self::HEIGHT * 0.66) / $sourceHeight,
            );
            $targetWidth = max(1, (int) round($sourceWidth * $scale));
            $targetHeight = max(1, (int) round($sourceHeight * $scale));

            imagecopyresampled(
                $canvas,
                $source,
                (int) floor((self::WIDTH - $targetWidth) / 2),
                (int) floor((self::HEIGHT - $targetHeight) / 2),
                0,
                0,
                $targetWidth,
                $targetHeight,
                $sourceWidth,
                $sourceHeight,
            );
        }

        $temporaryPath = tempnam(dirname($targetPath), 'literacy-social-');

        if (is_string($temporaryPath) && imagejpeg($canvas, $temporaryPath, 82)) {
            @rename($temporaryPath, $targetPath);
        }

        if (is_string($temporaryPath) && is_file($temporaryPath)) {
            @unlink($temporaryPath);
        }

        imagedestroy($canvas);
        imagedestroy($source);
    }
}
