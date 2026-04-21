<?php

namespace App\Filament\Resources\StrukturKomiteResource\Pages;

use App\Filament\Resources\StrukturKomiteResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditStrukturKomite extends EditRecord
{
    protected static string $resource = StrukturKomiteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
