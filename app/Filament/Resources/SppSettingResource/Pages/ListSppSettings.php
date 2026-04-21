<?php

namespace App\Filament\Resources\SppSettingResource\Pages;

use App\Filament\Resources\SppSettingResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSppSettings extends ListRecords
{
    protected static string $resource = SppSettingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
