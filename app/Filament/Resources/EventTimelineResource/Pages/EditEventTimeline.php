<?php

namespace App\Filament\Resources\EventTimelineResource\Pages;

use App\Filament\Resources\EventTimelineResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditEventTimeline extends EditRecord
{
    protected static string $resource = EventTimelineResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
