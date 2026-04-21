<?php

namespace App\Filament\Resources\PerpustakaanKategoriResource\Pages;

use App\Filament\Resources\PerpustakaanKategoriResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPerpustakaanKategori extends EditRecord
{
    protected static string $resource = PerpustakaanKategoriResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
