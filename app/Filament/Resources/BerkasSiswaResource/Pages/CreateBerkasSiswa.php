<?php

namespace App\Filament\Resources\BerkasSiswaResource\Pages;

use App\Filament\Resources\BerkasSiswaResource;
use App\Models\BerkasSiswa;
use Filament\Resources\Pages\CreateRecord;

class CreateBerkasSiswa extends CreateRecord
{
    protected static string $resource = BerkasSiswaResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return static::getResource()::normalizeStoredDocument($data);
    }

    protected function afterCreate(): void
    {
        /** @var BerkasSiswa $record */
        $record = $this->getRecord();

        static::getResource()::queueGoogleDriveSync($record);
    }
}
