<?php

namespace App\Filament\Resources;

use App\Filament\Resources\HotspotUserResource\Pages;
use App\Models\HotspotUser;
use App\Services\HotspotManager;
use App\Support\Hotspot\HotspotAccessible;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class HotspotUserResource extends Resource
{
    use HotspotAccessible;

    protected static ?string $model = HotspotUser::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-users';

    protected static \UnitEnum|string|null $navigationGroup = 'Manajemen Sekolah';

    protected static ?string $navigationLabel = 'Akun Hotspot';

    protected static ?string $modelLabel = 'Akun Hotspot';

    protected static ?string $pluralModelLabel = 'Akun Hotspot';

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

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Forms\Components\TextInput::make('username')
                    ->required()
                    ->maxLength(64),
                Forms\Components\TextInput::make('password')
                    ->required()
                    ->maxLength(64),
                Forms\Components\Select::make('profile')
                    ->label('Grup / Profil')
                    ->options(fn (): array => self::profileOptions())
                    ->default('default'),
                Forms\Components\TextInput::make('durasi')
                    ->label('Durasi (hari, 0 = unlimited)')
                    ->numeric()
                    ->minValue(0)
                    ->default(0),
                Forms\Components\Textarea::make('note')
                    ->label('Catatan')
                    ->rows(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('username')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('password')
                    ->copyable()
                    ->copyMessage('Password disalin')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('profile')
                    ->badge()
                    ->color('gray'),
                Tables\Columns\TextColumn::make('durasi')
                    ->label('Durasi')
                    ->formatStateUsing(fn ($state) => (int) $state > 0 ? "{$state} hari" : 'Unlimited'),
                Tables\Columns\IconColumn::make('disabled')
                    ->label('Status')
                    ->boolean()
                    ->falseIcon('heroicon-o-check-circle')
                    ->trueIcon('heroicon-o-x-circle')
                    ->falseColor('success')
                    ->trueColor('danger'),
                Tables\Columns\TextColumn::make('source')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'both' => 'success',
                        'router' => 'gray',
                        default => 'warning',
                    }),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Update')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('profile'),
                Tables\Filters\TernaryFilter::make('disabled')
                    ->label('Status')
                    ->falseLabel('Aktif')
                    ->trueLabel('Disabled'),
            ])
            ->actions([
                Actions\Action::make('toggle')
                    ->label(fn (HotspotUser $record): string => $record->disabled ? 'Aktifkan' : 'Nonaktifkan')
                    ->icon(fn (HotspotUser $record): string => $record->disabled ? 'heroicon-o-play' : 'heroicon-o-pause')
                    ->color(fn (HotspotUser $record): string => $record->disabled ? 'success' : 'warning')
                    ->requiresConfirmation()
                    ->action(fn (HotspotUser $record) => self::toggleEnabled($record)),
                Actions\EditAction::make(),
                Actions\DeleteAction::make()
                    ->requiresConfirmation()
                    ->modalHeading('Hapus akun dari router & lokal?')
                    ->using(fn (HotspotUser $record): bool => self::deleteOnRouter($record)),
            ])
            ->bulkActions([
                Actions\DeleteBulkAction::make()
                    ->requiresConfirmation()
                    ->using(function ($records) {
                        foreach ($records as $record) {
                            self::deleteOnRouter($record);
                        }
                        Notification::make()->title('Akun terpilih dihapus dari router & lokal')->success()->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListHotspotUsers::route('/'),
            'create' => Pages\CreateHotspotUser::route('/create'),
            'edit' => Pages\EditHotspotUser::route('/{record}/edit'),
        ];
    }

    // ---------- Helper integrasi router ----------

    /** Opsi profil: dari cache (hasil sync), fallback 'default'. */
    public static function profileOptions(): array
    {
        $cached = \App\Models\HhSetting::get('hotspot_profiles', '');
        $profiles = $cached !== '' ? json_decode($cached, true) : [];
        if (! is_array($profiles) || $profiles === []) {
            $profiles = ['default'];
        }

        return array_combine($profiles, $profiles);
    }

    /** Simpan daftar profil dari router ke cache (dipanggil saat "Sync dari Router"). */
    public static function cacheProfiles(HotspotManager $m): void
    {
        $names = $m->profileNames();
        if ($names !== []) {
            \App\Models\HhSetting::set('hotspot_profiles', json_encode($names));
        }
    }

    /** Tambah/update akun ke router + cache profil. */
    public static function syncToRouter(HotspotUser $record, ?string $oldName = null): array
    {
        $m = new HotspotManager();
        if (! $m->connect()) {
            return ['ok' => false, 'msg' => $m->error()];
        }
        try {
            $data = [
                'username' => $record->username,
                'password' => $record->password,
                'profile' => $record->profile,
                'durasi' => (int) $record->durasi,
                'note' => (string) $record->note,
            ];
            $r = $oldName !== null
                ? $m->updateUser($oldName, $data)
                : $m->addUser($data);
            if ($r['ok']) {
                self::cacheProfiles($m);
            }

            return $r;
        } finally {
            $m->close();
        }
    }

    public static function deleteOnRouter(HotspotUser $record): bool
    {
        $m = new HotspotManager();
        if (! $m->connect()) {
            Notification::make()->title('Router tidak terhubung: ' . $m->error())->danger()->send();

            return false;
        }
        try {
            $r = $m->deleteUser($record->username, false);
            if (! $r['ok']) {
                Notification::make()->title('Gagal hapus di router: ' . $r['msg'])->danger()->send();

                return false;
            }
            Notification::make()->title("Akun {$record->username} dihapus dari router & lokal")->success()->send();

            return true;
        } finally {
            $m->close();
        }
    }

    public static function toggleEnabled(HotspotUser $record): void
    {
        $m = new HotspotManager();
        if (! $m->connect()) {
            Notification::make()->title('Router tidak terhubung: ' . $m->error())->danger()->send();

            return;
        }
        try {
            $r = $m->setEnabled($record->username, $record->disabled);
            Notification::make()
                ->title($r['ok'] ? "Akun {$record->username} " . ($record->disabled ? 'diaktifkan' : 'dinonaktifkan') : 'Gagal: ' . $r['msg'])
                ->{$r['ok'] ? 'success' : 'danger'}()
                ->send();
            $record->refresh();
        } finally {
            $m->close();
        }
    }
}