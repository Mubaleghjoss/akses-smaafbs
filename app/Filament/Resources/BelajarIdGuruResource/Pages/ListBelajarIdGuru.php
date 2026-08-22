<?php

namespace App\Filament\Resources\BelajarIdGuruResource\Pages;

use App\Filament\Resources\BelajarIdGuruResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListBelajarIdGuru extends ListRecords
{
    protected static string $resource = BelajarIdGuruResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
