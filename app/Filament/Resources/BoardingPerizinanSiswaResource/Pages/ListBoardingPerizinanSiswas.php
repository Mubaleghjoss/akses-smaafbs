<?php

namespace App\Filament\Resources\BoardingPerizinanSiswaResource\Pages;

use App\Filament\Resources\BoardingPerizinanSiswaResource;
use Filament\Resources\Pages\ListRecords;

class ListBoardingPerizinanSiswas extends ListRecords
{
    protected static string $resource = BoardingPerizinanSiswaResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
