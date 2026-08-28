<?php

namespace App\Filament\Resources\BkKasusResource\Pages;

use App\Filament\Resources\BkKasusResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListBkKasus extends ListRecords
{
    protected static string $resource = BkKasusResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('rekapSigap')
                ->label('Rekap SIGAP')
                ->icon('heroicon-o-chart-bar')
                ->color('info')
                ->url(fn (): string => \App\Filament\Pages\Bk\RekapSigapPage::getUrl())
                ->visible(fn (): bool => BkKasusResource::canViewAny()),
            Actions\CreateAction::make()
                ->label('Tambah Laporan SIGAP'),
        ];
    }
}
