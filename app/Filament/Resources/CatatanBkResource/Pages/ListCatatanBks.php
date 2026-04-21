<?php

namespace App\Filament\Resources\CatatanBkResource\Pages;

use App\Filament\Resources\CatatanBkResource;
use Filament\Resources\Pages\ListRecords;

class ListCatatanBks extends ListRecords
{
    protected static string $resource = CatatanBkResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
