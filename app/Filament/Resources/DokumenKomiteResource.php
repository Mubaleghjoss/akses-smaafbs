<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasConfirmedDeleteActions;
use App\Filament\Concerns\HasModulePermissions;
use App\Filament\Concerns\HasOptimizedAdminTable;
use App\Filament\Resources\DokumenKomiteResource\Pages;
use App\Models\KomiteDocument;
use App\Support\GoogleDrive\GoogleDriveService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Schema as SchemaFacade;

class DokumenKomiteResource extends Resource
{
    use HasConfirmedDeleteActions;
    use HasModulePermissions;
    use HasOptimizedAdminTable;

    protected static ?string $model = KomiteDocument::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-folder-open';

    protected static string|\UnitEnum|null $navigationGroup = 'Manajemen Sekolah';

    protected static ?string $navigationLabel = 'Dokumen Komite';

    protected static ?string $modelLabel = 'dokumen komite';

    protected static ?string $pluralModelLabel = 'Dokumen Komite';

    protected static ?int $navigationSort = 40;

    protected static ?string $permissionPrefix = 'dokumen_komite';

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public static function canAccess(): bool
    {
        return SchemaFacade::hasTable('komite_documents') && parent::canAccess();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Arsip Dokumen Komite')
                    ->description('Simpan SK komite, notulen rapat, catatan hasil rapat, dan dokumentasi kegiatan komite per tahun arsip.')
                    ->columns(['default' => 1, 'md' => 2])
                    ->schema([
                        Forms\Components\TextInput::make('arsip_tahun')
                            ->label('Tahun Arsip')
                            ->numeric()
                            ->required()
                            ->minValue(2000)
                            ->maxValue(2100),
                        Forms\Components\Select::make('jenis_dokumen')
                            ->label('Jenis Dokumen')
                            ->required()
                            ->native(false)
                            ->options(KomiteDocument::typeOptions()),
                        Forms\Components\TextInput::make('judul')
                            ->label('Judul')
                            ->required()
                            ->maxLength(180)
                            ->columnSpanFull(),
                        Forms\Components\TextInput::make('nomor_dokumen')
                            ->label('Nomor Dokumen')
                            ->maxLength(120),
                        Forms\Components\DatePicker::make('tanggal_dokumen')
                            ->label('Tanggal Dokumen')
                            ->closeOnDateSelection()
                            ->native(false),
                        Forms\Components\FileUpload::make('file_path')
                            ->label('File Dokumen')
                            ->disk('public')
                            ->directory('komite/dokumen')
                            ->acceptedFileTypes([
                                'application/pdf',
                                'application/msword',
                                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                                'image/jpeg',
                                'image/png',
                            ])
                            ->downloadable()
                            ->openable()
                            ->columnSpanFull(),
                        Forms\Components\FileUpload::make('dokumentasi')
                            ->label('Dokumentasi Acara')
                            ->disk('public')
                            ->directory('komite/dokumentasi')
                            ->image()
                            ->multiple()
                            ->appendFiles()
                            ->reorderable()
                            ->panelLayout('grid')
                            ->downloadable()
                            ->openable()
                            ->columnSpanFull(),
                        Forms\Components\Textarea::make('catatan')
                            ->label('Catatan / Ringkasan')
                            ->rows(5)
                            ->placeholder('Simpan ringkasan keputusan rapat, poin penting SK, atau catatan kegiatan komite.')
                            ->columnSpanFull(),
                    ]),
                Section::make('Sinkron Google Drive')
                    ->description('File tetap tersimpan lokal di project. Jika sinkron otomatis aktif, dokumen akan masuk antrean background. Jika tidak, admin masih bisa memakai tombol Upload Sekarang secara manual.')
                    ->columns(['default' => 1, 'md' => 2])
                    ->schema([
                        Forms\Components\Placeholder::make('google_drive_status')
                            ->label('Status Upload')
                            ->content(fn (?KomiteDocument $record): string => $record
                                ? KomiteDocument::googleDriveStatusLabel($record->gdrive_upload_status)
                                : 'Akan muncul setelah dokumen disimpan.'),
                        Forms\Components\Placeholder::make('google_drive_progress')
                            ->label('Progress')
                            ->content(fn (?KomiteDocument $record): string => $record
                                ? ((int) ($record->gdrive_upload_progress ?? 0)).'%'
                                : '0%'),
                        Forms\Components\Placeholder::make('google_drive_sync_mode')
                            ->label('Hasil Sinkron Terakhir')
                            ->content(fn (?KomiteDocument $record): string => $record
                                ? KomiteDocument::googleDriveSyncModeLabel($record->gdrive_last_sync_mode)
                                : '-'),
                        Forms\Components\Placeholder::make('google_drive_message')
                            ->label('Pesan Terakhir')
                            ->content(fn (?KomiteDocument $record): string => $record?->gdrive_upload_message ?: 'Belum ada proses sinkronisasi.')
                            ->columnSpanFull(),
                        Forms\Components\Placeholder::make('google_drive_link')
                            ->label('Link Google Drive')
                            ->content(fn (?KomiteDocument $record): string => $record?->resolvedDriveUrl() ?: '-')
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return static::optimizeAdminTable(
            $table,
            searchPlaceholder: 'Cari judul, nomor dokumen, atau catatan komite...',
            emptyStateHeading: 'Belum ada dokumen komite',
            emptyStateDescription: 'Tambahkan arsip SK, notulen rapat, catatan hasil rapat, atau dokumentasi kegiatan komite.'
        )
            ->defaultSort('arsip_tahun', 'desc')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->orderByDesc('arsip_tahun')->orderByDesc('tanggal_dokumen')->orderByDesc('id'))
            ->columns([
                Tables\Columns\TextColumn::make('arsip_tahun')
                    ->label('Tahun')
                    ->sortable()
                    ->alignCenter(),
                Tables\Columns\TextColumn::make('jenis_dokumen')
                    ->label('Jenis')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => KomiteDocument::typeLabel($state))
                    ->searchable(),
                Tables\Columns\TextColumn::make('judul')
                    ->label('Judul')
                    ->searchable()
                    ->description(fn (KomiteDocument $record): ?string => filled($record->nomor_dokumen) ? 'No. '.$record->nomor_dokumen : null)
                    ->wrap(),
                Tables\Columns\TextColumn::make('tanggal_dokumen')
                    ->label('Tanggal')
                    ->date('d/m/Y')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('file_path')
                    ->label('File')
                    ->formatStateUsing(fn (?string $state): string => $state ? basename($state) : '-')
                    ->url(fn (KomiteDocument $record): ?string => $record->resolvedFileUrl())
                    ->openUrlInNewTab()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('gdrive_upload_status')
                    ->label('Google Drive')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => KomiteDocument::googleDriveStatusLabel($state))
                    ->color(fn (?string $state): string => KomiteDocument::googleDriveStatusColor($state))
                    ->description(fn (KomiteDocument $record): string => ((int) ($record->gdrive_upload_progress ?? 0)).'%'),
                Tables\Columns\TextColumn::make('gdrive_last_sync_mode')
                    ->label('Sinkron Terakhir')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => KomiteDocument::googleDriveSyncModeLabel($state))
                    ->color(fn (?string $state): string => KomiteDocument::googleDriveSyncModeColor($state))
                    ->toggleable(),
                Tables\Columns\TextColumn::make('dokumentasi_count')
                    ->label('Dokumentasi')
                    ->state(fn (KomiteDocument $record): int => count(Arr::wrap($record->dokumentasi)))
                    ->badge()
                    ->alignCenter(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Diupdate')
                    ->since()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('arsip_tahun')
                    ->label('Tahun Arsip')
                    ->options(fn (): array => KomiteDocument::arsipTahunOptions()),
                Tables\Filters\SelectFilter::make('jenis_dokumen')
                    ->label('Jenis Dokumen')
                    ->options(KomiteDocument::typeOptions()),
                Tables\Filters\SelectFilter::make('gdrive_last_sync_mode')
                    ->label('Mode Sinkron Terakhir')
                    ->options(KomiteDocument::googleDriveSyncModeOptions()),
            ])
            ->actions([
                Action::make('upload_google_drive_now')
                    ->label('Upload Sekarang')
                    ->icon('heroicon-o-cloud-arrow-up')
                    ->color('primary')
                    ->visible(fn (KomiteDocument $record): bool => $record->hasUploadableFiles())
                    ->action(function (KomiteDocument $record): void {
                        static::uploadGoogleDriveNow($record);
                    }),
                Action::make('buka_file')
                    ->label('Buka File')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->color('gray')
                    ->visible(fn (KomiteDocument $record): bool => filled($record->file_path))
                    ->url(fn (KomiteDocument $record): ?string => $record->resolvedFileUrl())
                    ->openUrlInNewTab(),
                Action::make('buka_drive')
                    ->label('Buka Drive')
                    ->icon('heroicon-o-folder-open')
                    ->color('gray')
                    ->visible(fn (KomiteDocument $record): bool => filled($record->resolvedDriveUrl()))
                    ->url(fn (KomiteDocument $record): ?string => $record->resolvedDriveUrl())
                    ->openUrlInNewTab(),
                EditAction::make(),
                static::makeDeleteTableAction('dokumen komite'),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    static::makeDeleteBulkTableAction(),
                ]),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->orderByDesc('arsip_tahun');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDokumenKomites::route('/'),
            'create' => Pages\CreateDokumenKomite::route('/create'),
            'edit' => Pages\EditDokumenKomite::route('/{record}/edit'),
        ];
    }

    public static function queueGoogleDriveSync(KomiteDocument $record): string
    {
        $status = app(GoogleDriveService::class)->queueKomiteDocumentSync($record);

        Notification::make()
            ->title(match ($status) {
                KomiteDocument::GDRIVE_STATUS_QUEUED => 'Dokumen masuk antrean Google Drive',
                KomiteDocument::GDRIVE_STATUS_CONFIG_INCOMPLETE => 'Konfigurasi Google Drive belum lengkap',
                KomiteDocument::GDRIVE_STATUS_INACTIVE => 'Sinkronisasi otomatis Google Drive nonaktif',
                KomiteDocument::GDRIVE_STATUS_SKIPPED => 'Tidak ada file untuk diunggah',
                default => 'Status Google Drive diperbarui',
            })
            ->body($record->fresh()?->gdrive_upload_message)
            ->{$status === KomiteDocument::GDRIVE_STATUS_QUEUED ? 'success' : 'warning'}()
            ->send();

        return $status;
    }

    public static function uploadGoogleDriveNow(KomiteDocument $record): string
    {
        $status = app(GoogleDriveService::class)->uploadKomiteDocumentNow($record);

        Notification::make()
            ->title(match ($status) {
                KomiteDocument::GDRIVE_STATUS_SYNCED => 'Upload / pemulihan Google Drive selesai',
                KomiteDocument::GDRIVE_STATUS_CONFIG_INCOMPLETE => 'Konfigurasi Google Drive belum lengkap',
                KomiteDocument::GDRIVE_STATUS_INACTIVE => 'Google Drive nonaktif',
                KomiteDocument::GDRIVE_STATUS_SKIPPED => 'Tidak ada file untuk diunggah',
                KomiteDocument::GDRIVE_STATUS_FAILED => 'Upload Google Drive gagal',
                default => 'Status Google Drive diperbarui',
            })
            ->body($record->fresh()?->gdrive_upload_message)
            ->{$status === KomiteDocument::GDRIVE_STATUS_SYNCED ? 'success' : ($status === KomiteDocument::GDRIVE_STATUS_FAILED ? 'danger' : 'warning')}()
            ->send();

        return $status;
    }
}
