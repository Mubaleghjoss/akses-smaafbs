<?php

namespace App\Filament\Resources\ProfilSekolahResource\Pages;

use App\Filament\Resources\ProfilSekolahResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListProfilSekolahs extends ListRecords
{
    protected static string $resource = ProfilSekolahResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
