<?php

namespace App\Filament\Resources\SarprasMonthlyAgendaResource\Pages;

use App\Filament\Resources\SarprasMonthlyAgendaResource;
use Filament\Actions;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;

class ListSarprasMonthlyAgendas extends ListRecords
{
    protected static string $resource = SarprasMonthlyAgendaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('exportExcel')
                ->label('Export Excel')
                ->icon('heroicon-o-document-arrow-down')
                ->color('success')
                ->url(route('admin.sarpras-monthly-agendas.export'))
                ->openUrlInNewTab()
                ->visible(fn (): bool => SarprasMonthlyAgendaResource::canViewAny()),
            Action::make('print')
                ->label('Print')
                ->icon('heroicon-o-printer')
                ->color('gray')
                ->url(route('admin.sarpras-monthly-agendas.print'))
                ->openUrlInNewTab()
                ->visible(fn (): bool => SarprasMonthlyAgendaResource::canViewAny()),
            Action::make('pdf')
                ->label('Download PDF')
                ->icon('heroicon-o-document')
                ->color('warning')
                ->url(route('admin.sarpras-monthly-agendas.pdf'))
                ->openUrlInNewTab()
                ->visible(fn (): bool => SarprasMonthlyAgendaResource::canViewAny()),
            Actions\CreateAction::make()
                ->label('Tambah Agenda Sarpras'),
        ];
    }
}
