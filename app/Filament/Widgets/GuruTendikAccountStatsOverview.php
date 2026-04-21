<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\GuruTendikResource;
use App\Support\Admin\Dashboard\UserCredentialDashboardSupport;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class GuruTendikAccountStatsOverview extends StatsOverviewWidget
{
    protected int|string|array $columnSpan = 'full';

    protected ?string $pollingInterval = null;

    protected function getStats(): array
    {
        $summary = UserCredentialDashboardSupport::snapshot()['guru_summary'];

        return [
            Stat::make('Punya Akun', (string) ($summary['punya_akun'] ?? 0))
                ->description('Guru/tendik yang sudah punya akun login')
                ->color('primary')
                ->url(GuruTendikResource::getUrl('index', [
                    'account_status' => 'has_account',
                ])),
            Stat::make('Password Default', (string) ($summary['default_password'] ?? 0))
                ->description('Akun guru/tendik yang masih default')
                ->color('warning')
                ->url(GuruTendikResource::getUrl('index', [
                    'account_status' => 'has_account',
                    'password_status' => 'default',
                ])),
            Stat::make('Sudah Diganti', (string) ($summary['changed_password'] ?? 0))
                ->description('Akun guru/tendik yang sudah ganti password')
                ->color('success')
                ->url(GuruTendikResource::getUrl('index', [
                    'account_status' => 'has_account',
                    'password_status' => 'changed',
                ])),
            Stat::make('Belum Ada Akun', (string) ($summary['belum_punya_akun'] ?? 0))
                ->description('Data guru/tendik yang belum punya akun login')
                ->color('danger')
                ->url(GuruTendikResource::getUrl('index', [
                    'account_status' => 'no_account',
                ])),
        ];
    }
}
