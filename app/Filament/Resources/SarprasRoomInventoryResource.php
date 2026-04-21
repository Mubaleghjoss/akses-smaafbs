<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasConfirmedDeleteActions;
use App\Filament\Concerns\HasModulePermissions;
use App\Filament\Concerns\HasOptimizedAdminTable;
use App\Filament\Resources\SarprasRoomInventoryResource\Pages;
use App\Models\SarprasRoomInventory;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema as SchemaFacade;

class SarprasRoomInventoryResource extends Resource
{
    use HasConfirmedDeleteActions;
    use HasModulePermissions;
    use HasOptimizedAdminTable;

    protected static ?string $model = SarprasRoomInventory::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-building-office-2';

    protected static string|\UnitEnum|null $navigationGroup = 'Manajemen Sekolah';

    protected static ?string $navigationLabel = 'Daftar Inventaris Ruangan';

    protected static ?string $modelLabel = 'inventaris ruangan';

    protected static ?string $pluralModelLabel = 'Daftar Inventaris Ruangan';

    protected static ?int $navigationSort = 20;

    protected static ?string $permissionPrefix = 'sarpras_room_inventory';

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public static function canAccess(): bool
    {
        return SchemaFacade::hasTable('sarpras_room_inventories')
            && SchemaFacade::hasTable('sarpras_room_inventory_items')
            && parent::canAccess();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Informasi Ruangan')
                    ->description('Simpan identitas gedung dan ruang untuk daftar inventaris yang akan dicatat.')
                    ->columns(['default' => 1, 'md' => 2])
                    ->schema([
                        Forms\Components\TextInput::make('nama_gedung')
                            ->label('Nama Gedung')
                            ->required()
                            ->maxLength(180),
                        Forms\Components\TextInput::make('nama_ruang')
                            ->label('Nama Ruang')
                            ->required()
                            ->maxLength(180),
                        Forms\Components\TextInput::make('nomor_ruang')
                            ->label('Nomor Ruang')
                            ->maxLength(50),
                        Forms\Components\DatePicker::make('tanggal_pendataan')
                            ->label('Tanggal Pendataan')
                            ->native(false)
                            ->closeOnDateSelection(),
                        Forms\Components\TextInput::make('penanggung_jawab')
                            ->label('Kepala Sekolah / Penanggung Jawab')
                            ->maxLength(150),
                        Forms\Components\TextInput::make('diketahui_oleh')
                            ->label('Mengetahui')
                            ->maxLength(150),
                        Forms\Components\Textarea::make('catatan')
                            ->label('Catatan Ruangan')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),
                Section::make('Daftar Barang Ruangan')
                    ->description('Setiap barang bisa dicatat lengkap dengan jumlah, kondisi, dan keterangan tambahan.')
                    ->schema([
                        Forms\Components\Repeater::make('items')
                            ->relationship()
                            ->orderColumn('urutan')
                            ->collapsed()
                            ->reorderableWithButtons()
                            ->cloneable()
                            ->addActionLabel('Tambah Barang')
                            ->schema([
                                Forms\Components\DatePicker::make('tanggal')
                                    ->label('Tanggal')
                                    ->native(false)
                                    ->closeOnDateSelection(),
                                Forms\Components\TextInput::make('nama_barang')
                                    ->label('Nama Barang')
                                    ->required()
                                    ->maxLength(180),
                                Forms\Components\TextInput::make('jumlah')
                                    ->label('Jumlah')
                                    ->numeric()
                                    ->default(1)
                                    ->required()
                                    ->minValue(1),
                                Forms\Components\TextInput::make('kondisi_barang')
                                    ->label('Kondisi Barang')
                                    ->maxLength(100),
                                Forms\Components\TextInput::make('keterangan')
                                    ->label('Keterangan')
                                    ->maxLength(255)
                                    ->columnSpanFull(),
                            ])
                            ->columns(['default' => 1, 'md' => 2]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return static::optimizeAdminTable(
            $table,
            searchPlaceholder: 'Cari nama ruang, gedung, atau penanggung jawab...',
            emptyStateHeading: 'Belum ada inventaris ruangan',
            emptyStateDescription: 'Tambahkan ruangan beserta daftar inventaris barang yang ada di dalamnya.'
        )
            ->defaultSort('tanggal_pendataan', 'desc')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->withCount('items')
                ->orderByDesc('tanggal_pendataan')
                ->orderBy('nama_gedung')
                ->orderBy('nama_ruang'))
            ->columns([
                Tables\Columns\TextColumn::make('nama_ruang')
                    ->label('Ruangan')
                    ->searchable()
                    ->sortable()
                    ->description(fn (SarprasRoomInventory $record): string => collect([
                        $record->nama_gedung,
                        filled($record->nomor_ruang) ? 'No. '.$record->nomor_ruang : null,
                    ])->filter()->implode(' | '))
                    ->wrap(),
                Tables\Columns\TextColumn::make('tanggal_pendataan')
                    ->label('Tanggal')
                    ->date('d/m/Y')
                    ->placeholder('-')
                    ->sortable()
                    ->visibleFrom('md'),
                Tables\Columns\TextColumn::make('items_count')
                    ->label('Barang')
                    ->badge()
                    ->alignCenter()
                    ->sortable(),
                Tables\Columns\TextColumn::make('penanggung_jawab')
                    ->label('PJ')
                    ->searchable()
                    ->wrap()
                    ->visibleFrom('lg'),
                Tables\Columns\TextColumn::make('diketahui_oleh')
                    ->label('Mengetahui')
                    ->wrap()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Diupdate')
                    ->since()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('nama_gedung')
                    ->label('Gedung')
                    ->options(fn (): array => SarprasRoomInventory::gedungOptions()),
            ])
            ->actions([
                Action::make('print')
                    ->label('Print')
                    ->icon('heroicon-o-printer')
                    ->color('gray')
                    ->url(fn (SarprasRoomInventory $record): string => route('admin.sarpras-room-inventories.print', $record))
                    ->openUrlInNewTab(),
                Action::make('export_excel')
                    ->label('Export Excel')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('success')
                    ->url(fn (SarprasRoomInventory $record): string => route('admin.sarpras-room-inventories.export', $record))
                    ->openUrlInNewTab(),
                Action::make('pdf')
                    ->label('PDF')
                    ->icon('heroicon-o-document')
                    ->color('warning')
                    ->url(fn (SarprasRoomInventory $record): string => route('admin.sarpras-room-inventories.pdf', $record))
                    ->openUrlInNewTab(),
                EditAction::make(),
                static::makeDeleteTableAction('inventaris ruangan'),
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
            'index' => Pages\ListSarprasRoomInventories::route('/'),
            'create' => Pages\CreateSarprasRoomInventory::route('/create'),
            'edit' => Pages\EditSarprasRoomInventory::route('/{record}/edit'),
        ];
    }
}
