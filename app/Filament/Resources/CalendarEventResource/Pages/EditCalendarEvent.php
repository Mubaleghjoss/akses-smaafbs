<?php

namespace App\Filament\Resources\CalendarEventResource\Pages;

use App\Filament\Resources\CalendarEventResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCalendarEvent extends EditRecord
{
    protected static string $resource = CalendarEventResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
