<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasModulePermissions;
use App\Filament\Resources\BelajarIdAccountResource\Pages;
use App\Models\AccountCategory;
use App\Models\BelajarIdAccount;
use App\Support\BelajarId\BelajarIdWorkbookImporter;
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

class BelajarIdAccountResource extends Resource
{
    use HasModulePermissions;

    protected static ?string $permissionPrefix = 'akun_belajar_id_siswa';

    protected static ?string $model = BelajarIdAccount::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-academic-cap';

    protected static \UnitEnum|string|null $navigationGroup = 'Manajemen Sekolah';

    protected static ?string $navigationLabel = 'Data Akun Belajar.id';

    protected static ?string $modelLabel = 'Akun Belajar.id';

    protected static ?string $pluralModelLabel = 'Data Akun Belajar.id (Siswa)';

    /** Role yang ditampilkan resource ini; override di subclass Guru. */
    protected static string $accountRole = 'siswa';

    public static function accountRole(): string
    {
        return static::$accountRole;
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
                Forms\Components\TextInput::make('nama')
                    ->label('Nama')
                    ->required()
                    ->maxLength(150),
                Forms\Components\TextInput::make('status')
                    ->label($isGuru ? 'Status (guru/tendik)' : 'Kelas')
                    ->maxLength(100)
                    ->helperText($isGuru
                        ? 'Isi "guru" atau "tendik".'
                        : 'Isi kelas siswa, mis. "X IPA 1".'),
                Forms\Components\TextInput::make('email')
                    ->label('Email Belajar.id')
                    ->email()
                    ->required()
                    ->maxLength(190)
                    ->unique(ignoreRecord: true),
                Forms\Components\TextInput::make('password')
                    ->label('Password')
                    ->required()
                    ->maxLength(190)
                    ->revealable()
                    ->password(),
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
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('status')
                    ->label($isGuru ? 'Status' : 'Kelas')
                    ->badge()
                    ->color('gray')
                    ->searchable(),
                Tables\Columns\TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->copyable()
                    ->copyMessage('Email disalin'),
                Tables\Columns\TextColumn::make('password')
                    ->label('Password')
                    ->formatStateUsing(fn (): string => '••••••••')
                    ->copyable()
                    ->copyableState(fn (BelajarIdAccount $record): string => (string) $record->password)
                    ->copyMessage('Password disalin')
                    ->toggleable(),
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
            ->headerActions([
                Actions\Action::make('downloadTemplate')
                    ->label('Download Template')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->url(route('admin.belajar-id.import-template', absolute: false))
                    ->openUrlInNewTab(),
                Actions\Action::make('importExcel')
                    ->label('Import Excel')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->color('primary')
                    ->modalHeading('Import Akun Belajar.id')
                    ->modalSubmitActionLabel('Proses Import')
                    ->visible(fn (): bool => static::canCreate())
                    ->form([
                        Forms\Components\FileUpload::make('berkas')
                            ->label('File Excel (kolom: NAMA, STATUS, EMAIL, PASSWORD)')
                            ->disk('public')
                            ->directory('belajar-id/imports')
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
                            $result = app(BelajarIdWorkbookImporter::class)->import($disk->path($path));
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
                            ->title('Import selesai.')
                            ->body("{$result['created']} baru, {$result['updated']} diperbarui, {$result['skipped']} dilewati (siswa: {$result['siswa']}, guru: {$result['guru']}).")
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
            'index' => Pages\ListBelajarIdAccounts::route('/'),
            'create' => Pages\CreateBelajarIdAccount::route('/create'),
            'edit' => Pages\EditBelajarIdAccount::route('/{record}/edit'),
        ];
    }
}
