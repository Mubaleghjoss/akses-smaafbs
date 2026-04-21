<?php

namespace App\Filament\Resources\PerpustakaanLemariResource\Pages;

use App\Filament\Resources\PerpustakaanLemariResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPerpustakaanLemaris extends ListRecords
{
    protected static string $resource = PerpustakaanLemariResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
