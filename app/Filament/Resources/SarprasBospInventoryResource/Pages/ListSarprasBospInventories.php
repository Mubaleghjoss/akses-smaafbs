<?php

namespace App\Filament\Resources\SarprasBospInventoryResource\Pages;

use App\Filament\Resources\SarprasBospInventoryResource;
use Filament\Actions;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;

class ListSarprasBospInventories extends ListRecords
{
    protected static string $resource = SarprasBospInventoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('exportExcel')
                ->label('Export Excel')
                ->icon('heroicon-o-document-arrow-down')
                ->color('success')
                ->url(route('admin.sarpras-bosp-inventories.export'))
                ->openUrlInNewTab()
                ->visible(fn (): bool => SarprasBospInventoryResource::canViewAny()),
            Action::make('print')
                ->label('Print')
                ->icon('heroicon-o-printer')
                ->color('gray')
                ->url(route('admin.sarpras-bosp-inventories.print'))
                ->openUrlInNewTab()
                ->visible(fn (): bool => SarprasBospInventoryResource::canViewAny()),
            Action::make('pdf')
                ->label('Download PDF')
                ->icon('heroicon-o-document')
                ->color('warning')
                ->url(route('admin.sarpras-bosp-inventories.pdf'))
                ->openUrlInNewTab()
                ->visible(fn (): bool => SarprasBospInventoryResource::canViewAny()),
            Actions\CreateAction::make()
                ->label('Tambah Inventaris BOSP'),
        ];
    }
}
