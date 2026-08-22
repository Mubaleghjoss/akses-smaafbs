<?php

namespace App\Filament\Resources\BelajarIdGuruResource\Pages;

use App\Filament\Resources\BelajarIdGuruResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditBelajarIdGuru extends EditRecord
{
    protected static string $resource = BelajarIdGuruResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
