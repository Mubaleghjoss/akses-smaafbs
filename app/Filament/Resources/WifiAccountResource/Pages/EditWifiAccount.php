<?php

namespace App\Filament\Resources\WifiAccountResource\Pages;

use App\Filament\Resources\WifiAccountResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditWifiAccount extends EditRecord
{
    protected static string $resource = WifiAccountResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
