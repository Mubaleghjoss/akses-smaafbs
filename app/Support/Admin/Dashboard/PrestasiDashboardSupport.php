<?php

namespace App\Support\Admin\Dashboard;

use App\Models\Prestasi;
use App\Models\User;
use Illuminate\Support\Facades\Schema as SchemaFacade;

class PrestasiDashboardSupport
{
    protected static array $memo = [];

    protected static ?bool $prestasiTableAvailable = null;

    public static function snapshot(mixed $user): array
    {
        return static::remember(static::scopeKey($user), function () use ($user): array {
            if (! static::hasPrestasiTable()) {
                return [
                    'summary' => [
                        'total_prestasi' => 0,
                        'siswa_berprestasi' => 0,
                        'juara_satu' => 0,
                        'tahun_berjalan' => 0,
                    ],
                    'by_rombel' => [],
                ];
            }

            $yearStart = now()->startOfYear()->toDateString();
            $nextYearStart = now()->startOfYear()->addYear()->toDateString();

            $summary = Prestasi::query()
                ->visibleToUser($user)
                ->selectRaw('count(*) as total_prestasi')
                ->selectRaw('count(distinct siswa_id) as siswa_berprestasi')
                ->selectRaw("sum(case when juara like '%1%' then 1 else 0 end) as juara_satu")
                ->selectRaw(
                    "sum(case when tanggal_prestasi >= ? and tanggal_prestasi < ? then 1 else 0 end) as tahun_berjalan",
                    [$yearStart, $nextYearStart]
                )
                ->first();

            $byRombel = Prestasi::query()
                ->visibleToUser($user)
                ->join('data_siswa', 'data_siswa.id', '=', 'prestasis.siswa_id')
                ->select('data_siswa.rombel_saat_ini')
                ->selectRaw('count(prestasis.id) as total_prestasi')
                ->whereNotNull('data_siswa.rombel_saat_ini')
                ->where('data_siswa.rombel_saat_ini', '!=', '')
                ->groupBy('data_siswa.rombel_saat_ini')
                ->orderBy('data_siswa.rombel_saat_ini')
                ->get()
                ->map(fn ($row): array => [
                    'rombel_saat_ini' => (string) $row->rombel_saat_ini,
                    'total_prestasi' => (int) $row->total_prestasi,
                ])
                ->all();

            return [
                'summary' => [
                    'total_prestasi' => (int) ($summary?->total_prestasi ?? 0),
                    'siswa_berprestasi' => (int) ($summary?->siswa_berprestasi ?? 0),
                    'juara_satu' => (int) ($summary?->juara_satu ?? 0),
                    'tahun_berjalan' => (int) ($summary?->tahun_berjalan ?? 0),
                ],
                'by_rombel' => $byRombel,
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
        $memoKey = 'prestasi:'.$scopeKey;

        if (array_key_exists($memoKey, static::$memo)) {
            return static::$memo[$memoKey];
        }

        return static::$memo[$memoKey] = DashboardCacheSupport::remember('prestasi', $scopeKey, $callback);
    }

    protected static function hasPrestasiTable(): bool
    {
        return static::$prestasiTableAvailable ??= SchemaFacade::hasTable('prestasis');
    }
}
