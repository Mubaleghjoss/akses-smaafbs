<?php

namespace App\Filament\Resources\SarprasMonthlyAgendaResource\Pages;

use App\Filament\Resources\SarprasMonthlyAgendaResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSarprasMonthlyAgenda extends EditRecord
{
    protected static string $resource = SarprasMonthlyAgendaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
