<?php

namespace App\Filament\Resources\BerkasSiswaResource\Pages;

use App\Filament\Resources\BerkasSiswaResource;
use App\Models\BerkasSiswa;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditBerkasSiswa extends EditRecord
{
    protected static string $resource = BerkasSiswaResource::class;

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
        /** @var BerkasSiswa $record */
        $record = $this->getRecord();

        if ($record->wasChanged(['file_path', 'siswa_id', 'jenis_berkas_id'])) {
            static::getResource()::queueGoogleDriveSync($record);
        }
    }
}
