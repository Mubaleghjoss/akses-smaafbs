<?php

namespace App\Support\Admin\Dashboard;

use App\Models\UksRecord;
use App\Support\Uks\UksAnthropometrySupport;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema as SchemaFacade;

class UksDashboardSupport
{
    protected static array $memo = [];

    protected static ?bool $uksTableAvailable = null;

    protected static ?bool $dataSiswaTableAvailable = null;

    public static function snapshot(mixed $user): array
    {
        return static::remember(static::scopeKey($user), function () use ($user): array {
            if (! static::hasUksTable()) {
                return [
                    'summary' => [
                        'total' => 0,
                        'bulan_ini' => 0,
                        'punya_ukur' => 0,
                        'kategori_terbanyak' => '-',
                        'active_students' => 0,
                        'unmeasured_this_month' => 0,
                    ],
                    'measurement_averages' => [
                        'berat_badan' => 0.0,
                        'tinggi_badan' => 0.0,
                        'lingkar_kepala' => 0.0,
                    ],
                    'category_rows' => [],
                ];
            }

            $monthStart = now()->startOfMonth()->toDateString();
            $nextMonthStart = now()->startOfMonth()->addMonth()->toDateString();

            $summary = UksRecord::query()
                ->selectRaw('count(*) as total')
                ->selectRaw(
                    "sum(case when tanggal_sakit >= ? and tanggal_sakit < ? and (kategori is null or kategori != ?) then 1 else 0 end) as bulan_ini",
                    [$monthStart, $nextMonthStart, UksRecord::ANTHROPOMETRY_CATEGORY]
                )
                ->first();

            $categoryRows = UksRecord::query()
                ->withoutAnthropometryCategory()
                ->selectRaw('kategori, count(*) as total')
                ->whereNotNull('kategori')
                ->where('kategori', '!=', '')
                ->groupBy('kategori')
                ->orderByDesc('total')
                ->limit(8)
                ->get()
                ->map(fn (UksRecord $row): array => [
                    'kategori' => (string) $row->kategori,
                    'total' => (int) $row->total,
                ])
                ->all();

            $students = static::hasDataSiswaTable()
                ? UksAnthropometrySupport::activeStudentsQuery($user)->get()
                : collect();

            return [
                'summary' => [
                    'total' => (int) ($summary?->total ?? 0),
                    'bulan_ini' => (int) ($summary?->bulan_ini ?? 0),
                    'punya_ukur' => static::measuredStudentsCount($students),
                    'kategori_terbanyak' => $categoryRows[0]['kategori'] ?? '-',
                    'active_students' => (int) $students->count(),
                    'unmeasured_this_month' => UksAnthropometrySupport::unmeasuredThisMonthCount($user),
                ],
                'measurement_averages' => [
                    'berat_badan' => static::averageMeasurement($students, 'latest_berat_badan'),
                    'tinggi_badan' => static::averageMeasurement($students, 'latest_tinggi_badan'),
                    'lingkar_kepala' => static::averageMeasurement($students, 'latest_lingkar_kepala'),
                ],
                'category_rows' => $categoryRows,
            ];
        });
    }

    protected static function measuredStudentsCount(Collection $students): int
    {
        return $students
            ->filter(fn ($student): bool => filled($student->latest_berat_badan)
                || filled($student->latest_tinggi_badan)
                || filled($student->latest_lingkar_kepala))
            ->count();
    }

    protected static function averageMeasurement(Collection $students, string $attribute): float
    {
        $average = $students
            ->pluck($attribute)
            ->filter(fn ($value): bool => $value !== null && $value !== '')
            ->map(fn ($value): float => (float) $value)
            ->avg();

        return round((float) ($average ?? 0), 2);
    }

    protected static function scopeKey(mixed $user): string
    {
        if (! is_object($user)) {
            return 'guest';
        }

        return sha1(json_encode([
            'id' => method_exists($user, 'getAuthIdentifier') ? $user->getAuthIdentifier() : null,
            'boarding_angkatan_scope' => $user->boarding_angkatan_scope ?? null,
            'boarding_rombel_scope' => $user->boarding_rombel_scope ?? null,
            'guru_tendik_id' => $user->guru_tendik_id ?? null,
            'guru_walas_scope' => $user->guru_walas_scope ?? null,
        ]));
    }

    protected static function remember(string $scopeKey, \Closure $callback): mixed
    {
        $memoKey = 'uks:'.$scopeKey;

        if (array_key_exists($memoKey, static::$memo)) {
            return static::$memo[$memoKey];
        }

        return static::$memo[$memoKey] = DashboardCacheSupport::remember('uks', $scopeKey, $callback);
    }

    protected static function hasUksTable(): bool
    {
        return static::$uksTableAvailable ??= SchemaFacade::hasTable('uks_records');
    }

    protected static function hasDataSiswaTable(): bool
    {
        return static::$dataSiswaTableAvailable ??= SchemaFacade::hasTable('data_siswa');
    }
}
