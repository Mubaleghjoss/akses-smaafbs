<?php

namespace App\Filament\Resources\DokumenKomiteResource\Pages;

use App\Filament\Resources\DokumenKomiteResource;
use App\Models\KomiteDocument;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditDokumenKomite extends EditRecord
{
    protected static string $resource = DokumenKomiteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function afterSave(): void
    {
        /** @var KomiteDocument $record */
        $record = $this->getRecord();

        if ($record->wasChanged(['file_path', 'dokumentasi', 'judul', 'arsip_tahun', 'jenis_dokumen'])) {
            static::getResource()::queueGoogleDriveSync($record);
        }
    }
}
