<?php

namespace App\Filament\Resources\BerkasSiswaResource\Pages;

use App\Filament\Resources\BerkasSiswaResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListBerkasSiswas extends ListRecords
{
    protected static string $resource = BerkasSiswaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
