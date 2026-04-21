<?php

namespace App\Filament\Resources\PrestasiResource\Pages;

use App\Filament\Resources\PrestasiResource;
use App\Models\Prestasi;
use Filament\Resources\Pages\CreateRecord;

class CreatePrestasi extends CreateRecord
{
    protected static string $resource = PrestasiResource::class;

    protected function afterCreate(): void
    {
        /** @var Prestasi $record */
        $record = $this->getRecord();

        static::getResource()::queueGoogleDriveSync($record);
    }
}
