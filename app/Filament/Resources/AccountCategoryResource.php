<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasModulePermissions;
use App\Filament\Resources\AccountCategoryResource\Pages;
use App\Models\AccountCategory;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class AccountCategoryResource extends Resource
{
    use HasModulePermissions;

    protected static ?string $permissionPrefix = 'kategori_akun_guru';

    protected static ?string $model = AccountCategory::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-folder';

    protected static \UnitEnum|string|null $navigationGroup = 'Manajemen Sekolah';

    protected static ?string $navigationLabel = 'Kategori Akun Guru';

    protected static ?string $modelLabel = 'Kategori Akun';

    protected static ?string $pluralModelLabel = 'Kategori Akun Guru';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Forms\Components\TextInput::make('nama')
                    ->label('Nama Kategori')
                    ->required()
                    ->maxLength(100)
                    ->helperText('Contoh: "Email Dinas", "Akun WiFi Cadangan", "Akun Belajar.id".'),
                Forms\Components\Textarea::make('keterangan')
                    ->label('Keterangan')
                    ->rows(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('nama')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('keterangan')
                    ->limit(60)
                    ->wrap()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Update')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
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
            'index' => Pages\ListAccountCategories::route('/'),
            'create' => Pages\CreateAccountCategory::route('/create'),
            'edit' => Pages\EditAccountCategory::route('/{record}/edit'),
        ];
    }
}
