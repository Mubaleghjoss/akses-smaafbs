<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasConfirmedDeleteActions;
use App\Filament\Concerns\HasModulePermissions;
use App\Filament\Resources\ProkerBidangResource\Pages;
use App\Models\ProkerBidang;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Schema as SchemaFacade;

class ProkerBidangResource extends Resource
{
    use HasConfirmedDeleteActions;
    use HasModulePermissions;

    protected static ?string $model = ProkerBidang::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-building-office-2';

    protected static string|\UnitEnum|null $navigationGroup = 'Manajemen Sekolah';

    protected static ?int $navigationSort = 10;

    protected static ?string $navigationLabel = 'Bidang Proker';

    protected static ?string $modelLabel = 'Bidang Proker';

    protected static ?string $pluralModelLabel = 'Bidang Proker';

    protected static ?string $permissionPrefix = 'proker_bidang';

    public static function shouldRegisterNavigation(): bool
    {
        return static::hasRequiredTables() && parent::canAccess();
    }

    public static function canAccess(): bool
    {
        return static::hasRequiredTables() && parent::canAccess();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Informasi Bidang')
                    ->columns(['default' => 1, 'md' => 2])
                    ->schema([
                        Forms\Components\TextInput::make('nama')
                            ->label('Nama Bidang')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('kode')
                            ->label('Kode')
                            ->maxLength(50)
                            ->unique(ignoreRecord: true),
                        Forms\Components\TextInput::make('penanggung_jawab')
                            ->label('Penanggung Jawab')
                            ->maxLength(255),
                        Forms\Components\Toggle::make('is_active')
                            ->label('Aktif')
                            ->default(true),
                        Forms\Components\Textarea::make('deskripsi')
                            ->label('Deskripsi')
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('nama')
            ->columns([
                Tables\Columns\TextColumn::make('nama')
                    ->label('Bidang')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('kode')
                    ->label('Kode')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('penanggung_jawab')
                    ->label('Penanggung Jawab')
                    ->searchable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Aktif')
                    ->boolean(),
                Tables\Columns\TextColumn::make('prokers_count')
                    ->label('Jumlah Proker')
                    ->counts('prokers'),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Update')
                    ->since()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Status Aktif'),
            ])
            ->actions([
                EditAction::make(),
                static::makeDeleteTableAction('bidang proker'),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    static::makeDeleteBulkTableAction(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProkerBidangs::route('/'),
            'create' => Pages\CreateProkerBidang::route('/create'),
            'edit' => Pages\EditProkerBidang::route('/{record}/edit'),
        ];
    }

    protected static function hasRequiredTables(): bool
    {
        return SchemaFacade::hasTable('proker_bidangs')
            && SchemaFacade::hasTable('prokers');
    }
}
