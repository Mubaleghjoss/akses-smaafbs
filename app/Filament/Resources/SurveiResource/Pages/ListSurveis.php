<?php

namespace App\Filament\Resources\SurveiResource\Pages;

use App\Filament\Resources\SurveiResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSurveis extends ListRecords
{
    protected static string $resource = SurveiResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
