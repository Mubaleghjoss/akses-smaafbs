<?php

namespace App\Filament\Resources\BoardingKonselingMtResource\Pages;

use App\Filament\Resources\BoardingKonselingMtResource;
use Filament\Resources\Pages\ListRecords;

class ListBoardingKonselingMts extends ListRecords
{
    protected static string $resource = BoardingKonselingMtResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
