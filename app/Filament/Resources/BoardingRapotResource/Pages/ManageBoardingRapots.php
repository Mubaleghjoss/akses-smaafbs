<?php

namespace App\Filament\Resources\BoardingRapotResource\Pages;

use App\Filament\Resources\BoardingRapotResource;
use App\Models\BoardingRapot;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageBoardingRapots extends ManageRecords
{
    protected static string $resource = BoardingRapotResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->after(function (BoardingRapot $record): void {
                    $record->syncFromSources();
                }),
        ];
    }
}
