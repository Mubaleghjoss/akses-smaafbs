<?php

namespace App\Filament\Resources\PerpustakaanLiterasiMaterialResource\Pages;

use App\Filament\Resources\PerpustakaanLiterasiMaterialResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewPerpustakaanLiterasiMaterial extends ViewRecord
{
    protected static string $resource = PerpustakaanLiterasiMaterialResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
