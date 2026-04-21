<?php

namespace App\Filament\Resources\BoardingKeuanganSiswaResource\Pages;

use App\Filament\Resources\BoardingKeuanganSiswaResource;
use Filament\Resources\Pages\ListRecords;

class ListBoardingKeuanganSiswas extends ListRecords
{
    protected static string $resource = BoardingKeuanganSiswaResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
