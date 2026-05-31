<?php

namespace App\Filament\Resources\BoardingRapotResource\Pages;

use App\Filament\Resources\BoardingRapotResource;
use Filament\Resources\Pages\CreateRecord;

class CreateBoardingRapot extends CreateRecord
{
    protected static string $resource = BoardingRapotResource::class;

    protected function afterCreate(): void
    {
        $this->record->syncFromSources();
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}
