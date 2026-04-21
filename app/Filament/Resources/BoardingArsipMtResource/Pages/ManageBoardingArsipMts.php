<?php

namespace App\Filament\Resources\BoardingArsipMtResource\Pages;

use App\Filament\Resources\BoardingArsipMtResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageBoardingArsipMts extends ManageRecords
{
    protected static string $resource = BoardingArsipMtResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Tambahkan Arsip Boarding')
                ->modalHeading('Tambahkan Arsip Boarding'),
        ];
    }
}
