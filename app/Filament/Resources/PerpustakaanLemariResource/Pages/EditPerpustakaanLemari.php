<?php

namespace App\Filament\Resources\PerpustakaanLemariResource\Pages;

use App\Filament\Resources\PerpustakaanLemariResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPerpustakaanLemari extends EditRecord
{
    protected static string $resource = PerpustakaanLemariResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
