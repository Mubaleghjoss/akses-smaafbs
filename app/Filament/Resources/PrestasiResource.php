<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasModulePermissions;
use App\Filament\Concerns\HasOptimizedAdminTable;
use App\Filament\Resources\PrestasiResource\Pages;
use App\Filament\Resources\PrestasiResource\RelationManagers\HistoriesRelationManager;
use App\Models\DataSiswa;
use App\Models\Prestasi;
use App\Support\DataSiswa\DataSiswaSupport;
use App\Support\GoogleDrive\GoogleDriveService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Schema as SchemaFacade;

class PrestasiResource extends Resource
{
    use HasModulePermissions;
    use HasOptimizedAdminTable;

    protected static ?string $model = Prestasi::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-trophy';

    protected static string|\UnitEnum|null $navigationGroup = 'Siswa';

    protected static ?string $navigationLabel = 'Prestasi';

    protected static ?string $modelLabel = 'prestasi';

    protected static ?string $pluralModelLabel = 'Prestasi';

    protected static ?int $navigationSort = 30;

    protected static ?string $permissionPrefix = 'prestasi';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Data Prestasi Murid')
                    ->description('Simpan riwayat lomba, capaian juara, hadiah, dokumentasi, dan sertifikat siswa secara terstruktur.')
                    ->columns(['default' => 1, 'md' => 2])
                    ->schema([
                        Forms\Components\Select::make('siswa_id')
                            ->label('Murid')
                            ->relationship(
                                name: 'siswa',
                                titleAttribute: 'nama',
                                modifyQueryUsing: fn (Builder $query) => DataSiswa::applyVisibleScope($query, auth()->user())->orderBy('nama')
                            )
                            ->getOptionLabelFromRecordUsing(
                                fn (DataSiswa $record): string => trim($record->nama.' - '.($record->rombel_saat_ini ?: 'Tanpa rombel'))
                            )
                            ->searchable()
                            ->required(),
                        Forms\Components\TextInput::make('nama_lomba')
                            ->label('Nama Lomba')
                            ->required()
                            ->maxLength(150),
                        Forms\Components\DatePicker::make('tanggal_prestasi')
                            ->label('Tanggal Kegiatan / Lomba')
                            ->default(now())
                            ->required(),
                        Forms\Components\TextInput::make('penyelenggara')
                            ->label('Penyelenggara')
                            ->maxLength(150),
                        Forms\Components\TextInput::make('juara')
                            ->label('Juara / Capaian')
                            ->placeholder('Contoh: Juara 1, Finalis, Medali Emas')
                            ->maxLength(100),
                        Forms\Components\TextInput::make('hadiah')
                            ->label('Hadiah')
                            ->placeholder('Contoh: Piagam, trofi, uang pembinaan')
                            ->maxLength(255),
                        Forms\Components\Textarea::make('keterangan')
                            ->label('Keterangan')
                            ->rows(4)
                            ->columnSpanFull(),
                        Forms\Components\FileUpload::make('dokumentasi')
                            ->label('Bukti Dokumentasi')
                            ->disk('public')
                            ->directory('prestasi/dokumentasi')
                            ->multiple()
                            ->appendFiles()
                            ->reorderable()
                            ->panelLayout('grid')
                            ->downloadable()
                            ->openable()
                            ->helperText('Setelah prestasi disimpan, dokumentasi ini akan masuk antrean Google Drive jika sinkron otomatis aktif. Jika perlu, gunakan tombol Upload Sekarang setelah data tersimpan.')
                            ->columnSpanFull(),
                        Forms\Components\FileUpload::make('sertifikat_files')
                            ->label('Sertifikat / Piagam')
                            ->disk('public')
                            ->directory('prestasi/sertifikat')
                            ->multiple()
                            ->appendFiles()
                            ->reorderable()
                            ->panelLayout('grid')
                            ->downloadable()
                            ->openable()
                            ->helperText('Setelah prestasi disimpan, file sertifikat akan masuk antrean Google Drive jika sinkron otomatis aktif. Jika perlu, gunakan tombol Upload Sekarang setelah data tersimpan.')
                            ->columnSpanFull(),
                    ]),
                Section::make('Sinkron Google Drive')
                    ->description('Dokumentasi dan sertifikat tetap tersimpan lokal di project. Jika sinkron otomatis aktif, file prestasi akan masuk antrean background. Jika tidak, admin masih bisa memakai tombol Upload Sekarang dari halaman edit atau tabel.')
                    ->columns(['default' => 1, 'md' => 2])
                    ->schema([
                        Forms\Components\Placeholder::make('google_drive_status')
                            ->label('Status Upload')
                            ->content(fn (?Prestasi $record): string => $record
                                ? Prestasi::googleDriveStatusLabel($record->gdrive_upload_status)
                                : 'Akan muncul setelah prestasi disimpan.'),
                        Forms\Components\Placeholder::make('google_drive_progress')
                            ->label('Progress')
                            ->content(fn (?Prestasi $record): string => $record
                                ? ((int) ($record->gdrive_upload_progress ?? 0)).'%'
                                : '0%'),
                        Forms\Components\Placeholder::make('google_drive_sync_mode')
                            ->label('Hasil Sinkron Terakhir')
                            ->content(fn (?Prestasi $record): string => $record
                                ? Prestasi::googleDriveSyncModeLabel($record->gdrive_last_sync_mode)
                                : '-'),
                        Forms\Components\Placeholder::make('google_drive_link')
                            ->label('Link Google Drive')
                            ->content(fn (?Prestasi $record): string => $record?->resolvedDriveUrl() ?: '-'),
                        Forms\Components\Placeholder::make('google_drive_message')
                            ->label('Pesan Terakhir')
                            ->content(fn (?Prestasi $record): string => $record?->gdrive_upload_message ?: 'Belum ada proses sinkronisasi.')
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return static::optimizeAdminTable(
            $table,
            searchPlaceholder: 'Cari murid, rombel, nama lomba, penyelenggara, atau juara...',
            emptyStateHeading: 'Belum ada data prestasi',
            emptyStateDescription: 'Catat lomba, capaian juara, dan bukti pendukung agar riwayat prestasi siswa terdokumentasi.'
        )
            ->defaultSort('tanggal_prestasi', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('tanggal_prestasi')
                    ->label('Tanggal')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('siswa.nama')
                    ->label('Murid')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('siswa.rombel_saat_ini')
                    ->label('Rombel')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('nama_lomba')
                    ->label('Nama Lomba')
                    ->searchable()
                    ->wrap(),
                Tables\Columns\TextColumn::make('penyelenggara')
                    ->label('Penyelenggara')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('juara')
                    ->label('Juara')
                    ->badge()
                    ->searchable(),
                Tables\Columns\TextColumn::make('gdrive_upload_status')
                    ->label('Google Drive')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => Prestasi::googleDriveStatusLabel($state))
                    ->color(fn (?string $state): string => Prestasi::googleDriveStatusColor($state))
                    ->description(fn (Prestasi $record): string => ((int) ($record->gdrive_upload_progress ?? 0)).'%'),
                Tables\Columns\TextColumn::make('gdrive_last_sync_mode')
                    ->label('Sinkron Terakhir')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => Prestasi::googleDriveSyncModeLabel($state))
                    ->color(fn (?string $state): string => Prestasi::googleDriveSyncModeColor($state))
                    ->toggleable(),
                Tables\Columns\TextColumn::make('hadiah')
                    ->label('Hadiah')
                    ->limit(30)
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('dokumentasi_count')
                    ->label('Dokumentasi')
                    ->state(fn (Prestasi $record): int => count(Arr::wrap($record->dokumentasi))),
                Tables\Columns\TextColumn::make('sertifikat_count')
                    ->label('Sertifikat')
                    ->state(fn (Prestasi $record): int => count(Arr::wrap($record->sertifikat_files))),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Diupdate')
                    ->since()
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\Filter::make('angkatan')
                    ->form([
                        Forms\Components\Select::make('value')
                            ->label('Angkatan')
                            ->options(fn (): array => DataSiswaSupport::angkatanOptions(auth()->user())),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        if (! filled($data['value'] ?? null)) {
                            return $query;
                        }

                        return $query->whereHas('siswa', function (Builder $studentQuery) use ($data): void {
                            $studentQuery->where('rombel_saat_ini', 'like', '%'.$data['value'].'%');
                        });
                    }),
                Tables\Filters\SelectFilter::make('rombel')
                    ->label('Rombel')
                    ->options(fn (): array => DataSiswaSupport::rombelOptions(auth()->user()))
                    ->query(function (Builder $query, array $data): Builder {
                        $value = $data['value'] ?? null;

                        if (! filled($value)) {
                            return $query;
                        }

                        return $query->whereHas('siswa', function (Builder $studentQuery) use ($value): void {
                            $studentQuery->where('rombel_saat_ini', $value);
                        });
                    }),
                Tables\Filters\SelectFilter::make('gdrive_last_sync_mode')
                    ->label('Mode Sinkron Terakhir')
                    ->options(Prestasi::googleDriveSyncModeOptions()),
            ])
            ->actions([
                Action::make('upload_google_drive_now')
                    ->label('Upload Sekarang')
                    ->icon('heroicon-o-cloud-arrow-up')
                    ->color('primary')
                    ->visible(fn (Prestasi $record): bool => $record->hasUploadableFiles())
                    ->action(function (Prestasi $record): void {
                        static::uploadGoogleDriveNow($record);
                    }),
                Action::make('buka_drive')
                    ->label('Buka Drive')
                    ->icon('heroicon-o-folder-open')
                    ->color('gray')
                    ->visible(fn (Prestasi $record): bool => filled($record->resolvedDriveUrl()))
                    ->url(fn (Prestasi $record): ?string => $record->resolvedDriveUrl())
                    ->openUrlInNewTab(),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            HistoriesRelationManager::class,
        ];
    }

    public static function canDelete($record): bool
    {
        return $record instanceof Model && static::userCanModule('manage');
    }

    public static function canDeleteAny(): bool
    {
        return static::userCanModule('manage');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny() && SchemaFacade::hasTable('prestasis');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with('siswa:id,nama,rombel_saat_ini')
            ->visibleToUser(auth()->user());
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPrestasis::route('/'),
            'create' => Pages\CreatePrestasi::route('/create'),
            'edit' => Pages\EditPrestasi::route('/{record}/edit'),
        ];
    }

    public static function queueGoogleDriveSync(Prestasi $record): string
    {
        $status = app(GoogleDriveService::class)->queuePrestasiSync($record);

        Notification::make()
            ->title(match ($status) {
                Prestasi::GDRIVE_STATUS_QUEUED => 'Prestasi masuk antrean Google Drive',
                Prestasi::GDRIVE_STATUS_CONFIG_INCOMPLETE => 'Konfigurasi Google Drive belum lengkap',
                Prestasi::GDRIVE_STATUS_INACTIVE => 'Sinkronisasi otomatis Google Drive nonaktif',
                Prestasi::GDRIVE_STATUS_SKIPPED => 'Tidak ada file untuk diunggah',
                default => 'Status Google Drive diperbarui',
            })
            ->body($record->fresh()?->gdrive_upload_message)
            ->{$status === Prestasi::GDRIVE_STATUS_QUEUED ? 'success' : 'warning'}()
            ->send();

        return $status;
    }

    public static function uploadGoogleDriveNow(Prestasi $record): string
    {
        $status = app(GoogleDriveService::class)->uploadPrestasiNow($record);

        Notification::make()
            ->title(match ($status) {
                Prestasi::GDRIVE_STATUS_SYNCED => 'Upload / pemulihan Google Drive selesai',
                Prestasi::GDRIVE_STATUS_CONFIG_INCOMPLETE => 'Konfigurasi Google Drive belum lengkap',
                Prestasi::GDRIVE_STATUS_INACTIVE => 'Google Drive nonaktif',
                Prestasi::GDRIVE_STATUS_SKIPPED => 'Tidak ada file untuk diunggah',
                Prestasi::GDRIVE_STATUS_FAILED => 'Upload Google Drive gagal',
                default => 'Status Google Drive diperbarui',
            })
            ->body($record->fresh()?->gdrive_upload_message)
            ->{$status === Prestasi::GDRIVE_STATUS_SYNCED ? 'success' : ($status === Prestasi::GDRIVE_STATUS_FAILED ? 'danger' : 'warning')}()
            ->send();

        return $status;
    }
}
