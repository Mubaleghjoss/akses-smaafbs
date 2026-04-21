<?php

namespace App\Filament\Resources\SppBillResource\Pages;

use App\Filament\Resources\SppBillResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSppBill extends EditRecord
{
    protected static string $resource = SppBillResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
