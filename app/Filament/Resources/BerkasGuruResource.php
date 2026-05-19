<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasConfirmedDeleteActions;
use App\Filament\Concerns\HasModulePermissions;
use App\Filament\Concerns\HasOptimizedAdminTable;
use App\Filament\Resources\BerkasGuruResource\Pages;
use App\Models\BerkasGuru;
use App\Models\GuruTendik;
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

class BerkasGuruResource extends Resource
{
    use HasConfirmedDeleteActions;
    use HasModulePermissions;
    use HasOptimizedAdminTable;

    protected static ?string $model = BerkasGuru::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-folder-open';

    protected static string|\UnitEnum|null $navigationGroup = 'Guru/Tendik';

    protected static ?string $navigationLabel = 'Berkas Guru';

    protected static ?string $modelLabel = 'berkas guru';

    protected static ?string $pluralModelLabel = 'Berkas Guru';

    protected static ?int $navigationSort = 30;

    protected static ?string $permissionPrefix = 'berkas_guru';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Berkas Guru')
                    ->columns(['default' => 1, 'md' => 2])
                    ->schema([
                        Forms\Components\Select::make('guru_id')
                            ->label('Guru/Tendik')
                            ->required()
                            ->searchable()
                            ->placeholder('Pilih guru / tendik')
                            ->searchPrompt('Ketik nama, NIY, atau NIK')
                            ->default(fn (): ?int => request()->integer('guru_id') ?: (auth()->user()?->isGuru() ? auth()->user()?->guru_tendik_id : null))
                            ->disabled(fn (): bool => (bool) auth()->user()?->isGuru())
                            ->dehydrated()
                            ->options(fn (): array => GuruTendik::query()
                                ->visibleToUser(auth()->user())
                                ->orderBy('nama')
                                ->limit(50)
                                ->pluck('nama', 'id')
                                ->toArray())
                            ->getSearchResultsUsing(function (string $search): array {
                                return GuruTendik::query()
                                    ->visibleToUser(auth()->user())
                                    ->where(function (Builder $query) use ($search): void {
                                        $query
                                            ->where('nama', 'like', '%'.$search.'%')
                                            ->orWhere('nip', 'like', '%'.$search.'%')
                                            ->orWhere('nik', 'like', '%'.$search.'%');
                                    })
                                    ->orderBy('nama')
                                    ->limit(100)
                                    ->pluck('nama', 'id')
                                    ->toArray();
                            })
                            ->getOptionLabelUsing(fn ($value): ?string => GuruTendik::query()->whereKey($value)->value('nama')),
                        Forms\Components\Select::make('jenis_berkas_id')
                            ->label('Jenis Berkas')
                            ->required()
                            ->searchable()
                            ->options(fn (): array => JenisBerkas::searchOptionLabels(limit: 25))
                            ->getSearchResultsUsing(fn (string $search): array => JenisBerkas::searchOptionLabels($search))
                            ->getOptionLabelUsing(fn ($value): ?string => JenisBerkas::resolveOptionLabel($value)),
                        Forms\Components\FileUpload::make('file_path')
                            ->label('File')
                            ->disk('public')
                            ->directory('berkas_guru')
                            ->maxSize(4096)
                            ->acceptedFileTypes([
                                'application/pdf',
                                'image/jpeg',
                                'image/png',
                            ])
                            ->required(),
                        Forms\Components\TextInput::make('keterangan')
                            ->maxLength(255)
                            ->default(null)
                            ->columnSpanFull(),
                        Forms\Components\Hidden::make('has_deleted')
                            ->default(0),
                    ]),
                Section::make('Sinkron Google Drive')
                    ->description('File tetap tersimpan lokal di project. Jika sinkron otomatis aktif, berkas guru akan masuk antrean background. Jika tidak, admin masih bisa memakai tombol Upload Sekarang secara manual.')
                    ->columns(['default' => 1, 'md' => 2])
                    ->schema([
                        Forms\Components\Placeholder::make('google_drive_status')
                            ->label('Status Upload')
                            ->content(fn (?BerkasGuru $record): string => $record
                                ? BerkasGuru::googleDriveStatusLabel($record->gdrive_upload_status)
                                : 'Akan muncul setelah berkas disimpan.'),
                        Forms\Components\Placeholder::make('google_drive_progress')
                            ->label('Progress')
                            ->content(fn (?BerkasGuru $record): string => $record
                                ? ((int) ($record->gdrive_upload_progress ?? 0)).'%'
                                : '0%'),
                        Forms\Components\Placeholder::make('google_drive_sync_mode')
                            ->label('Hasil Sinkron Terakhir')
                            ->content(fn (?BerkasGuru $record): string => $record
                                ? BerkasGuru::googleDriveSyncModeLabel($record->gdrive_last_sync_mode)
                                : '-'),
                        Forms\Components\Placeholder::make('google_drive_link')
                            ->label('Link Google Drive')
                            ->content(fn (?BerkasGuru $record): string => $record?->resolvedDriveUrl() ?: '-'),
                        Forms\Components\Placeholder::make('google_drive_message')
                            ->label('Pesan Terakhir')
                            ->content(fn (?BerkasGuru $record): string => $record?->gdrive_upload_message ?: 'Belum ada proses sinkronisasi.')
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return static::optimizeAdminTable(
            $table,
            searchPlaceholder: 'Cari guru, jenis berkas, atau file dokumen...',
            emptyStateHeading: 'Belum ada berkas guru',
            emptyStateDescription: 'Unggah dokumen guru agar arsip pribadi dan administrasi lebih aman dan mudah dicari.'
        )
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->select([
                'id',
                'guru_id',
                'jenis_berkas_id',
                'file_path',
                'keterangan',
                'has_deleted',
                'deleted_at',
                'uploaded_at',
                'gdrive_upload_status',
                'gdrive_upload_progress',
                'gdrive_upload_message',
                'gdrive_last_sync_mode',
                'gdrive_uploaded_at',
                'gdrive_folder_url',
                'gdrive_file_url',
            ]))
            ->columns([
                Tables\Columns\TextColumn::make('guru.nama')
                    ->label('Guru/Tendik')
                    ->searchable(),
                Tables\Columns\TextColumn::make('jenisBerkas.nama_berkas')
                    ->label('Jenis Berkas')
                    ->searchable()
                    ->badge(),
                Tables\Columns\TextColumn::make('sumber_data')
                    ->label('Sumber')
                    ->searchable()
                    ->state(fn (BerkasGuru $record): string => $record->isManagedTugasTambahanSk() ? 'History Tugas Tambahan' : 'Berkas Guru')
                    ->badge()
                    ->color(fn (BerkasGuru $record): string => $record->isManagedTugasTambahanSk() ? 'warning' : 'gray'),
                Tables\Columns\TextColumn::make('file_path')
                    ->label('File')
                    ->searchable()
                    ->state(fn (BerkasGuru $record): string => $record->displayFileName())
                    ->url(fn (BerkasGuru $record): ?string => $record->resolvedFileUrl())
                    ->openUrlInNewTab(),
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
                Tables\Columns\TextColumn::make('preview_type')
                    ->label('Tipe')
                    ->searchable()
                    ->state(fn (BerkasGuru $record): string => $record->isPdf() ? 'PDF' : ($record->isImage() ? 'Gambar' : strtoupper($record->fileExtension() ?: '-')))
                    ->badge(),
                Tables\Columns\TextColumn::make('keterangan')
                    ->limit(30)
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\IconColumn::make('has_deleted')
                    ->label('Deleted')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('guru_id')
                    ->numeric()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('jenis_berkas_id')
                    ->numeric()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('deleted_at')
                    ->dateTime()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('jenis_berkas_id')
                    ->label('Jenis Berkas')
                    ->relationship('jenisBerkas', 'nama_berkas'),
                Tables\Filters\SelectFilter::make('gdrive_last_sync_mode')
                    ->label('Mode Sinkron Terakhir')
                    ->options(BerkasGuru::googleDriveSyncModeOptions()),
            ])
            ->actions([
                Action::make('preview')
                    ->label('Preview')
                    ->icon('heroicon-o-eye')
                    ->color('info')
                    ->url(fn (BerkasGuru $record): string => route('admin.berkas-gurus.preview', $record))
                    ->openUrlInNewTab(),
                Action::make('download')
                    ->label('Download')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->url(fn (BerkasGuru $record): string => route('admin.berkas-gurus.download', $record))
                    ->openUrlInNewTab(),
                ActionGroup::make([
                    Action::make('upload_google_drive_now')
                        ->label('Upload Sekarang')
                        ->icon('heroicon-o-cloud-arrow-up')
                        ->color('primary')
                        ->visible(fn (BerkasGuru $record): bool => $record->hasUploadableFiles())
                        ->action(function (BerkasGuru $record): void {
                            static::uploadGoogleDriveNow($record);
                        }),
                    Action::make('buka_drive')
                        ->label('Buka Drive')
                        ->icon('heroicon-o-folder-open')
                        ->color('gray')
                        ->visible(fn (BerkasGuru $record): bool => filled($record->resolvedDriveUrl()))
                        ->url(fn (BerkasGuru $record): ?string => $record->resolvedDriveUrl())
                        ->openUrlInNewTab(),
                    EditAction::make()
                        ->visible(fn (BerkasGuru $record): bool => ! $record->isManagedTugasTambahanSk()),
                    static::makeDeleteTableAction('berkas guru')
                        ->visible(fn (BerkasGuru $record): bool => ! $record->isManagedTugasTambahanSk()),
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

                            /** @var BerkasGuru $record */
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
                                ->title('Rapikan berkas guru selesai.')
                                ->body("File dirapikan: {$normalized}, antre sinkron: {$queued}, gagal: {$failed}")
                                ->{$failed > 0 ? 'warning' : 'success'}()
                                ->send();
                        }),
                    static::makeDeleteBulkTableAction(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function normalizeStoredDocument(array $data): array
    {
        $path = trim((string) ($data['file_path'] ?? ''));

        if ($path === '') {
            return $data;
        }

        $teacherName = GuruTendik::query()->whereKey($data['guru_id'] ?? null)->value('nama');
        $documentType = JenisBerkas::query()->whereKey($data['jenis_berkas_id'] ?? null)->value('nama_berkas');
        $extension = ManagedDocumentNaming::extensionFromPath($path);
        $targetFileName = ManagedDocumentNaming::storageFileNameFromParts(
            [$documentType, $teacherName],
            $extension,
        );
        $targetPath = 'berkas_guru/'.$targetFileName;

        if ($path !== $targetPath && Storage::disk('public')->exists($path)) {
            $targetPath = static::moveStoredFileToNormalizedPath($path, $targetPath);
            $data['file_path'] = $targetPath;
        }

        return $data;
    }

    public static function normalizeStoredRecordDocument(BerkasGuru $record): array
    {
        $path = trim((string) $record->file_path);

        if ($path === '') {
            return ['file_path' => $record->file_path];
        }

        $record->loadMissing([
            'guru:id,nama',
            'jenisBerkas:id,nama_berkas',
            'tugasTambahanHistory:id,berkas_guru_id,tugas_tambahan',
        ]);

        $targetFileName = ManagedDocumentNaming::storageFileNameFromParts(
            $record->documentNameParts(),
            ManagedDocumentNaming::extensionFromPath($path),
        );
        $targetPath = 'berkas_guru/'.$targetFileName;

        if ($path !== $targetPath) {
            if (! Storage::disk('public')->exists($path)) {
                return ['file_path' => $record->file_path];
            }

            $targetPath = static::moveStoredFileToNormalizedPath($path, $targetPath);
        }

        return ['file_path' => $targetPath];
    }

    public static function normalizeRecord(BerkasGuru $record): bool
    {
        if (! $record->hasUploadableFiles()) {
            return false;
        }

        $payload = static::normalizeStoredRecordDocument($record);

        $newPath = $payload['file_path'] ?? null;

        if ($record->file_path === $newPath) {
            return false;
        }

        $record->forceFill([
            'file_path' => $newPath,
        ])->save();

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

    public static function canCreate(): bool
    {
        $user = auth()->user();

        return static::userCanModule('manage')
            && (! $user?->isGuru() || filled($user?->guru_tendik_id));
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with([
                'guru:id,nama',
                'jenisBerkas:id,nama_berkas',
                'tugasTambahanHistory:id,berkas_guru_id,tugas_tambahan',
            ])
            ->visibleToUser(auth()->user());
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBerkasGurus::route('/'),
            'create' => Pages\CreateBerkasGuru::route('/create'),
            'edit' => Pages\EditBerkasGuru::route('/{record}/edit'),
        ];
    }

    public static function queueGoogleDriveSync(BerkasGuru $record): string
    {
        $status = app(GoogleDriveService::class)->queueBerkasGuruSync($record);

        Notification::make()
            ->title(match ($status) {
                BerkasGuru::GDRIVE_STATUS_QUEUED => 'Berkas guru masuk antrean Google Drive',
                BerkasGuru::GDRIVE_STATUS_CONFIG_INCOMPLETE => 'Konfigurasi Google Drive belum lengkap',
                BerkasGuru::GDRIVE_STATUS_INACTIVE => 'Sinkronisasi otomatis Google Drive nonaktif',
                BerkasGuru::GDRIVE_STATUS_SKIPPED => 'Tidak ada file untuk diunggah',
                default => 'Status Google Drive diperbarui',
            })
            ->body($record->fresh()?->gdrive_upload_message)
            ->{$status === BerkasGuru::GDRIVE_STATUS_QUEUED ? 'success' : 'warning'}()
            ->send();

        return $status;
    }

    public static function uploadGoogleDriveNow(BerkasGuru $record): string
    {
        $status = app(GoogleDriveService::class)->uploadBerkasGuruNow($record);

        Notification::make()
            ->title(match ($status) {
                BerkasGuru::GDRIVE_STATUS_SYNCED => 'Upload / pemulihan Google Drive selesai',
                BerkasGuru::GDRIVE_STATUS_CONFIG_INCOMPLETE => 'Konfigurasi Google Drive belum lengkap',
                BerkasGuru::GDRIVE_STATUS_INACTIVE => 'Google Drive nonaktif',
                BerkasGuru::GDRIVE_STATUS_SKIPPED => 'Tidak ada file untuk diunggah',
                BerkasGuru::GDRIVE_STATUS_FAILED => 'Upload Google Drive gagal',
                default => 'Status Google Drive diperbarui',
            })
            ->body($record->fresh()?->gdrive_upload_message)
            ->{$status === BerkasGuru::GDRIVE_STATUS_SYNCED ? 'success' : ($status === BerkasGuru::GDRIVE_STATUS_FAILED ? 'danger' : 'warning')}()
            ->send();

        return $status;
    }
}

