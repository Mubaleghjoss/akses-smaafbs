<?php

namespace App\Filament\Resources\DokumenKomiteResource\Pages;

use App\Filament\Resources\DokumenKomiteResource;
use App\Models\KomiteDocument;
use Filament\Resources\Pages\CreateRecord;

class CreateDokumenKomite extends CreateRecord
{
    protected static string $resource = DokumenKomiteResource::class;

    protected function afterCreate(): void
    {
        /** @var KomiteDocument $record */
        $record = $this->getRecord();

        static::getResource()::queueGoogleDriveSync($record);
    }
}
