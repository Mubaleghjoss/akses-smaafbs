<?php

namespace App\Filament\Resources\SarprasBospInventoryResource\Pages;

use App\Filament\Resources\SarprasBospInventoryResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSarprasBospInventory extends EditRecord
{
    protected static string $resource = SarprasBospInventoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
