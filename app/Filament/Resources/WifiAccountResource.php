<?php

namespace App\Filament\Resources;

use App\Filament\Resources\WifiAccountResource\Pages;
use App\Models\AccountCategory;
use App\Models\HotspotUser;
use App\Support\Hotspot\HotspotAccessible;
use App\Support\WifiAccount\WifiAccountSyncClient;
use App\Support\WifiAccount\WifiAccountWorkbookImporter;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;
use Throwable;

class WifiAccountResource extends Resource
{
    use HotspotAccessible;

    protected static ?string $model = HotspotUser::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-wifi';

    protected static \UnitEnum|string|null $navigationGroup = 'Manajemen Sekolah';

    protected static ?string $navigationLabel = 'Data Akun WiFi';

    protected static ?string $modelLabel = 'Akun WiFi';

    protected static ?string $pluralModelLabel = 'Data Akun WiFi (Siswa)';

    /** Role yang ditampilkan resource ini; override di subclass Guru. */
    protected static string $accountRole = 'siswa';

    public static function accountRole(): string
    {
        return static::$accountRole;
    }

    public static function canViewAny(): bool
    {
        return self::hotspotAccessGranted();
    }

    public static function canCreate(): bool
    {
        return self::hotspotAccessGranted();
    }

    public static function canEdit($record): bool
    {
        return self::hotspotAccessGranted();
    }

    public static function canDelete($record): bool
    {
        return self::hotspotAccessGranted();
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('role', static::accountRole());
    }

    public static function form(Schema $schema): Schema
    {
        $isGuru = static::accountRole() === 'guru';

        return $schema
            ->schema([
                Forms\Components\Hidden::make('role')->default(static::accountRole()),
                Forms\Components\Hidden::make('input_mode')->default('manual'),
                Forms\Components\TextInput::make('nama')
                    ->label('Nama Pemilik')
                    ->maxLength(150),
                Forms\Components\TextInput::make('username')
                    ->label('Username WiFi')
                    ->required()
                    ->maxLength(64),
                Forms\Components\TextInput::make('password')
                    ->label('Password WiFi')
                    ->required()
                    ->maxLength(64)
                    ->revealable()
                    ->password(),
                Forms\Components\TextInput::make('kelas')
                    ->label($isGuru ? 'Status (guru/tendik)' : 'Kelas')
                    ->maxLength(100)
                    ->helperText($isGuru ? 'Isi "guru" atau "tendik".' : 'Isi kelas siswa, mis. "X IPA 1".'),
                Forms\Components\TextInput::make('profile')
                    ->label('Profil / Grup')
                    ->maxLength(64)
                    ->default('default'),
                Forms\Components\Select::make('category_id')
                    ->label('Kategori (opsional)')
                    ->options(fn (): array => AccountCategory::query()->orderBy('nama')->pluck('nama', 'id')->all())
                    ->searchable()
                    ->visible($isGuru),
            ]);
    }

    public static function table(Table $table): Table
    {
        $isGuru = static::accountRole() === 'guru';

        return $table
            ->columns([
                Tables\Columns\TextColumn::make('nama')
                    ->label('Nama')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('username')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('password')
                    ->label('Password')
                    ->formatStateUsing(fn (): string => '••••••••')
                    ->copyable()
                    ->copyableState(fn (HotspotUser $record): string => (string) $record->password)
                    ->copyMessage('Password disalin')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('kelas')
                    ->label($isGuru ? 'Status' : 'Kelas')
                    ->badge()
                    ->color('gray')
                    ->searchable()
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('profile')
                    ->badge()
                    ->color('gray')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('input_mode')
                    ->label('Sumber')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'otomatis' ? 'success' : 'warning'),
                Tables\Columns\TextColumn::make('category.nama')
                    ->label('Kategori')
                    ->badge()
                    ->toggleable()
                    ->visible($isGuru),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Update')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('input_mode')
                    ->label('Sumber')
                    ->options(['manual' => 'Manual', 'otomatis' => 'Otomatis']),
            ])
            ->headerActions([
                Actions\Action::make('downloadTemplateWifi')
                    ->label('Download Template')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->url(route('admin.wifi-accounts.import-template', absolute: false))
                    ->openUrlInNewTab(),
                Actions\Action::make('importWifiExcel')
                    ->label('Import Excel')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->color('primary')
                    ->modalHeading('Import Akun WiFi (jembatan)')
                    ->modalSubmitActionLabel('Proses Import')
                    ->visible(fn (): bool => self::hotspotAccessGranted())
                    ->form([
                        Forms\Components\FileUpload::make('berkas')
                            ->label('File Excel (USERNAME, PASSWORD, PROFIL, KELAS, ROLE)')
                            ->disk('public')
                            ->directory('wifi/imports')
                            ->acceptedFileTypes([
                                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                                'application/vnd.ms-excel',
                            ])
                            ->required(),
                    ])
                    ->action(function (array $data): void {
                        $disk = Storage::disk('public');
                        $path = $data['berkas'] ?? null;
                        if (! $path || ! $disk->exists($path)) {
                            Notification::make()->title('File import tidak ditemukan.')->danger()->send();

                            return;
                        }
                        try {
                            $result = app(WifiAccountWorkbookImporter::class)->import($disk->path($path));
                        } catch (Throwable $e) {
                            report($e);
                            Notification::make()
                                ->title('Import gagal.')
                                ->body(filled($e->getMessage()) ? $e->getMessage() : 'Gagal membaca file Excel.')
                                ->danger()
                                ->send();

                            return;
                        } finally {
                            if ($disk->exists($path)) {
                                $disk->delete($path);
                            }
                        }

                        Notification::make()
                            ->title('Import akun WiFi selesai.')
                            ->body("{$result['created']} baru, {$result['updated']} diperbarui, {$result['skipped']} dilewati (siswa: {$result['siswa']}, guru: {$result['guru']}).")
                            ->success()
                            ->send();
                    }),
                Actions\Action::make('sinkronApi')
                    ->label('Sinkron dari MikroTik')
                    ->icon('heroicon-o-arrow-path')
                    ->color('info')
                    ->visible(fn (): bool => self::hotspotAccessGranted() && (bool) config('wifi_sync.enabled'))
                    ->requiresConfirmation()
                    ->modalHeading('Sinkron akun WiFi dari aplikasi MikroTik')
                    ->modalDescription('Menarik daftar akun hotspot (read-only), lalu memperbarui data lokal. Tidak menghapus akun.')
                    ->action(function (): void {
                        $client = app(WifiAccountSyncClient::class);
                        try {
                            $accounts = $client->fetchAccounts();
                            $preview = $client->diffPreview($accounts);
                            $applied = $client->apply($accounts);
                        } catch (Throwable $e) {
                            report($e);
                            Notification::make()
                                ->title('Sinkron gagal.')
                                ->body(filled($e->getMessage()) ? $e->getMessage() : 'Tidak dapat menghubungi sumber.')
                                ->danger()
                                ->send();

                            return;
                        }

                        Notification::make()
                            ->title('Sinkron akun WiFi selesai.')
                            ->body("{$applied['created']} baru, {$applied['updated']} diperbarui (preview: {$preview['baru']} baru, {$preview['berubah']} berubah, {$preview['sama']} sama).")
                            ->success()
                            ->send();
                    }),
            ])
            ->actions([
                Actions\EditAction::make(),
                Actions\DeleteAction::make()->requiresConfirmation(),
            ])
            ->bulkActions([
                Actions\DeleteBulkAction::make()->requiresConfirmation(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListWifiAccounts::route('/'),
            'create' => Pages\CreateWifiAccount::route('/create'),
            'edit' => Pages\EditWifiAccount::route('/{record}/edit'),
        ];
    }
}
