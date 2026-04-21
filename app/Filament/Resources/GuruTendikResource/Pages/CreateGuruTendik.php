<?php

namespace App\Filament\Resources\GuruTendikResource\Pages;

use App\Filament\Resources\GuruTendikResource;
use Filament\Resources\Pages\CreateRecord;

class CreateGuruTendik extends CreateRecord
{
    protected static string $resource = GuruTendikResource::class;

    protected function afterCreate(): void
    {
        GuruTendikResource::notifyTugasTambahanGoogleDriveSummary($this->record);
    }
}
