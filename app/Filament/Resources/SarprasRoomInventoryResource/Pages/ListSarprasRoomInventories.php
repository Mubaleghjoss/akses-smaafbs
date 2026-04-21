<?php

namespace App\Filament\Resources\SarprasRoomInventoryResource\Pages;

use App\Filament\Resources\SarprasRoomInventoryResource;
use Filament\Actions;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;

class ListSarprasRoomInventories extends ListRecords
{
    protected static string $resource = SarprasRoomInventoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('exportExcelAll')
                ->label('Export Excel Semua Ruangan')
                ->icon('heroicon-o-document-arrow-down')
                ->color('success')
                ->url(route('admin.sarpras-room-inventories.export-all'))
                ->openUrlInNewTab()
                ->visible(fn (): bool => SarprasRoomInventoryResource::canViewAny()),
            Actions\CreateAction::make()
                ->label('Tambah Inventaris Ruangan'),
        ];
    }
}
