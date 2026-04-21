<?php

namespace App\Filament\Resources\BoardingHafalanPointResource\Pages;

use App\Filament\Resources\BoardingHafalanPointResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageBoardingHafalanPoints extends ManageRecords
{
    protected static string $resource = BoardingHafalanPointResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
