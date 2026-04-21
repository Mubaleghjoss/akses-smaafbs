<?php

namespace App\Filament\Resources\GuruTendikResource\RelationManagers;

use App\Filament\Resources\BerkasGuruResource;
use App\Models\BerkasGuru;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Livewire\Attributes\On;

class BerkasGurusRelationManager extends RelationManager
{
    protected static string $relationship = 'berkasGurus';

    protected static ?string $title = 'Histori Berkas Guru';

    protected static bool $isLazy = true;

    #[On('refresh-berkas-guru-relation-manager')]
    public function refreshRelationManager(): void
    {
        $this->resetPage();
        $this->flushCachedTableRecords();
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('uploaded_at', 'desc')
            ->modifyQueryUsing(fn ($query) => $query
                ->select([
                    'id',
                    'guru_id',
                    'jenis_berkas_id',
                    'file_path',
                    'keterangan',
                    'uploaded_at',
                    'gdrive_upload_status',
                    'gdrive_upload_progress',
                    'gdrive_upload_message',
                    'gdrive_last_sync_mode',
                    'gdrive_folder_url',
                    'gdrive_file_url',
                ])
                ->with(['jenisBerkas:id,nama_berkas', 'tugasTambahanHistory:id,berkas_guru_id']))
            ->recordTitleAttribute('jenisBerkas.nama_berkas')
            ->columns([
                Tables\Columns\TextColumn::make('jenisBerkas.nama_berkas')
                    ->label('Jenis Berkas')
                    ->badge()
                    ->searchable(),
                Tables\Columns\TextColumn::make('sumber_data')
                    ->label('Sumber')
                    ->state(fn (BerkasGuru $record): string => $record->tugasTambahanHistory ? 'History Tugas Tambahan' : 'Berkas Guru')
                    ->badge()
                    ->color(fn (BerkasGuru $record): string => $record->tugasTambahanHistory ? 'warning' : 'gray'),
                Tables\Columns\TextColumn::make('file_path')
                    ->label('File')
                    ->formatStateUsing(fn (?string $state): string => $state ? basename($state) : '-')
                    ->url(fn (BerkasGuru $record): ?string => $record->resolvedFileUrl())
                    ->openUrlInNewTab()
                    ->wrap(),
                Tables\Columns\TextColumn::make('gdrive_upload_status')
                    ->label('Google Drive')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => BerkasGuru::googleDriveStatusLabel($state))
                    ->color(fn (?string $state): string => BerkasGuru::googleDriveStatusColor($state))
                    ->description(fn (BerkasGuru $record): string => ((int) ($record->gdrive_upload_progress ?? 0)).'%'),
                Tables\Columns\TextColumn::make('gdrive_last_sync_mode')
                    ->label('Sinkron Terakhir')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => BerkasGuru::googleDriveSyncModeLabel($state))
                    ->color(fn (?string $state): string => BerkasGuru::googleDriveSyncModeColor($state))
                    ->toggleable(),
                Tables\Columns\TextColumn::make('uploaded_at')
                    ->label('Upload')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('gdrive_upload_message')
                    ->label('Pesan Sinkron')
                    ->limit(90)
                    ->wrap()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('keterangan')
                    ->limit(60)
                    ->wrap()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->headerActions([
                Action::make('tambahBerkas')
                    ->label('Tambah Berkas')
                    ->icon('heroicon-o-plus')
                    ->color('primary')
                    ->visible(fn (): bool => BerkasGuruResource::canCreate())
                    ->url(fn (): string => BerkasGuruResource::getUrl('create', ['guru_id' => $this->getOwnerRecord()->getKey()])),
            ])
            ->actions([
                Action::make('preview')
                    ->label('Preview')
                    ->icon('heroicon-o-eye')
                    ->color('info')
                    ->url(fn (BerkasGuru $record): string => route('admin.berkas-gurus.preview', $record))
                    ->openUrlInNewTab(),
                ActionGroup::make([
                    Action::make('upload_google_drive_now')
                        ->label('Upload Sekarang')
                        ->icon('heroicon-o-cloud-arrow-up')
                        ->color('primary')
                        ->visible(fn (BerkasGuru $record): bool => $record->hasUploadableFiles())
                        ->action(function (BerkasGuru $record): void {
                            BerkasGuruResource::uploadGoogleDriveNow($record);
                            $this->refreshRelationManager();
                        }),
                    Action::make('buka_drive')
                        ->label('Buka Drive')
                        ->icon('heroicon-o-folder-open')
                        ->color('gray')
                        ->visible(fn (BerkasGuru $record): bool => filled($record->resolvedDriveUrl()))
                        ->url(fn (BerkasGuru $record): ?string => $record->resolvedDriveUrl())
                        ->openUrlInNewTab(),
                    Action::make('edit_berkas')
                        ->label('Edit Berkas')
                        ->icon('heroicon-o-pencil-square')
                        ->color('warning')
                        ->visible(fn (BerkasGuru $record): bool => ! $record->tugasTambahanHistory)
                        ->url(fn (BerkasGuru $record): string => BerkasGuruResource::getUrl('edit', ['record' => $record])),
                ])
                    ->label('Lainnya')
                    ->icon('heroicon-o-ellipsis-horizontal'),
            ])
            ->bulkActions([]);
    }
}
