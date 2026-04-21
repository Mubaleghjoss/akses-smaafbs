<?php

namespace App\Filament\Resources\PrestasiResource\Pages;

use App\Filament\Resources\PrestasiResource;
use App\Models\Prestasi;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPrestasi extends EditRecord
{
    protected static string $resource = PrestasiResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('uploadGoogleDriveNow')
                ->label('Upload Sekarang')
                ->icon('heroicon-o-cloud-arrow-up')
                ->color('primary')
                ->visible(fn (): bool => $this->record->hasUploadableFiles())
                ->action(function (): void {
                    PrestasiResource::uploadGoogleDriveNow($this->record);
                    $this->record->refresh();
                    $this->fillForm();
                }),
            Actions\Action::make('bukaDrive')
                ->label('Buka Drive')
                ->icon('heroicon-o-folder-open')
                ->color('gray')
                ->visible(fn (): bool => filled($this->record->resolvedDriveUrl()))
                ->url(fn (): ?string => $this->record->resolvedDriveUrl())
                ->openUrlInNewTab(),
            Actions\DeleteAction::make(),
        ];
    }

    protected function afterSave(): void
    {
        /** @var Prestasi $record */
        $record = $this->getRecord();

        if ($record->wasChanged(['dokumentasi', 'sertifikat_files', 'siswa_id', 'nama_lomba'])) {
            static::getResource()::queueGoogleDriveSync($record);
        }

        $this->record->refresh();
        $this->fillForm();
    }
}
