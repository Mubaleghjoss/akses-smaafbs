<?php

namespace App\Filament\Widgets;

use App\Models\UksRecord;
use App\Support\Admin\Dashboard\UksDashboardSupport;
use App\Support\Uks\UksAnthropometrySupport;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class UksStatsOverview extends StatsOverviewWidget
{
    protected int|string|array $columnSpan = 'full';

    protected ?string $pollingInterval = null;

    protected function getStats(): array
    {
        $summary = UksDashboardSupport::snapshot(auth()->user())['summary'];
        $total = (int) ($summary['total'] ?? 0);
        $bulanIni = (int) ($summary['bulan_ini'] ?? 0);
        $punyaUkur = (int) ($summary['punya_ukur'] ?? 0);
        $kategoriTerbanyak = (string) ($summary['kategori_terbanyak'] ?? '-');

        return [
            Stat::make('Total Kunjungan UKS', (string) $total)
                ->description('Riwayat kunjungan dan pemeriksaan UKS yang tersimpan')
                ->color('danger'),
            Stat::make('Kasus Sakit Bulan Ini', (string) $bulanIni)
                ->description('Kunjungan sakit pada bulan berjalan')
                ->color('warning'),
            Stat::make('Murid Punya Antropometri', (string) $punyaUkur)
                ->description('Murid aktif yang sudah punya pengukuran terbaru')
                ->color('success'),
            Stat::make('Kategori Sakit Terbanyak', $kategoriTerbanyak)
                ->description('Keluhan atau kategori sakit yang paling sering tercatat')
                ->color('info'),
        ];
    }
}
