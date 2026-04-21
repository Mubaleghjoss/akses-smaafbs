<?php

namespace App\Filament\Resources\PerpustakaanBukuResource\Pages;

use App\Filament\Resources\PerpustakaanBukuResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPerpustakaanBukus extends ListRecords
{
    protected static string $resource = PerpustakaanBukuResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
