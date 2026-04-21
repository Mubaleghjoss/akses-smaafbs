<?php

namespace App\Filament\Resources\PerpustakaanKategoriResource\Pages;

use App\Filament\Resources\PerpustakaanKategoriResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPerpustakaanKategoris extends ListRecords
{
    protected static string $resource = PerpustakaanKategoriResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
