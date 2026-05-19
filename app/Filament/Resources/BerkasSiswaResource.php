<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasConfirmedDeleteActions;
use App\Filament\Concerns\HasModulePermissions;
use App\Filament\Concerns\HasOptimizedAdminTable;
use App\Filament\Resources\BerkasSiswaResource\Pages;
use App\Models\BerkasSiswa;
use App\Models\DataSiswa;
use App\Models\JenisBerkas;
use App\Support\Documents\ManagedDocumentNaming;
use App\Support\GoogleDrive\GoogleDriveService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
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
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Throwable;

class BerkasSiswaResource extends Resource
{
    use HasConfirmedDeleteActions;
    use HasModulePermissions;
    use HasOptimizedAdminTable;

    protected static ?string $model = BerkasSiswa::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-folder-open';

    protected static string|\UnitEnum|null $navigationGroup = 'Siswa';

    protected static ?string $navigationLabel = 'Berkas Siswa';

    protected static ?string $modelLabel = 'berkas siswa';

    protected static ?string $pluralModelLabel = 'Berkas Siswa';

    protected static ?int $navigationSort = 20;

    protected static ?string $permissionPrefix = 'berkas_siswa';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Berkas Siswa')
                    ->columns(['default' => 1, 'md' => 2])
                    ->schema([
                        Forms\Components\Select::make('siswa_id')
                            ->label('Siswa')
                            ->required()
                            ->searchable()
                            ->getSearchResultsUsing(function (string $search): array {
                                return DataSiswa::query()
                                    ->where(function (Builder $query) use ($search): void {
                                        $query
                                            ->where('nama', 'like', '%'.$search.'%')
                                            ->orWhere('nipd', 'like', '%'.$search.'%')
                                            ->orWhere('nisn', 'like', '%'.$search.'%');
                                    })
                                    ->orderBy('nama')
                                    ->limit(50)
                                    ->pluck('nama', 'id')
                                    ->toArray();
                            })
                            ->getOptionLabelUsing(fn ($value): ?string => DataSiswa::query()->whereKey($value)->value('nama')),
                        Forms\Components\Select::make('jenis_berkas_id')
                            ->label('Jenis Berkas')
                            ->required()
                            ->searchable()
                            ->options(fn (): array => JenisBerkas::searchOptionLabels(limit: 25))
                            ->getSearchResultsUsing(fn (string $search): array => JenisBerkas::searchOptionLabels($search))
                            ->getOptionLabelUsing(fn ($value): ?string => JenisBerkas::resolveOptionLabel($value)),
                        Forms\Components\Select::make('status')
                            ->required()
                            ->options([
                                'lengkap' => 'Lengkap',
                                'kurang' => 'Kurang',
                                'belum_mengumpulkan' => 'Belum Mengumpulkan',
                            ])
                            ->default('belum_mengumpulkan'),
                        Forms\Components\FileUpload::make('file_path')
                            ->label('File')
                            ->disk('public')
                            ->directory('berkas_siswa')
                            ->maxSize(4096)
                            ->acceptedFileTypes([
                                'application/pdf',
                                'image/jpeg',
                                'image/png',
                            ])
                            ->required(),
                        Forms\Components\Textarea::make('keterangan')
                            ->columnSpanFull(),
                        Forms\Components\Hidden::make('file_name'),
                        Forms\Components\Hidden::make('file_size'),
                        Forms\Components\Hidden::make('mime_type'),
                        Forms\Components\Hidden::make('has_deleted')
                            ->default(0),
                        Forms\Components\Hidden::make('uploaded_by')
                            ->default(fn () => auth()->id()),
                    ]),
                Section::make('Sinkron Google Drive')
                    ->description('File tetap tersimpan lokal di project. Jika sinkron otomatis aktif, berkas siswa akan masuk antrean background. Jika tidak, admin masih bisa memakai tombol Upload Sekarang secara manual.')
                    ->columns(['default' => 1, 'md' => 2])
                    ->schema([
                        Forms\Components\Placeholder::make('google_drive_status')
                            ->label('Status Upload')
                            ->content(fn (?BerkasSiswa $record): string => $record
                                ? BerkasSiswa::googleDriveStatusLabel($record->gdrive_upload_status)
                                : 'Akan muncul setelah berkas disimpan.'),
                        Forms\Components\Placeholder::make('google_drive_progress')
                            ->label('Progress')
                            ->content(fn (?BerkasSiswa $record): string => $record
                                ? ((int) ($record->gdrive_upload_progress ?? 0)).'%'
                                : '0%'),
                        Forms\Components\Placeholder::make('google_drive_sync_mode')
                            ->label('Hasil Sinkron Terakhir')
                            ->content(fn (?BerkasSiswa $record): string => $record
                                ? BerkasSiswa::googleDriveSyncModeLabel($record->gdrive_last_sync_mode)
                                : '-'),
                        Forms\Components\Placeholder::make('google_drive_link')
                            ->label('Link Google Drive')
                            ->content(fn (?BerkasSiswa $record): string => $record?->resolvedDriveUrl() ?: '-'),
                        Forms\Components\Placeholder::make('google_drive_message')
                            ->label('Pesan Terakhir')
                            ->content(fn (?BerkasSiswa $record): string => $record?->gdrive_upload_message ?: 'Belum ada proses sinkronisasi.')
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return static::optimizeAdminTable(
            $table,
            searchPlaceholder: 'Cari nama siswa, jenis berkas, atau file...',
            emptyStateHeading: 'Belum ada berkas siswa',
            emptyStateDescription: 'Unggah atau import data pendukung siswa agar arsip dokumen tampil di sini.'
        )
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->select([
                'id',
                'siswa_id',
                'jenis_berkas_id',
                'status',
                'file_path',
                'file_name',
                'file_size',
                'mime_type',
                'keterangan',
                'has_deleted',
                'deleted_at',
                'uploaded_by',
                'uploaded_at',
                'updated_at',
                'gdrive_upload_status',
                'gdrive_upload_progress',
                'gdrive_upload_message',
                'gdrive_last_sync_mode',
                'gdrive_uploaded_at',
                'gdrive_folder_url',
                'gdrive_file_url',
            ]))
            ->columns([
                Tables\Columns\TextColumn::make('siswa.nama')
                    ->label('Siswa')
                    ->searchable(),
                Tables\Columns\TextColumn::make('siswa.rombel_saat_ini')
                    ->label('Rombel')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('jenisBerkas.nama_berkas')
                    ->label('Jenis Berkas')
                    ->searchable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'lengkap' => 'success',
                        'kurang' => 'warning',
                        'belum_mengumpulkan' => 'gray',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('file_path')
                    ->label('File')
                    ->searchable()
                    ->state(fn (BerkasSiswa $record): string => $record->displayFileName())
                    ->url(fn (BerkasSiswa $record): ?string => $record->resolvedFileUrl())
                    ->openUrlInNewTab(),
                Tables\Columns\TextColumn::make('gdrive_upload_status')
                    ->label('Google Drive')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => BerkasSiswa::googleDriveStatusLabel($state))
                    ->color(fn (?string $state): string => BerkasSiswa::googleDriveStatusColor($state))
                    ->description(fn (BerkasSiswa $record): string => ((int) ($record->gdrive_upload_progress ?? 0)).'%'),
                Tables\Columns\TextColumn::make('gdrive_last_sync_mode')
                    ->label('Sinkron Terakhir')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => BerkasSiswa::googleDriveSyncModeLabel($state))
                    ->color(fn (?string $state): string => BerkasSiswa::googleDriveSyncModeColor($state))
                    ->toggleable(),
                Tables\Columns\TextColumn::make('uploaded_at')
                    ->label('Upload')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('keterangan')
                    ->limit(30)
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\IconColumn::make('has_deleted')
                    ->label('Deleted')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('siswa_id')
                    ->numeric()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('jenis_berkas_id')
                    ->numeric()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('deleted_at')
                    ->dateTime()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('file_name')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('file_size')
                    ->numeric()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('mime_type')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('uploaded_by')
                    ->numeric()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'lengkap' => 'Lengkap',
                        'kurang' => 'Kurang',
                        'belum_mengumpulkan' => 'Belum Mengumpulkan',
                    ]),
                Tables\Filters\SelectFilter::make('jenis_berkas_id')
                    ->label('Jenis Berkas')
                    ->relationship('jenisBerkas', 'nama_berkas'),
                Tables\Filters\SelectFilter::make('gdrive_last_sync_mode')
                    ->label('Mode Sinkron Terakhir')
                    ->options(BerkasSiswa::googleDriveSyncModeOptions()),
            ])
            ->actions([
                Action::make('upload_google_drive_now')
                    ->label('Upload Sekarang')
                    ->icon('heroicon-o-cloud-arrow-up')
                    ->color('primary')
                    ->visible(fn (BerkasSiswa $record): bool => $record->hasUploadableFiles())
                    ->action(function (BerkasSiswa $record): void {
                        static::uploadGoogleDriveNow($record);
                    }),
                Action::make('buka_file')
                    ->label('Buka File')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->color('gray')
                    ->visible(fn (BerkasSiswa $record): bool => filled($record->resolvedFileUrl()))
                    ->url(fn (BerkasSiswa $record): ?string => $record->resolvedFileUrl())
                    ->openUrlInNewTab(),
                Action::make('buka_drive')
                    ->label('Buka Drive')
                    ->icon('heroicon-o-folder-open')
                    ->color('gray')
                    ->visible(fn (BerkasSiswa $record): bool => filled($record->resolvedDriveUrl()))
                    ->url(fn (BerkasSiswa $record): ?string => $record->resolvedDriveUrl())
                    ->openUrlInNewTab(),
                ActionGroup::make([
                    EditAction::make(),
                    static::makeDeleteTableAction('berkas siswa'),
                ])
                    ->label('Lainnya')
                    ->icon('heroicon-o-ellipsis-horizontal'),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    BulkAction::make('normalize_and_queue_google_drive')
                        ->label('Rapikan + Sinkron Drive')
                        ->icon('heroicon-o-wrench-screwdriver')
                        ->color('info')
                        ->requiresConfirmation()
                        ->modalHeading('Rapikan nama file dan antrekan sinkron ulang?')
                        ->modalDescription('File lokal akan dirapikan ke pola nama baru, lalu record terpilih akan masuk antrean sinkron Google Drive.')
                        ->modalSubmitActionLabel('Rapikan dan antrekan')
                        ->deselectRecordsAfterCompletion()
                        ->action(function (Collection $records): void {
                            $normalized = 0;
                            $queued = 0;
                            $failed = 0;

                            /** @var BerkasSiswa $record */
                            foreach ($records as $record) {
                                try {
                                    if (! $record->hasUploadableFiles()) {
                                        continue;
                                    }

                                    if (static::normalizeRecord($record)) {
                                        $normalized++;
                                    }

                                    static::queueGoogleDriveSync($record->fresh());
                                    $queued++;
                                } catch (Throwable) {
                                    $failed++;
                                }
                            }

                            Notification::make()
                                ->title('Rapikan berkas siswa selesai.')
                                ->body("File dirapikan: {$normalized}, antre sinkron: {$queued}, gagal: {$failed}")
                                ->{$failed > 0 ? 'warning' : 'success'}()
                                ->send();
                        }),
                    static::makeDeleteBulkTableAction(),
                ]),
            ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function enrichStoredFileMetadata(array $data): array
    {
        $path = trim((string) ($data['file_path'] ?? ''));

        if ($path === '') {
            $data['file_size'] = null;
            $data['mime_type'] = null;

            return $data;
        }

        $data['file_name'] ??= basename($path);

        try {
            $data['file_size'] = Storage::disk('public')->size($path);
        } catch (Throwable) {
            $data['file_size'] = $data['file_size'] ?? null;
        }

        try {
            $data['mime_type'] = Storage::disk('public')->mimeType($path) ?: ($data['mime_type'] ?? null);
        } catch (Throwable) {
            $data['mime_type'] = $data['mime_type'] ?? null;
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function normalizeStoredDocument(array $data): array
    {
        $path = trim((string) ($data['file_path'] ?? ''));

        if ($path === '') {
            return static::enrichStoredFileMetadata($data);
        }

        $student = DataSiswa::query()->whereKey($data['siswa_id'] ?? null)->first(['id', 'nama', 'rombel_saat_ini']);
        $documentType = JenisBerkas::query()->whereKey($data['jenis_berkas_id'] ?? null)->value('nama_berkas');
        $extension = ManagedDocumentNaming::extensionFromPath($path);
        $targetFileName = ManagedDocumentNaming::storageFileNameFromParts(
            [$documentType, $student?->nama, $student?->rombel_saat_ini],
            $extension,
        );
        $targetPath = 'berkas_siswa/'.$targetFileName;

        if ($path !== $targetPath && Storage::disk('public')->exists($path)) {
            $targetPath = static::moveStoredFileToNormalizedPath($path, $targetPath);
            $data['file_path'] = $targetPath;
        }

        $data['file_name'] = ManagedDocumentNaming::fileNameFromParts(
            [$documentType, $student?->nama, $student?->rombel_saat_ini],
            $extension,
        );

        return static::enrichStoredFileMetadata($data);
    }

    public static function normalizeRecord(BerkasSiswa $record): bool
    {
        if (! $record->hasUploadableFiles()) {
            return false;
        }

        $payload = static::normalizeStoredDocument([
            'file_path' => $record->file_path,
            'siswa_id' => $record->siswa_id,
            'jenis_berkas_id' => $record->jenis_berkas_id,
            'file_name' => $record->file_name,
            'file_size' => $record->file_size,
            'mime_type' => $record->mime_type,
        ]);

        $changes = [];

        foreach (['file_path', 'file_name', 'file_size', 'mime_type'] as $field) {
            $newValue = $payload[$field] ?? null;

            if ($record->{$field} !== $newValue) {
                $changes[$field] = $newValue;
            }
        }

        if ($changes === []) {
            return false;
        }

        $record->forceFill($changes)->save();

        return true;
    }

    protected static function moveStoredFileToNormalizedPath(string $fromPath, string $targetPath): string
    {
        if ($fromPath === $targetPath) {
            return $targetPath;
        }

        $disk = Storage::disk('public');
        $finalPath = $targetPath;

        if ($disk->exists($finalPath)) {
            $directory = pathinfo($targetPath, PATHINFO_DIRNAME);
            $filename = pathinfo($targetPath, PATHINFO_FILENAME);
            $extension = pathinfo($targetPath, PATHINFO_EXTENSION);
            $suffix = Str::lower(Str::random(4));
            $finalPath = trim($directory !== '.' ? $directory.'/' : '', '/').'/'.$filename.'-'.$suffix.($extension !== '' ? '.'.$extension : '');
        }

        $disk->move($fromPath, $finalPath);

        return $finalPath;
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with([
            'siswa:id,nama,rombel_saat_ini',
            'jenisBerkas:id,nama_berkas',
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBerkasSiswas::route('/'),
            'create' => Pages\CreateBerkasSiswa::route('/create'),
            'edit' => Pages\EditBerkasSiswa::route('/{record}/edit'),
        ];
    }

    public static function queueGoogleDriveSync(BerkasSiswa $record): string
    {
        $status = app(GoogleDriveService::class)->queueBerkasSiswaSync($record);

        Notification::make()
            ->title(match ($status) {
                BerkasSiswa::GDRIVE_STATUS_QUEUED => 'Berkas siswa masuk antrean Google Drive',
                BerkasSiswa::GDRIVE_STATUS_CONFIG_INCOMPLETE => 'Konfigurasi Google Drive belum lengkap',
                BerkasSiswa::GDRIVE_STATUS_INACTIVE => 'Sinkronisasi otomatis Google Drive nonaktif',
                BerkasSiswa::GDRIVE_STATUS_SKIPPED => 'Tidak ada file untuk diunggah',
                default => 'Status Google Drive diperbarui',
            })
            ->body($record->fresh()?->gdrive_upload_message)
            ->{$status === BerkasSiswa::GDRIVE_STATUS_QUEUED ? 'success' : 'warning'}()
            ->send();

        return $status;
    }

    public static function uploadGoogleDriveNow(BerkasSiswa $record): string
    {
        $status = app(GoogleDriveService::class)->uploadBerkasSiswaNow($record);

        Notification::make()
            ->title(match ($status) {
                BerkasSiswa::GDRIVE_STATUS_SYNCED => 'Upload / pemulihan Google Drive selesai',
                BerkasSiswa::GDRIVE_STATUS_CONFIG_INCOMPLETE => 'Konfigurasi Google Drive belum lengkap',
                BerkasSiswa::GDRIVE_STATUS_INACTIVE => 'Google Drive nonaktif',
                BerkasSiswa::GDRIVE_STATUS_SKIPPED => 'Tidak ada file untuk diunggah',
                BerkasSiswa::GDRIVE_STATUS_FAILED => 'Upload Google Drive gagal',
                default => 'Status Google Drive diperbarui',
            })
            ->body($record->fresh()?->gdrive_upload_message)
            ->{$status === BerkasSiswa::GDRIVE_STATUS_SYNCED ? 'success' : ($status === BerkasSiswa::GDRIVE_STATUS_FAILED ? 'danger' : 'warning')}()
            ->send();

        return $status;
    }
}




