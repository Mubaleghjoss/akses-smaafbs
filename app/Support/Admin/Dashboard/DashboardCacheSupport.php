<?php

namespace App\Support\Admin\Dashboard;

use Closure;
use Illuminate\Support\Facades\Cache;

class DashboardCacheSupport
{
    public static function remember(string $module, string $suffix, Closure $callback, int $seconds = 60): mixed
    {
        $version = static::version($module);

        return Cache::remember(
            "admin-dashboard:{$module}:v{$version}:{$suffix}",
            now()->addSeconds($seconds),
            $callback,
        );
    }

    public static function forgetModule(string $module): void
    {
        Cache::increment(static::versionKey($module));
    }

    protected static function version(string $module): int
    {
        return (int) Cache::rememberForever(static::versionKey($module), fn (): int => 1);
    }

    protected static function versionKey(string $module): string
    {
        return "admin-dashboard:{$module}:version";
    }
}
