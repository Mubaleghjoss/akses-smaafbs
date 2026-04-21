<?php

namespace App\Filament\Resources\VisiMisiResource\Pages;

use App\Filament\Resources\VisiMisiResource;
use Filament\Resources\Pages\EditRecord;

class EditVisiMisi extends EditRecord
{
    protected static string $resource = VisiMisiResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
