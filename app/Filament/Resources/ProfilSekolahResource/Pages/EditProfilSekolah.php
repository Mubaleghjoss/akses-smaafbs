<?php

namespace App\Filament\Resources\ProfilSekolahResource\Pages;

use App\Filament\Resources\ProfilSekolahResource;
use App\Models\ProfilSekolah;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditProfilSekolah extends EditRecord
{
    protected static string $resource = ProfilSekolahResource::class;

    protected function getHeaderActions(): array
    {
        /** @var ProfilSekolah|null $record */
        $record = $this->getRecord();

        return array_values(array_filter([
            Actions\Action::make('upload_google_drive_now')
                ->label('Upload Sekarang')
                ->icon('heroicon-o-cloud-arrow-up')
                ->color('primary')
                ->visible(fn (): bool => $record?->hasUploadableFiles() ?? false)
                ->action(fn (): string => static::getResource()::uploadGoogleDriveNow($this->getRecord())),
            Actions\Action::make('buka_file')
                ->label('Buka File')
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->color('gray')
                ->visible(fn (): bool => filled($record?->resolvedAccreditationFileUrl()))
                ->url(fn (): ?string => $record?->resolvedAccreditationFileUrl())
                ->openUrlInNewTab(),
            Actions\Action::make('buka_drive')
                ->label('Buka Drive')
                ->icon('heroicon-o-folder-open')
                ->color('gray')
                ->visible(fn (): bool => filled($record?->resolvedDriveUrl()))
                ->url(fn (): ?string => $record?->resolvedDriveUrl())
                ->openUrlInNewTab(),
        ]));
    }

    protected function afterSave(): void
    {
        /** @var ProfilSekolah $record */
        $record = $this->getRecord();

        if ($record->wasChanged(['file_akreditasi_path', 'nama_sekolah', 'tanggal_identitas', 'terakreditasi'])) {
            static::getResource()::queueGoogleDriveSync($record);
        }
    }
}
