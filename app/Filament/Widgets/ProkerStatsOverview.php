<?php

namespace App\Filament\Widgets;

use App\Models\Proker;
use App\Support\Admin\Dashboard\ProkerDashboardSupport;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ProkerStatsOverview extends StatsOverviewWidget
{
    protected int|string|array $columnSpan = 'full';

    protected ?string $pollingInterval = null;

    protected function getStats(): array
    {
        $summary = ProkerDashboardSupport::snapshot()['summary'];
        $totalProker = (int) ($summary['total_proker'] ?? 0);
        $totalIndikator = (int) ($summary['total_indikator'] ?? 0);
        $indikatorSelesai = (int) ($summary['indikator_selesai'] ?? 0);
        $terkendala = (int) ($summary['terkendala'] ?? 0);
        $updateBulanIni = (int) ($summary['update_bulan_ini'] ?? 0);
        $persenIndikator = $totalIndikator > 0 ? (int) round(($indikatorSelesai / $totalIndikator) * 100) : 0;

        return [
            Stat::make('Total Proker', (string) $totalProker)
                ->description('Seluruh program kerja yang tercatat')
                ->color('primary'),
            Stat::make('Indikator Tercapai', "{$persenIndikator}%")
                ->description("{$indikatorSelesai} dari {$totalIndikator} indikator")
                ->color($persenIndikator >= 75 ? 'success' : ($persenIndikator > 40 ? 'warning' : 'danger')),
            Stat::make('Proker Terkendala', (string) $terkendala)
                ->description('Memerlukan evaluasi dan tindak lanjut')
                ->color($terkendala > 0 ? 'danger' : 'success'),
            Stat::make('Update Bulan Ini', (string) $updateBulanIni)
                ->description('Histori monitoring bulan berjalan')
                ->color('info'),
        ];
    }
}
