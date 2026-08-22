<?php

namespace App\Filament\Resources\WifiGuruResource\Pages;

use App\Filament\Resources\WifiGuruResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListWifiGuru extends ListRecords
{
    protected static string $resource = WifiGuruResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
