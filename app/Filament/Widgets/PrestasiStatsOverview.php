<?php

namespace App\Filament\Widgets;

use App\Models\Prestasi;
use App\Support\Admin\Dashboard\PrestasiDashboardSupport;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class PrestasiStatsOverview extends StatsOverviewWidget
{
    protected int|string|array $columnSpan = 'full';

    protected ?string $pollingInterval = null;

    protected function getStats(): array
    {
        $summary = PrestasiDashboardSupport::snapshot(auth()->user())['summary'];
        $totalPrestasi = (int) ($summary['total_prestasi'] ?? 0);
        $siswaBerprestasi = (int) ($summary['siswa_berprestasi'] ?? 0);
        $juaraSatu = (int) ($summary['juara_satu'] ?? 0);
        $tahunBerjalan = (int) ($summary['tahun_berjalan'] ?? 0);

        return [
            Stat::make('Total Prestasi', (string) $totalPrestasi)
                ->description('Seluruh data prestasi siswa yang tercatat')
                ->color('warning'),
            Stat::make('Siswa Berprestasi', (string) $siswaBerprestasi)
                ->description('Jumlah siswa yang sudah memiliki minimal satu catatan prestasi')
                ->color('success'),
            Stat::make('Juara 1 / Setara', (string) $juaraSatu)
                ->description('Data prestasi dengan capaian juara utama atau setara')
                ->color('primary'),
            Stat::make('Prestasi Tahun Ini', (string) $tahunBerjalan)
                ->description('Data prestasi yang tanggal kegiatannya berada di tahun berjalan')
                ->color('info'),
        ];
    }
}
