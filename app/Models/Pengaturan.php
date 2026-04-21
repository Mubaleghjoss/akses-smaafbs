<?php

namespace App\Models;

use App\Support\Admin\Dashboard\DashboardCacheSupport;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class Pengaturan extends Model
{
    protected static ?bool $tableAvailable = null;

    protected $table = 'pengaturan';

    public $timestamps = false;

    protected $guarded = [];

    protected static function booted(): void
    {
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

    protected static function tableAvailable(): bool
    {
        return static::$tableAvailable ??= Schema::hasTable('pengaturan');
    }
}
