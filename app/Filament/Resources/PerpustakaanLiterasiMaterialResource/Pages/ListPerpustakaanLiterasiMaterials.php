<?php

namespace App\Filament\Resources\PerpustakaanLiterasiMaterialResource\Pages;

use App\Filament\Resources\PerpustakaanLiterasiMaterialResource;
use App\Filament\Widgets\PerpustakaanLiterasiGlobalAnalytics;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPerpustakaanLiterasiMaterials extends ListRecords
{
    protected static string $resource = PerpustakaanLiterasiMaterialResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            PerpustakaanLiterasiGlobalAnalytics::class,
        ];
    }

    public function getHeaderWidgetsColumns(): int|array
    {
        return 1;
    }
}
