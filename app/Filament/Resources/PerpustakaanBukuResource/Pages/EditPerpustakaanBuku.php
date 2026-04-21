<?php

namespace App\Filament\Resources\PerpustakaanBukuResource\Pages;

use App\Filament\Resources\PerpustakaanBukuResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPerpustakaanBuku extends EditRecord
{
    protected static string $resource = PerpustakaanBukuResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
