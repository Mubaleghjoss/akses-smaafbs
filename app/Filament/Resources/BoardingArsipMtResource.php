<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasModulePermissions;
use App\Filament\Resources\BoardingArsipMtResource\Pages;
use App\Filament\Resources\BoardingArsipMtResource\RelationManagers\HistoriesRelationManager;
use App\Models\BoardingArsipMt;
use App\Models\DataSiswa;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;

class BoardingArsipMtResource extends Resource
{
    use HasModulePermissions;

    protected static ?string $model = BoardingArsipMt::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-archive-box';

    protected static string|\UnitEnum|null $navigationGroup = 'Boarding';

    protected static ?string $navigationLabel = 'Arsip MT';

    protected static ?string $modelLabel = 'arsip MT';

    protected static ?string $pluralModelLabel = 'Arsip MT';

    protected static ?int $navigationSort = 30;

    protected static ?string $permissionPrefix = 'boarding_arsip';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Arsip Boarding')
                    ->description('Arsip ini tetap terhubung ke data murid, dan setiap perubahan status akan masuk ke history status arsip.')
                    ->columns(['default' => 1, 'md' => 2])
                    ->schema([
                        Forms\Components\Select::make('siswa_id')
                            ->label('Murid')
                            ->relationship(
                                name: 'siswa',
                                titleAttribute: 'nama',
                                modifyQueryUsing: fn (Builder $query) => DataSiswa::applyVisibleScope($query, auth()->user())->orderBy('nama')
                            )
                            ->getOptionLabelFromRecordUsing(
                                fn (DataSiswa $record): string => trim($record->nama.' - '.($record->rombel_saat_ini ?: 'Tanpa rombel'))
                            )
                            ->searchable()
                            ->unique(ignoreRecord: true)
                            ->required(),
                        Forms\Components\TextInput::make('angkatan_label')
                            ->label('Angkatan')
                            ->maxLength(100),
                        Forms\Components\TextInput::make('tahun_lulus')
                            ->label('Tahun Lulus')
                            ->numeric()
                            ->minValue(2000)
                            ->maxValue(2100),
                        Forms\Components\Select::make('status_arsip')
                            ->label('Status Arsip')
                            ->required()
                            ->default('berangkat_tes')
                            ->options(BoardingArsipMt::statusOptions())
                            ->native(false)
                            ->helperText('Status dapat diubah sewaktu-waktu. Setiap perubahan akan dicatat pada history status arsip.'),
                        Forms\Components\FileUpload::make('arsip_ijazah_path')
                            ->label('Arsip Ijazah')
                            ->disk('public')
                            ->directory('boarding/arsip-mt/ijazah')
                            ->downloadable()
                            ->openable()
                            ->columnSpanFull(),
                        Forms\Components\FileUpload::make('foto_angkatan')
                            ->label('Foto Angkatan')
                            ->disk('public')
                            ->directory('boarding/arsip-mt/foto-angkatan')
                            ->multiple()
                            ->appendFiles()
                            ->reorderable()
                            ->panelLayout('grid')
                            ->downloadable()
                            ->openable()
                            ->columnSpanFull(),
                        Forms\Components\Textarea::make('keterangan')
                            ->label('Keterangan')
                            ->rows(4)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('tahun_lulus', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('siswa.nama')
                    ->label('Murid')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('siswa.rombel_saat_ini')
                    ->label('Rombel')
                    ->searchable(),
                Tables\Columns\TextColumn::make('angkatan_label')
                    ->label('Angkatan')
                    ->searchable(),
                Tables\Columns\TextColumn::make('tahun_lulus')
                    ->label('Tahun')
                    ->sortable(),
                Tables\Columns\TextColumn::make('status_arsip')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => BoardingArsipMt::statusLabel($state)),
                Tables\Columns\TextColumn::make('arsip_ijazah_path')
                    ->label('Ijazah')
                    ->formatStateUsing(fn (?string $state): string => $state ? basename($state) : '-'),
                Tables\Columns\TextColumn::make('foto_count')
                    ->label('Foto')
                    ->state(fn (BoardingArsipMt $record): int => count(Arr::wrap($record->foto_angkatan))),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('tahun_lulus')
                    ->label('Tahun Lulus')
                    ->options(fn (): array => BoardingArsipMt::tahunLulusOptions(auth()->user())),
                Tables\Filters\SelectFilter::make('status_arsip')
                    ->label('Status')
                    ->options(BoardingArsipMt::statusOptions()),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            HistoriesRelationManager::class,
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->select([
                'id',
                'siswa_id',
                'angkatan_label',
                'tahun_lulus',
                'status_arsip',
                'arsip_ijazah_path',
                'foto_angkatan',
            ])
            ->with('siswa:id,nama,rombel_saat_ini')
            ->visibleToUser(auth()->user());
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageBoardingArsipMts::route('/'),
        ];
    }
}
