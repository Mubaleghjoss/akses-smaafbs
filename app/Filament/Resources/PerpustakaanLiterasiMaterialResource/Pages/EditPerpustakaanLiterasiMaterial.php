<?php

namespace App\Filament\Resources\PerpustakaanLiterasiMaterialResource\Pages;

use App\Filament\Resources\PerpustakaanLiterasiMaterialResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPerpustakaanLiterasiMaterial extends EditRecord
{
    protected static string $resource = PerpustakaanLiterasiMaterialResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
        ];
    }
}
