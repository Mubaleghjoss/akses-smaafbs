<?php

namespace App\Filament\Resources\ProkerResource\Pages;

use App\Filament\Pages\DashboardProker;
use App\Filament\Resources\ProkerResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListProkers extends ListRecords
{
    protected static string $resource = ProkerResource::class;

    protected function getHeaderActions(): array
    {
        $downloadActions = collect(ProkerResource::getPeriodYearOptions())
            ->map(
                fn (string $label, string $year): Actions\Action => Actions\Action::make("downloadExcel{$year}")
                    ->label($label)
                    ->icon('heroicon-o-arrow-down-tray')
                    ->url(route('admin.prokers.export', ['periode_tahun' => (int) $year]))
                    ->openUrlInNewTab()
            )
            ->values()
            ->all();

        return [
            Actions\Action::make('dashboard')
                ->label('Dashboard Proker')
                ->icon('heroicon-o-chart-bar-square')
                ->url(DashboardProker::getUrl()),
            $downloadActions === []
                ? Actions\Action::make('downloadExcel')
                    ->label('Download Excel')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('gray')
                    ->disabled()
                : Actions\ActionGroup::make($downloadActions)
                    ->label('Download Excel')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('success')
                    ->button()
                    ->dropdownPlacement('bottom-start'),
            Actions\CreateAction::make(),
        ];
    }
}
