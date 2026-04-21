<?php

namespace App\Filament\Resources\BerkasGuruResource\Pages;

use App\Filament\Resources\BerkasGuruResource;
use App\Models\BerkasGuru;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditBerkasGuru extends EditRecord
{
    protected static string $resource = BerkasGuruResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        return static::getResource()::normalizeStoredDocument($data);
    }

    protected function afterSave(): void
    {
        /** @var BerkasGuru $record */
        $record = $this->getRecord();

        if ($record->wasChanged(['file_path', 'guru_id', 'jenis_berkas_id'])) {
            static::getResource()::queueGoogleDriveSync($record);
        }
    }
}
