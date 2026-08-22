<?php

namespace App\Filament\Resources\WifiGuruResource\Pages;

use App\Filament\Resources\WifiGuruResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditWifiGuru extends EditRecord
{
    protected static string $resource = WifiGuruResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
