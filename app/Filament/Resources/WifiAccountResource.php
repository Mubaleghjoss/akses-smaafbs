<?php

namespace App\Filament\Resources;

use App\Filament\Resources\WifiAccountResource\Pages;
use App\Models\AccountCategory;
use App\Models\HotspotUser;
use App\Support\Hotspot\HotspotAccessible;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

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
