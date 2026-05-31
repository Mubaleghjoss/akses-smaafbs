<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasModulePermissions;
use App\Filament\Concerns\HasOptimizedAdminTable;
use App\Filament\Resources\BoardingHafalanPointResource\Pages;
use App\Models\BoardingHafalanPoint;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class BoardingHafalanPointResource extends Resource
{
    use HasModulePermissions;
    use HasOptimizedAdminTable;

    protected static ?string $model = BoardingHafalanPoint::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-book-open';

    protected static string|\UnitEnum|null $navigationGroup = 'Boarding';

    protected static ?string $navigationLabel = 'Materi Boarding';

    protected static ?string $modelLabel = 'materi boarding';

    protected static ?string $pluralModelLabel = 'Materi Boarding';

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
                Section::make('Materi Boarding')
                    ->description('Kelola materi hafalan, makna Quran, dan hadits untuk modul pencapaian boarding.')
                    ->columns(['default' => 1, 'md' => 2])
                    ->schema([
                        Forms\Components\Select::make('materi_scope')
                            ->label('Pilihan Materi')
                            ->required()
                            ->native(false)
                            ->live()
                            ->default('boarding')
                            ->options(BoardingHafalanPoint::scopeOptions()),
                        Forms\Components\Select::make('materi_key')
                            ->label('Kelompok Materi')
                            ->required()
                            ->native(false)
                            ->options(fn (Get $get): array => BoardingHafalanPoint::materiOptionsForScope($get('materi_scope')))
                            ->helperText('Pilih kelas materi sesuai pilihan Materi Boarding atau Materi MT.'),
                        Forms\Components\Select::make('jenis')
                            ->label('Jenis Materi')
                            ->options(BoardingHafalanPoint::jenisOptions())
                            ->native(false)
                            ->required(),
                        Forms\Components\TextInput::make('nama_point')
                            ->label('Nama Materi')
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
        return static::optimizeAdminTable(
            $table,
            searchPlaceholder: 'Cari materi hafalan, makna Quran, atau hadits...',
            emptyStateHeading: 'Belum ada materi boarding',
            emptyStateDescription: "Tambahkan materi sesuai Qur'an Bacaan, Qur'an Makna, Hadits Makna, Pengetesan Makna, atau Hafalan."
        )
            ->defaultSort(fn (Builder $query): Builder => $query
                ->orderByRaw(BoardingHafalanPoint::materiOrderSql())
                ->orderByRaw("CASE jenis WHEN 'bacaan_quran' THEN 10 WHEN 'makna_quran' THEN 20 WHEN 'makna_hadits' THEN 30 WHEN 'pengetesan_makna' THEN 40 WHEN 'surat' THEN 50 WHEN 'doa' THEN 60 WHEN 'dalil' THEN 70 ELSE 99 END")
                ->orderBy('urutan')
                ->orderBy('id'))
            ->defaultPaginationPageOption(50)
            ->paginated([25, 50, 100, 200])
            ->reorderable('urutan', condition: function ($livewire): bool {
                $materiKey = data_get($livewire, 'tableFilters.materi_key.value');

                return filled($materiKey);
            })
            ->authorizeReorder(fn (): bool => static::canEdit(null))
            ->groups([
                Group::make('materi_key')
                    ->label('Materi :')
                    ->orderQueryUsing(fn (Builder $query, string $direction): Builder => $query
                        ->orderByRaw(BoardingHafalanPoint::materiOrderSql()." {$direction}")
                        ->orderBy('materi_key', $direction))
                    ->getTitleFromRecordUsing(fn (BoardingHafalanPoint $record): string => BoardingHafalanPoint::materiLabel($record->materi_key)),
            ])
            ->defaultGroup('materi_key')
            ->filtersLayout(FiltersLayout::AboveContent)
            ->columns([
                Tables\Columns\SelectColumn::make('materi_key')
                    ->label('Materi')
                    ->options(BoardingHafalanPoint::allMateriOptions())
                    ->native(false)
                    ->selectablePlaceholder(false)
                    ->rules(['required', 'string', 'max:191'])
                    ->disabled(fn (): bool => ! static::canEdit(null))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\SelectColumn::make('jenis')
                    ->label('Jenis Materi')
                    ->options(BoardingHafalanPoint::jenisOptions())
                    ->native(false)
                    ->selectablePlaceholder(false)
                    ->rules(['required', 'string', 'max:191'])
                    ->disabled(fn (): bool => ! static::canEdit(null))
                    ->searchable()
                    ->sortable()
                    ->visibleFrom('md'),
                Tables\Columns\TextInputColumn::make('nama_point')
                    ->label('Nama Materi')
                    ->rules(['required', 'string', 'max:191'])
                    ->disabled(fn (): bool => ! static::canEdit(null))
                    ->searchable()
                    ->tooltip(function (BoardingHafalanPoint $record): string {
                        return collect([
                            BoardingHafalanPoint::jenisLabel($record->jenis),
                            'Urutan '.(int) $record->urutan,
                            $record->is_active ? 'Aktif' : 'Nonaktif',
                        ])->implode(' | ');
                    }),
                Tables\Columns\TextInputColumn::make('urutan')
                    ->label('Urutan')
                    ->type('number')
                    ->rules(['required', 'integer', 'min:0'])
                    ->extraInputAttributes(['min' => 0, 'inputmode' => 'numeric'])
                    ->disabled(fn (): bool => ! static::canEdit(null))
                    ->sortable()
                    ->alignCenter()
                    ->visibleFrom('md'),
                Tables\Columns\ToggleColumn::make('is_active')
                    ->label('Aktif')
                    ->disabled(fn (): bool => ! static::canEdit(null))
                    ->alignCenter()
                    ->visibleFrom('md'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('materi_key')
                    ->label('Materi :')
                    ->placeholder('Semua materi')
                    ->native(false)
                    ->searchable()
                    ->options(function ($livewire): array {
                        $activeTab = data_get($livewire, 'activeTab', 'boarding');

                        return BoardingHafalanPoint::materiOptionsForScope($activeTab === 'mt' ? 'mt' : 'boarding');
                    }),
                Tables\Filters\SelectFilter::make('jenis')
                    ->label('Jenis Materi')
                    ->options(BoardingHafalanPoint::jenisOptions()),
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Status Aktif'),
            ])
            ->actions([
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
