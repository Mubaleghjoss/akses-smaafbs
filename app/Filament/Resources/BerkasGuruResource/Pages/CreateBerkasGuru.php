<?php

namespace App\Filament\Resources\BerkasGuruResource\Pages;

use App\Filament\Resources\BerkasGuruResource;
use App\Models\BerkasGuru;
use Filament\Resources\Pages\CreateRecord;

class CreateBerkasGuru extends CreateRecord
{
    protected static string $resource = BerkasGuruResource::class;

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
        /** @var BerkasGuru $record */
        $record = $this->getRecord();

        static::getResource()::queueGoogleDriveSync($record);
    }
}
