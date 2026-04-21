<?php

namespace App\Support\Admin\Dashboard;

use App\Models\DataSiswa;
use App\Models\User;

class DataSiswaDashboardSupport
{
    protected static array $memo = [];

    public static function snapshot(mixed $user): array
    {
        return static::remember(static::scopeKey($user), function () use ($user): array {
            $summary = DataSiswa::applyVisibleScope(DataSiswa::query(), $user)
                ->selectRaw('count(*) as total_siswa')
                ->selectRaw("sum(case when status = 'aktif' then 1 else 0 end) as aktif")
                ->selectRaw("sum(case when status in ('alumni', 'pindah', 'keluar') then 1 else 0 end) as non_aktif")
                ->selectRaw("sum(case when status = 'alumni' then 1 else 0 end) as alumni")
                ->selectRaw("count(distinct case when status = 'aktif' and rombel_saat_ini is not null and rombel_saat_ini != '' then rombel_saat_ini end) as total_rombel")
                ->first();

            $statusCounts = DataSiswa::applyVisibleScope(DataSiswa::query(), $user)
                ->selectRaw('status, count(*) as total')
                ->whereIn('status', ['aktif', 'alumni', 'pindah', 'keluar'])
                ->groupBy('status')
                ->pluck('total', 'status')
                ->map(fn (mixed $count): int => (int) $count)
                ->all();

            $resolvedCategoryExpression = <<<'SQL'
                case
                    when lower(trim(coalesce(kategori_non_aktif, ''))) in ('lulus', 'mutasi', 'mengundurkan_diri', 'wafat', 'lainnya')
                        then lower(trim(kategori_non_aktif))
                    when lower(status) = 'alumni' then 'lulus'
                    when lower(status) = 'pindah' then 'mutasi'
                    when lower(status) = 'keluar' then 'lainnya'
                    else null
                end
            SQL;

            $nonActiveCategoryCounts = DataSiswa::applyVisibleScope(DataSiswa::query(), $user)
                ->whereIn('status', DataSiswa::nonActiveStatuses())
                ->selectRaw("{$resolvedCategoryExpression} as resolved_category")
                ->selectRaw('count(*) as total')
                ->groupBy('resolved_category')
                ->pluck('total', 'resolved_category')
                ->map(fn (mixed $count): int => (int) $count)
                ->all();

            $genderByRombel = DataSiswa::applyVisibleScope(DataSiswa::query(), $user)
                ->select('rombel_saat_ini')
                ->selectRaw("sum(case when jk = 'L' then 1 else 0 end) as total_putra")
                ->selectRaw("sum(case when jk = 'P' then 1 else 0 end) as total_putri")
                ->where('status', 'aktif')
                ->whereNotNull('rombel_saat_ini')
                ->where('rombel_saat_ini', '!=', '')
                ->groupBy('rombel_saat_ini')
                ->orderBy('rombel_saat_ini')
                ->get()
                ->map(fn (DataSiswa $row): array => [
                    'rombel_saat_ini' => (string) $row->rombel_saat_ini,
                    'total_putra' => (int) $row->total_putra,
                    'total_putri' => (int) $row->total_putri,
                ])
                ->all();

            return [
                'summary' => [
                    'total_siswa' => (int) ($summary?->total_siswa ?? 0),
                    'aktif' => (int) ($summary?->aktif ?? 0),
                    'non_aktif' => (int) ($summary?->non_aktif ?? 0),
                    'alumni' => (int) ($summary?->alumni ?? 0),
                    'total_rombel' => (int) ($summary?->total_rombel ?? 0),
                ],
                'status_counts' => $statusCounts,
                'non_active_category_counts' => $nonActiveCategoryCounts,
                'gender_by_rombel' => $genderByRombel,
            ];
        });
    }

    protected static function scopeKey(mixed $user): string
    {
        if (! $user instanceof User) {
            return 'guest';
        }

        return sha1(json_encode([
            'id' => $user->getAuthIdentifier(),
            'role' => $user->role ?? null,
            'boarding_angkatan_scope' => $user->boarding_angkatan_scope ?? null,
            'boarding_rombel_scope' => $user->boarding_rombel_scope ?? null,
            'guru_tendik_id' => $user->guru_tendik_id ?? null,
            'guru_walas_scope' => $user->guru_walas_scope ?? null,
        ]));
    }

    protected static function remember(string $scopeKey, \Closure $callback): mixed
    {
        $memoKey = 'data_siswa:'.$scopeKey;

        if (array_key_exists($memoKey, static::$memo)) {
            return static::$memo[$memoKey];
        }

        return static::$memo[$memoKey] = DashboardCacheSupport::remember('data_siswa', $scopeKey, $callback);
    }
}
