<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasConfirmedDeleteActions;
use App\Filament\Concerns\HasModulePermissions;
use App\Filament\Concerns\HasOptimizedAdminTable;
use App\Filament\Resources\SarprasBospInventoryResource\Pages;
use App\Models\SarprasBospInventory;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Schema as SchemaFacade;

class SarprasBospInventoryResource extends Resource
{
    use HasConfirmedDeleteActions;
    use HasModulePermissions;
    use HasOptimizedAdminTable;

    protected static ?string $model = SarprasBospInventory::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-archive-box';

    protected static string|\UnitEnum|null $navigationGroup = 'Manajemen Sekolah';

    protected static ?string $navigationLabel = 'Daftar Inventaris BOSP';

    protected static ?string $modelLabel = 'inventaris BOSP';

    protected static ?string $pluralModelLabel = 'Daftar Inventaris BOSP';

    protected static ?int $navigationSort = 10;

    protected static ?string $permissionPrefix = 'sarpras_bosp_inventory';

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public static function canAccess(): bool
    {
        return SchemaFacade::hasTable('sarpras_bosp_inventories') && parent::canAccess();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Informasi Inventaris BOSP')
                    ->description('Catat inventaris yang dibeli melalui BOSP berikut kualitas, kode barang, lokasi, dan nilai belinya.')
                    ->columns(['default' => 1, 'md' => 2])
                    ->schema([
                        Forms\Components\TextInput::make('nomor_urut')
                            ->label('No Urut')
                            ->maxLength(20),
                        Forms\Components\TextInput::make('nama_barang')
                            ->label('Nama Barang')
                            ->required()
                            ->maxLength(180)
                            ->columnSpanFull(),
                        Forms\Components\TextInput::make('quality')
                            ->label('Quality')
                            ->numeric()
                            ->default(1)
                            ->required()
                            ->minValue(1),
                        Forms\Components\TextInput::make('kode_barang')
                            ->label('Kode Barang')
                            ->maxLength(80),
                        Forms\Components\Select::make('bulan_beli')
                            ->label('Bulan Beli')
                            ->options(static::bulanOptions())
                            ->searchable()
                            ->native(false),
                        Forms\Components\TextInput::make('tahun_beli')
                            ->label('Tahun Beli')
                            ->numeric()
                            ->minValue(2000)
                            ->maxValue(2100),
                        Forms\Components\TextInput::make('lokasi_barang')
                            ->label('Lokasi Barang')
                            ->maxLength(180),
                        Forms\Components\TextInput::make('tempat_stiker')
                            ->label('Tempat di Stiker')
                            ->maxLength(180)
                            ->helperText('Opsional. Jika kosong, teks tempat pada stiker memakai Lokasi Barang. Contoh: RUANG IPS, LAB KOMPUTER, RAK TU.'),
                        Forms\Components\DatePicker::make('tanggal_datang')
                            ->label('Tanggal Datang')
                            ->native(false)
                            ->closeOnDateSelection(),
                        Forms\Components\TextInput::make('total_harga')
                            ->label('Total Harga')
                            ->numeric()
                            ->prefix('Rp')
                            ->minValue(0)
                            ->inputMode('decimal'),
                        Forms\Components\Textarea::make('catatan')
                            ->label('Catatan')
                            ->helperText('Isi instruksi peletakan barang untuk dibaca dari QR stiker. Contoh: Tempel stiker di sisi kanan barang, tempatkan di Lab Komputer rak 2, dan jangan dipindah tanpa konfirmasi Sarpras.')
                            ->placeholder('Contoh: Barang ditempatkan di Ruang Lab Komputer, meja guru sisi kanan. Stiker ditempel di bagian belakang atas agar mudah discan.')
                            ->rows(4)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return static::optimizeAdminTable(
            $table,
            searchPlaceholder: 'Cari nama barang, kode barang, atau lokasi...',
            emptyStateHeading: 'Belum ada inventaris BOSP',
            emptyStateDescription: 'Tambahkan daftar barang BOSP agar inventaris sarpras terdokumentasi rapi.'
        )
            ->defaultSort('tanggal_datang', 'desc')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->orderByDesc('tanggal_datang')
                ->orderByDesc('tahun_beli')
                ->orderByDesc('bulan_beli')
                ->orderBy('nomor_urut'))
            ->columns([
                Tables\Columns\TextColumn::make('nomor_urut')
                    ->label('No')
                    ->alignCenter()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('nama_barang')
                    ->label('Nama Barang')
                    ->searchable()
                    ->sortable()
                    ->description(fn (SarprasBospInventory $record): string => collect([
                        filled($record->kode_barang) ? 'Kode '.$record->kode_barang : null,
                        filled($record->tempat_stiker) ? 'Stiker: '.$record->tempat_stiker : $record->lokasi_barang,
                    ])->filter()->implode(' | '))
                    ->wrap(),
                Tables\Columns\TextColumn::make('quality')
                    ->label('Qty')
                    ->badge()
                    ->alignCenter()
                    ->sortable(),
                Tables\Columns\TextColumn::make('periode_beli')
                    ->label('Periode')
                    ->state(fn (SarprasBospInventory $record): string => trim(collect([
                        static::bulanOptions()[$record->bulan_beli] ?? null,
                        $record->tahun_beli,
                    ])->filter()->implode(' ')) ?: '-')
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query
                        ->orderBy('tahun_beli', $direction)
                        ->orderBy('bulan_beli', $direction))
                    ->visibleFrom('md'),
                Tables\Columns\TextColumn::make('tanggal_datang')
                    ->label('Tanggal Datang')
                    ->date('d/m/Y')
                    ->placeholder('-')
                    ->sortable()
                    ->visibleFrom('lg'),
                Tables\Columns\TextColumn::make('total_harga')
                    ->label('Total Harga')
                    ->money('IDR', locale: 'id')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Diupdate')
                    ->since()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('tahun_beli')
                    ->label('Tahun Beli')
                    ->options(fn (): array => SarprasBospInventory::tahunBeliOptions()),
                Tables\Filters\SelectFilter::make('bulan_beli')
                    ->label('Bulan Beli')
                    ->options(static::bulanOptions()),
            ])
            ->actions([
                EditAction::make(),
                Action::make('downloadStickerPdf')
                    ->label('Stiker PDF')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('info')
                    ->url(fn (SarprasBospInventory $record): string => route('admin.sarpras-bosp-inventories.sticker', [
                        'sarprasBospInventory' => $record,
                        'format' => 'pdf',
                    ]))
                    ->openUrlInNewTab(),
                Action::make('downloadStickerPng')
                    ->label('Stiker PNG')
                    ->icon('heroicon-o-photo')
                    ->color('success')
                    ->url(fn (SarprasBospInventory $record): string => route('admin.sarpras-bosp-inventories.sticker', [
                        'sarprasBospInventory' => $record,
                        'format' => 'png',
                    ]))
                    ->openUrlInNewTab(),
                static::makeDeleteTableAction('inventaris BOSP'),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    BulkAction::make('downloadStickersPdf')
                        ->label('Stiker PDF Terpilih')
                        ->icon('heroicon-o-document-arrow-down')
                        ->color('info')
                        ->url(fn (Collection $records): string => route('admin.sarpras-bosp-inventories.stickers', [
                            'selected' => implode(',', $records->modelKeys()),
                            'format' => 'pdf',
                        ]))
                        ->openUrlInNewTab(),
                    BulkAction::make('downloadStickersPng')
                        ->label('Stiker PNG Terpilih')
                        ->icon('heroicon-o-photo')
                        ->color('success')
                        ->url(fn (Collection $records): string => route('admin.sarpras-bosp-inventories.stickers', [
                            'selected' => implode(',', $records->modelKeys()),
                            'format' => 'png',
                        ]))
                        ->openUrlInNewTab(),
                    static::makeDeleteBulkTableAction(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSarprasBospInventories::route('/'),
            'create' => Pages\CreateSarprasBospInventory::route('/create'),
            'edit' => Pages\EditSarprasBospInventory::route('/{record}/edit'),
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function bulanOptions(): array
    {
        return [
            1 => 'Januari',
            2 => 'Februari',
            3 => 'Maret',
            4 => 'April',
            5 => 'Mei',
            6 => 'Juni',
            7 => 'Juli',
            8 => 'Agustus',
            9 => 'September',
            10 => 'Oktober',
            11 => 'November',
            12 => 'Desember',
        ];
    }
}
