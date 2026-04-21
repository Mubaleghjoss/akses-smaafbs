<?php

namespace App\Support\Admin\Dashboard;

use App\Models\GuruTendik;
use App\Models\GuruTendikTugasTambahan;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\JoinClause;

class GuruTendikDashboardSupport
{
    protected static array $memo = [];

    public static function snapshot(mixed $user): array
    {
        return static::remember(static::scopeKey($user), function () use ($user): array {
            $today = now()->toDateString();

            $activeTugasSubquery = GuruTendikTugasTambahan::query()
                ->select('guru_tendik_id')
                ->whereDate('tmt', '<=', $today)
                ->where(function (Builder $query) use ($today): void {
                    $query->whereNull('tst')->orWhereDate('tst', '>=', $today);
                })
                ->distinct();

            $summary = GuruTendik::visibleToUser(GuruTendik::query(), $user)
                ->leftJoinSub($activeTugasSubquery, 'active_tugas', function (JoinClause $join): void {
                    $join->on('guru_tendik.id', '=', 'active_tugas.guru_tendik_id');
                })
                ->selectRaw('count(distinct guru_tendik.id) as total')
                ->selectRaw("sum(case when guru_tendik.jenis_ptk = 'Guru' then 1 else 0 end) as guru")
                ->selectRaw("sum(case when guru_tendik.jenis_ptk = 'Tendik' then 1 else 0 end) as tendik")
                ->selectRaw("sum(case when guru_tendik.status = 'aktif' then 1 else 0 end) as aktif")
                ->selectRaw('count(distinct active_tugas.guru_tendik_id) as punya_tugas_aktif')
                ->first();

            $genderCounts = GuruTendik::visibleToUser(GuruTendik::query(), $user)
                ->selectRaw('jk, count(*) as total')
                ->whereIn('jk', ['L', 'P'])
                ->groupBy('jk')
                ->pluck('total', 'jk')
                ->map(fn (mixed $count): int => (int) $count)
                ->all();

            return [
                'summary' => [
                    'total' => (int) ($summary?->total ?? 0),
                    'guru' => (int) ($summary?->guru ?? 0),
                    'tendik' => (int) ($summary?->tendik ?? 0),
                    'aktif' => (int) ($summary?->aktif ?? 0),
                    'punya_tugas_aktif' => (int) ($summary?->punya_tugas_aktif ?? 0),
                ],
                'jenis_ptk_counts' => [
                    'Guru' => (int) ($summary?->guru ?? 0),
                    'Tendik' => (int) ($summary?->tendik ?? 0),
                ],
                'gender_counts' => $genderCounts,
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
            'guru_tendik_id' => $user->guru_tendik_id ?? null,
        ]));
    }

    protected static function remember(string $scopeKey, \Closure $callback): mixed
    {
        $memoKey = 'guru_tendik:'.$scopeKey;

        if (array_key_exists($memoKey, static::$memo)) {
            return static::$memo[$memoKey];
        }

        return static::$memo[$memoKey] = DashboardCacheSupport::remember('guru_tendik', $scopeKey, $callback);
    }
}
