<?php

namespace App\Support\Admin\Dashboard;

use App\Models\Proker;
use App\Models\ProkerBidang;
use App\Models\ProkerIndikator;
use App\Models\ProkerUpdate;

class ProkerDashboardSupport
{
    protected static array $memo = [];

    public static function snapshot(): array
    {
        return static::remember('snapshot', function (): array {
            $monthStart = now()->startOfMonth()->toDateString();
            $nextMonthStart = now()->startOfMonth()->addMonth()->toDateString();

            $summary = Proker::query()
                ->selectRaw('count(*) as total_proker')
                ->selectRaw("sum(case when status = 'terkendala' then 1 else 0 end) as terkendala")
                ->selectSub(ProkerIndikator::query()->selectRaw('count(*)'), 'total_indikator')
                ->selectSub(
                    ProkerIndikator::query()->selectRaw('coalesce(sum(case when is_checked = 1 then 1 else 0 end), 0)'),
                    'indikator_selesai'
                )
                ->selectSub(
                    ProkerUpdate::query()
                        ->selectRaw('count(*)')
                        ->where('tanggal_update', '>=', $monthStart)
                        ->where('tanggal_update', '<', $nextMonthStart),
                    'update_bulan_ini'
                )
                ->first();

            $statusCounts = Proker::query()
                ->selectRaw('status, count(*) as total')
                ->whereIn('status', ['draft', 'berjalan', 'terkendala', 'selesai'])
                ->groupBy('status')
                ->pluck('total', 'status')
                ->map(fn (mixed $count): int => (int) $count)
                ->all();

            $indicatorByBidang = ProkerBidang::query()
                ->leftJoin('prokers', 'proker_bidangs.id', '=', 'prokers.bidang_id')
                ->leftJoin('proker_indikators', 'prokers.id', '=', 'proker_indikators.proker_id')
                ->select('proker_bidangs.id', 'proker_bidangs.nama')
                ->selectRaw('count(proker_indikators.id) as total_indikator')
                ->selectRaw('sum(case when proker_indikators.is_checked = 1 then 1 else 0 end) as indikator_selesai')
                ->groupBy('proker_bidangs.id', 'proker_bidangs.nama')
                ->orderBy('proker_bidangs.nama')
                ->get()
                ->map(fn (ProkerBidang $bidang): array => [
                    'bidang_id' => (int) $bidang->id,
                    'label' => (string) $bidang->nama,
                    'rate' => (int) (
                        ((int) ($bidang->total_indikator ?? 0)) > 0
                            ? round((((int) ($bidang->indikator_selesai ?? 0)) / ((int) ($bidang->total_indikator ?? 0))) * 100)
                            : 0
                    ),
                ])
                ->all();

            return [
                'summary' => [
                    'total_proker' => (int) ($summary?->total_proker ?? 0),
                    'terkendala' => (int) ($summary?->terkendala ?? 0),
                    'total_indikator' => (int) ($summary?->total_indikator ?? 0),
                    'indikator_selesai' => (int) ($summary?->indikator_selesai ?? 0),
                    'update_bulan_ini' => (int) ($summary?->update_bulan_ini ?? 0),
                ],
                'status_counts' => $statusCounts,
                'indicator_by_bidang' => $indicatorByBidang,
            ];
        });
    }

    protected static function remember(string $suffix, \Closure $callback): mixed
    {
        $memoKey = 'proker:'.$suffix;

        if (array_key_exists($memoKey, static::$memo)) {
            return static::$memo[$memoKey];
        }

        return static::$memo[$memoKey] = DashboardCacheSupport::remember('proker', $suffix, $callback);
    }
}
