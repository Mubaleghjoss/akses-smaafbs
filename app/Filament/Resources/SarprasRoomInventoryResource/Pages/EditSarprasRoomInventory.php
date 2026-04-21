<?php

namespace App\Filament\Resources\SarprasRoomInventoryResource\Pages;

use App\Filament\Resources\SarprasRoomInventoryResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSarprasRoomInventory extends EditRecord
{
    protected static string $resource = SarprasRoomInventoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
