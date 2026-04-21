<?php

namespace App\Filament\Resources\ProfilSekolahResource\Pages;

use App\Filament\Resources\ProfilSekolahResource;
use App\Models\ProfilSekolah;
use Filament\Resources\Pages\CreateRecord;

class CreateProfilSekolah extends CreateRecord
{
    protected static string $resource = ProfilSekolahResource::class;

    protected function afterCreate(): void
    {
        /** @var ProfilSekolah $record */
        $record = $this->getRecord();

        static::getResource()::queueGoogleDriveSync($record);
    }
}
