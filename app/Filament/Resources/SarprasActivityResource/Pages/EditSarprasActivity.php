<?php

namespace App\Filament\Resources\SarprasActivityResource\Pages;

use App\Filament\Resources\SarprasActivityResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSarprasActivity extends EditRecord
{
    protected static string $resource = SarprasActivityResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
