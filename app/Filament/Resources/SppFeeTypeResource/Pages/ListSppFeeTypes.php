<?php

namespace App\Filament\Resources\SppFeeTypeResource\Pages;

use App\Filament\Resources\SppFeeTypeResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSppFeeTypes extends ListRecords
{
    protected static string $resource = SppFeeTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
