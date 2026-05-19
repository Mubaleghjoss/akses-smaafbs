<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\GuruTendikResource;
use App\Support\Admin\Dashboard\GuruTendikDashboardSupport;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class GuruTendikStatsOverview extends StatsOverviewWidget
{
    protected int|string|array $columnSpan = 'full';

    protected ?string $pollingInterval = null;

    protected function getStats(): array
    {
        $snapshot = GuruTendikDashboardSupport::snapshot(auth()->user());
        $summary = $snapshot['summary'];
        $genderCounts = $snapshot['gender_counts'] ?? [];
        $total = (int) ($summary['total'] ?? 0);
        $guru = (int) ($summary['guru'] ?? 0);
        $tendik = (int) ($summary['tendik'] ?? 0);
        $pamong = (int) ($summary['pamong'] ?? 0);
        $male = (int) ($genderCounts['L'] ?? 0);
        $female = (int) ($genderCounts['P'] ?? 0);
        $aktif = (int) ($summary['aktif'] ?? 0);
        $punyaTugasAktif = (int) ($summary['punya_tugas_aktif'] ?? 0);

        return [
            Stat::make('Total Guru/Tendik', (string) $total)
                ->description('Seluruh data yang tercatat di sistem')
                ->color('primary'),
            Stat::make('Guru', (string) $guru)
                ->description('Jenis PTK Guru')
                ->color('success')
                ->url(GuruTendikResource::getUrl('index', [
                    'chart_jenis_ptk' => 'Guru',
                ])),
            Stat::make('Tendik', (string) $tendik)
                ->description('Jenis PTK Tendik')
                ->color('info')
                ->url(GuruTendikResource::getUrl('index', [
                    'chart_jenis_ptk' => 'Tendik',
                ])),
            Stat::make('Pamong', (string) $pamong)
                ->description('Jenis PTK Pamong')
                ->color('success')
                ->url(GuruTendikResource::getUrl('index', [
                    'chart_jenis_ptk' => 'Pamong',
                ])),
            Stat::make('Laki-laki', (string) $male)
                ->description('Guru/tendik/pamong laki-laki')
                ->color('gray')
                ->url(GuruTendikResource::getUrl('index', [
                    'chart_jk' => 'L',
                ])),
            Stat::make('Perempuan', (string) $female)
                ->description('Guru/tendik/pamong perempuan')
                ->color('gray')
                ->url(GuruTendikResource::getUrl('index', [
                    'chart_jk' => 'P',
                ])),
            Stat::make('Status Aktif', (string) $aktif)
                ->description('Data dengan status aktif')
                ->color('warning'),
            Stat::make('Punya Tugas Aktif', (string) $punyaTugasAktif)
                ->description('Guru/Tendik dengan tugas tambahan yang masih berjalan')
                ->color('danger'),
        ];
    }
}
