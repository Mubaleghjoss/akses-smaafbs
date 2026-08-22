<?php

namespace App\Filament\Resources\WifiAccountResource\Pages;

use App\Filament\Resources\WifiAccountResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListWifiAccounts extends ListRecords
{
    protected static string $resource = WifiAccountResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
