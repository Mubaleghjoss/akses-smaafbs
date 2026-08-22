<?php

namespace App\Models;

use App\Support\Admin\Dashboard\DashboardCacheSupport;
use App\Support\Media\PublicImageOptimizer;
use App\Support\SiteSettings\SiteSettingKeys;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class Pengaturan extends Model
{
    protected static ?bool $tableAvailable = null;

    protected $table = 'pengaturan';

    public $timestamps = false;

    protected $guarded = [];

    protected static function booted(): void
    {
        static::saving(function (self $record): void {
            $key = (string) $record->nama_pengaturan;
            $path = trim((string) $record->nilai_pengaturan);

            if ($path === '' || ! $record->isDirty('nilai_pengaturan')) {
                return;
            }

            try {
                if ($key === SiteSettingKeys::LOGO_PATH) {
                    $record->nilai_pengaturan = app(PublicImageOptimizer::class)
                        ->optimizeUploadedPath($path, 'logo');
                }

                if ($key === SiteSettingKeys::FAVICON_PATH) {
                    $record->nilai_pengaturan = app(PublicImageOptimizer::class)
                        ->optimizeBrandingIcons($path)['favicon_path'];
                }
            } catch (RuntimeException $exception) {
                throw ValidationException::withMessages([
                    'nilai_pengaturan' => 'Aset branding gagal dioptimalkan: '.$exception->getMessage(),
                ]);
            }
        });

        $invalidateDashboardCaches = static function (self $record): void {
            DashboardCacheSupport::forgetModule('google_drive_monitor');
        };

        static::saved($invalidateDashboardCaches);
        static::deleted($invalidateDashboardCaches);
    }

    public static function value(string $key, ?string $default = null): ?string
    {
        if (! static::tableAvailable()) {
            return $default;
        }

        $value = static::query()
            ->where('nama_pengaturan', $key)
            ->value('nilai_pengaturan');

        return filled($value) ? (string) $value : $default;
    }

    /**
     * @param  array<int, string>  $keys
     * @param  array<string, ?string>  $defaults
     * @return array<string, ?string>
     */
    public static function values(array $keys, array $defaults = []): array
    {
        $resolvedDefaults = [];

        foreach ($keys as $key) {
            $resolvedDefaults[$key] = $defaults[$key] ?? null;
        }

        if (! static::tableAvailable()) {
            return $resolvedDefaults;
        }

        $stored = static::query()
            ->whereIn('nama_pengaturan', $keys)
            ->pluck('nilai_pengaturan', 'nama_pengaturan')
            ->all();

        foreach ($stored as $key => $value) {
            $resolvedDefaults[$key] = filled($value) ? (string) $value : ($resolvedDefaults[$key] ?? null);
        }

        return $resolvedDefaults;
    }

    public static function flushRuntimeSchemaCache(): void
    {
        static::$tableAvailable = null;
    }

    protected static function tableAvailable(): bool
    {
        return static::$tableAvailable ??= Schema::hasTable('pengaturan');
    }
}
