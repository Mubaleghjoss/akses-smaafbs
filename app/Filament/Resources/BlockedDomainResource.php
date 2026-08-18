<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BlockedDomainResource\Pages;
use App\Models\BlockedDomain;
use App\Services\HotspotBlocker;
use App\Services\HotspotManager;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class BlockedDomainResource extends Resource
{
    protected static ?string $model = BlockedDomain::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-shield-exclamation';

    protected static \UnitEnum|string|null $navigationGroup = 'Manajemen Sekolah';

    protected static ?string $navigationLabel = 'Blokir Situs';

    protected static ?string $modelLabel = 'Domain';

    protected static ?string $pluralModelLabel = 'Blokir Situs';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Forms\Components\TextInput::make('domain')
                    ->label('Domain')
                    ->required()
                    ->placeholder('contoh: tiktok.com')
                    ->helperText('Tanpa http:// atau www. Router akan resolve otomatis ke IP lalu diblokir di firewall.'),
                Forms\Components\TextInput::make('note')
                    ->label('Keterangan')
                    ->placeholder('opsional'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('domain')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->copyable(),
                Tables\Columns\TextColumn::make('note')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Ditambahkan')
                    ->dateTime('d/m/Y')
                    ->sortable(),
            ])
            ->actions([
                Tables\Actions\DeleteAction::make()
                    ->requiresConfirmation()
                    ->using(fn (BlockedDomain $record): bool => self::deleteFromRouter($record)),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make()
                    ->requiresConfirmation()
                    ->using(function ($records) {
                        foreach ($records as $record) {
                            self::deleteFromRouter($record);
                        }
                        Notification::make()->title('Domain terpilih dihapus (router + lokal)')->success()->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBlockedDomains::route('/'),
            'create' => Pages\CreateBlockedDomain::route('/create'),
            'edit' => Pages\EditBlockedDomain::route('/{record}/edit'),
        ];
    }

    // ---------- Helper router ----------

    protected static function blocker(): ?HotspotBlocker
    {
        $m = new HotspotManager();
        if (! $m->connect()) {
            Notification::make()->title('Router tidak terhubung: ' . $m->error())->danger()->send();

            return null;
        }
        $b = new HotspotBlocker($m->ros());

        return $b;
    }

    public static function deleteFromRouter(BlockedDomain $record): bool
    {
        $b = self::blocker();
        if ($b === null) {
            return false;
        }
        try {
            $removed = $b->removeDomain($record->domain);
            $record->delete();
            Notification::make()->title("{$record->domain} dihapus" . ($removed > 0 ? " ($removed entri router)" : ''))->success()->send();

            return true;
        } finally {
            $b->ros()?->close();
        }
    }
}