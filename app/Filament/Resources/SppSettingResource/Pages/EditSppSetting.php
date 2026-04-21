<?php

namespace App\Filament\Resources\SppSettingResource\Pages;

use App\Filament\Resources\SppSettingResource;
use Filament\Resources\Pages\EditRecord;

class EditSppSetting extends EditRecord
{
    protected static string $resource = SppSettingResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
