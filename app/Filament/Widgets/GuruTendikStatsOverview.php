<?php

namespace App\Filament\Widgets;

use App\Models\GuruTendik;
use App\Support\Admin\Dashboard\GuruTendikDashboardSupport;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class GuruTendikStatsOverview extends StatsOverviewWidget
{
    protected int|string|array $columnSpan = 'full';

    protected ?string $pollingInterval = null;

    protected function getStats(): array
    {
        $summary = GuruTendikDashboardSupport::snapshot(auth()->user())['summary'];
        $total = (int) ($summary['total'] ?? 0);
        $guru = (int) ($summary['guru'] ?? 0);
        $tendik = (int) ($summary['tendik'] ?? 0);
        $aktif = (int) ($summary['aktif'] ?? 0);
        $punyaTugasAktif = (int) ($summary['punya_tugas_aktif'] ?? 0);

        return [
            Stat::make('Total Guru/Tendik', (string) $total)
                ->description('Seluruh data yang tercatat di sistem')
                ->color('primary'),
            Stat::make('Guru', (string) $guru)
                ->description('Jenis PTK Guru')
                ->color('success'),
            Stat::make('Tendik', (string) $tendik)
                ->description('Jenis PTK Tendik')
                ->color('info'),
            Stat::make('Status Aktif', (string) $aktif)
                ->description('Data dengan status aktif')
                ->color('warning'),
            Stat::make('Punya Tugas Aktif', (string) $punyaTugasAktif)
                ->description('Guru/Tendik dengan tugas tambahan yang masih berjalan')
                ->color('danger'),
        ];
    }
}
