<?php

namespace App\Support\DataSiswa;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class DataSiswaImportReviewShareSupport
{
    public const TTL_MINUTES = 30;

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<string, mixed>  $summary
     */
    public static function store(array $rows, array $summary = []): string
    {
        $token = (string) Str::uuid();

        Cache::put(
            self::cacheKey($token),
            [
                'rows' => array_values($rows),
                'summary' => $summary,
                'generated_at' => now()->toIso8601String(),
                'generated_by' => auth()->user()?->name,
            ],
            now()->addMinutes(self::TTL_MINUTES),
        );

        return $token;
    }

    public static function payload(string $token): ?array
    {
        $payload = Cache::get(self::cacheKey($token));

        return is_array($payload) ? $payload : null;
    }

    public static function exportUrl(string $token): string
    {
        return route('admin.data-siswa.import-review.export', $token, false);
    }

    protected static function cacheKey(string $token): string
    {
        return 'data-siswa-import-review:'.$token;
    }
}
