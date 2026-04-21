<?php

namespace App\Filament\Resources\SppBillResource\Pages;

use App\Filament\Resources\SppBillResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSppBills extends ListRecords
{
    protected static string $resource = SppBillResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
