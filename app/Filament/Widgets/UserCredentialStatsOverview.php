<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\UserResource;
use App\Support\Admin\Dashboard\UserCredentialDashboardSupport;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class UserCredentialStatsOverview extends StatsOverviewWidget
{
    protected int|string|array $columnSpan = 'full';

    protected ?string $pollingInterval = null;

    protected function getStats(): array
    {
        $summary = UserCredentialDashboardSupport::snapshot()['user_summary'];

        return [
            Stat::make('Total Akun Admin', (string) ($summary['total'] ?? 0))
                ->description('Seluruh akun yang bisa login ke sistem')
                ->color('primary')
                ->url(UserResource::getUrl('index')),
            Stat::make('Password Default', (string) ($summary['default_password'] ?? 0))
                ->description('Akun yang masih perlu ganti password')
                ->color('warning')
                ->url(UserResource::getUrl('index', [
                    'password_status' => 'default',
                ])),
            Stat::make('Sudah Diganti', (string) ($summary['changed_password'] ?? 0))
                ->description('Akun yang sudah mengubah password')
                ->color('success')
                ->url(UserResource::getUrl('index', [
                    'password_status' => 'changed',
                ])),
            Stat::make('Tertaut ke Guru', (string) ($summary['linked_guru'] ?? 0))
                ->description('Akun yang terhubung ke data guru/tendik')
                ->color('info')
                ->url(UserResource::getUrl('index', [
                    'linked_status' => 'linked',
                ])),
        ];
    }
}
