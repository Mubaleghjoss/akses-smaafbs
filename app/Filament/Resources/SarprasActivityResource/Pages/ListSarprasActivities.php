<?php

namespace App\Filament\Resources\SarprasActivityResource\Pages;

use App\Filament\Resources\SarprasActivityResource;
use Filament\Actions;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;

class ListSarprasActivities extends ListRecords
{
    protected static string $resource = SarprasActivityResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('exportExcel')
                ->label('Export Excel')
                ->icon('heroicon-o-document-arrow-down')
                ->color('success')
                ->url(route('admin.sarpras-activities.export'))
                ->openUrlInNewTab()
                ->visible(fn (): bool => SarprasActivityResource::canViewAny()),
            Action::make('print')
                ->label('Print')
                ->icon('heroicon-o-printer')
                ->color('gray')
                ->url(route('admin.sarpras-activities.print'))
                ->openUrlInNewTab()
                ->visible(fn (): bool => SarprasActivityResource::canViewAny()),
            Action::make('pdf')
                ->label('Download PDF')
                ->icon('heroicon-o-document')
                ->color('warning')
                ->url(route('admin.sarpras-activities.pdf'))
                ->openUrlInNewTab()
                ->visible(fn (): bool => SarprasActivityResource::canViewAny()),
            Actions\CreateAction::make()
                ->label('Tambah Kegiatan Sarpras'),
        ];
    }
}
