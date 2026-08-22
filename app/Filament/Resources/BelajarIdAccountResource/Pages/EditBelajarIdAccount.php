<?php

namespace App\Filament\Resources\BelajarIdAccountResource\Pages;

use App\Filament\Resources\BelajarIdAccountResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditBelajarIdAccount extends EditRecord
{
    protected static string $resource = BelajarIdAccountResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
