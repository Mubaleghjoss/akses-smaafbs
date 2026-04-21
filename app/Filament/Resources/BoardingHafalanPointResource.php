<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasModulePermissions;
use App\Filament\Resources\BoardingHafalanPointResource\Pages;
use App\Models\BoardingHafalanPoint;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class BoardingHafalanPointResource extends Resource
{
    use HasModulePermissions;

    protected static ?string $model = BoardingHafalanPoint::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-book-open';

    protected static string|\UnitEnum|null $navigationGroup = 'Boarding';

    protected static ?string $navigationLabel = 'Master Hafalan';

    protected static ?string $modelLabel = 'master hafalan';

    protected static ?string $pluralModelLabel = 'Master Hafalan';

    protected static ?int $navigationSort = 25;

    protected static ?string $permissionPrefix = 'boarding_pencapaian';

    public static function shouldRegisterNavigation(): bool
    {
        return static::userCanModule('manage');
    }

    public static function canAccess(): bool
    {
        return static::userCanModule('manage');
    }

    public static function canViewAny(): bool
    {
        return static::userCanModule('manage');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Master Hafalan')
                    ->columns(['default' => 1, 'md' => 2])
                    ->schema([
                        Forms\Components\Select::make('materi_key')
                            ->label('Materi Key')
                            ->required()
                            ->native(false)
                            ->options(BoardingHafalanPoint::MATERI_OPTIONS)
                            ->helperText('Pilih materi hafalan sesuai daftar master.'),
                        Forms\Components\Select::make('jenis')
                            ->label('Jenis')
                            ->options([
                                'surat' => 'Surat',
                                'doa' => 'Doa',
                                'dalil' => 'Dalil',
                            ])
                            ->native(false)
                            ->required(),
                        Forms\Components\TextInput::make('nama_point')
                            ->label('Nama Point')
                            ->required()
                            ->maxLength(191)
                            ->columnSpanFull(),
                        Forms\Components\TextInput::make('urutan')
                            ->label('Urutan')
                            ->numeric()
                            ->minValue(0)
                            ->default(0)
                            ->required(),
                        Forms\Components\Toggle::make('is_active')
                            ->label('Aktif')
                            ->default(true),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort(fn (Builder $query): Builder => $query
                ->orderBy('materi_key')
                ->orderBy('urutan')
                ->orderBy('id'))
            ->reorderable('urutan', condition: function ($livewire): bool {
                $materiKey = data_get($livewire, 'tableFilters.materi_key.value');

                return filled($materiKey);
            })
            ->authorizeReorder(fn (): bool => static::canEdit(null))
            ->columns([
                Tables\Columns\TextColumn::make('materi_key')
                    ->label('Materi')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('jenis')
                    ->label('Jenis')
                    ->badge()
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('nama_point')
                    ->label('Nama Point')
                    ->wrap()
                    ->searchable(),
                Tables\Columns\TextColumn::make('urutan')
                    ->label('Urutan')
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Aktif')
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('materi_key')
                    ->label('Materi')
                    ->options(BoardingHafalanPoint::materiKeyOptions()),
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Status Aktif'),
            ])
            ->actions([
                Action::make('toggle_active')
                    ->label(fn (BoardingHafalanPoint $record): string => $record->is_active ? 'Nonaktifkan' : 'Aktifkan')
                    ->icon(fn (BoardingHafalanPoint $record): string => $record->is_active ? 'heroicon-o-x-circle' : 'heroicon-o-check-circle')
                    ->color(fn (BoardingHafalanPoint $record): string => $record->is_active ? 'warning' : 'success')
                    ->requiresConfirmation()
                    ->modalHeading(fn (BoardingHafalanPoint $record): string => $record->is_active ? 'Nonaktifkan point hafalan?' : 'Aktifkan point hafalan?')
                    ->modalDescription('Point hafalan tidak akan dihapus. Status aktif bisa diubah kembali kapan saja.')
                    ->action(function (BoardingHafalanPoint $record): void {
                        $record->update(['is_active' => ! $record->is_active]);
                    })
                    ->visible(fn (): bool => static::canEdit(null)),
                EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageBoardingHafalanPoints::route('/'),
        ];
    }
}
