<?php

namespace App\Filament\Resources\ProkerResource\Pages;

use App\Filament\Resources\ProkerResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditProker extends EditRecord
{
    protected static string $resource = ProkerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
