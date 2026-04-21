<?php

namespace App\Filament\Resources\SurveiResource\Pages;

use App\Filament\Resources\SurveiResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewSurvei extends ViewRecord
{
    protected static string $resource = SurveiResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
