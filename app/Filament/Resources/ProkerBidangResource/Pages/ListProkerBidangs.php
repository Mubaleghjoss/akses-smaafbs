<?php

namespace App\Filament\Resources\ProkerBidangResource\Pages;

use App\Filament\Pages\DashboardProker;
use App\Filament\Resources\ProkerBidangResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListProkerBidangs extends ListRecords
{
    protected static string $resource = ProkerBidangResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('dashboard')
                ->label('Dashboard Proker')
                ->icon('heroicon-o-chart-bar-square')
                ->url(DashboardProker::getUrl()),
            Actions\CreateAction::make(),
        ];
    }
}
