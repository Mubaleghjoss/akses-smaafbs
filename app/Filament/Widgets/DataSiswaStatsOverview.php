<?php

namespace App\Filament\Widgets;

use App\Models\DataSiswa;
use App\Support\Admin\Dashboard\DataSiswaDashboardSupport;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class DataSiswaStatsOverview extends StatsOverviewWidget
{
    protected int|string|array $columnSpan = 'full';

    protected ?string $pollingInterval = null;

    protected function getStats(): array
    {
        $summary = DataSiswaDashboardSupport::snapshot(auth()->user())['summary'];
        $totalSiswa = (int) ($summary['total_siswa'] ?? 0);
        $aktif = (int) ($summary['aktif'] ?? 0);
        $nonAktif = (int) ($summary['non_aktif'] ?? 0);
        $alumni = (int) ($summary['alumni'] ?? 0);
        $totalRombel = (int) ($summary['total_rombel'] ?? 0);

        return [
            Stat::make('Total Siswa', (string) $totalSiswa)
                ->description('Seluruh murid yang tercatat di sistem')
                ->color('primary'),
            Stat::make('Siswa Aktif', (string) $aktif)
                ->description('Data siswa aktif saat ini')
                ->color('success'),
            Stat::make('Siswa Non Aktif', (string) $nonAktif)
                ->description('Alumni, mutasi, keluar, dan status non aktif lain')
                ->color('danger'),
            Stat::make('Alumni', (string) $alumni)
                ->description('Data alumni yang masih terarsip')
                ->color('warning'),
            Stat::make('Rombel Terdaftar', (string) $totalRombel)
                ->description('Total rombel / kelompok yang terbaca')
                ->color('info'),
        ];
    }
}
