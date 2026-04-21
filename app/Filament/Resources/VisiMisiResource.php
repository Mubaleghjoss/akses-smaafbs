<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasModulePermissions;
use App\Filament\Resources\VisiMisiResource\Pages;
use App\Models\VisiMisi;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Schema as SchemaFacade;

class VisiMisiResource extends Resource
{
    use HasModulePermissions;

    protected static ?string $model = VisiMisi::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-text';

    protected static string|\UnitEnum|null $navigationGroup = 'Manajemen Sekolah';

    protected static ?int $navigationSort = 35;

    protected static ?string $modelLabel = 'Visi Misi';

    protected static ?string $pluralModelLabel = 'Visi Misi';

    protected static ?string $permissionPrefix = 'visi_misi';

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public static function canAccess(): bool
    {
        return SchemaFacade::hasTable('visi_misis') && parent::canAccess();
    }

    public static function canCreate(): bool
    {
        if (! SchemaFacade::hasTable('visi_misis')) {
            return false;
        }

        return parent::canCreate() && VisiMisi::query()->count() === 0;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Profil Visi Misi Sekolah')
                    ->description('Kelola satu dokumen resmi visi dan misi sekolah yang akan dipakai pada halaman publik.')
                    ->columns(['default' => 1, 'md' => 1])
                    ->schema([
                        Forms\Components\TextInput::make('title')
                            ->label('Judul')
                            ->required()
                            ->maxLength(160),
                        Forms\Components\RichEditor::make('content')
                            ->label('Isi Visi Misi')
                            ->required()
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->label('Judul')
                    ->searchable()
                    ->wrap(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Diupdate')
                    ->since()
                    ->sortable(),
            ])
            ->actions([
                EditAction::make(),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListVisiMisis::route('/'),
            'create' => Pages\CreateVisiMisi::route('/create'),
            'edit' => Pages\EditVisiMisi::route('/{record}/edit'),
        ];
    }
}
