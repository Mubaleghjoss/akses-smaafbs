<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasModulePermissions;
use App\Filament\Concerns\HasOptimizedAdminTable;
use App\Filament\Resources\BoardingKeuanganSiswaResource\Pages;
use App\Filament\Resources\BoardingKeuanganSiswaResource\RelationManagers\TransaksisRelationManager;
use App\Models\BoardingKeuanganKategori;
use App\Models\BoardingKeuanganSiswa;
use App\Models\BoardingKeuanganTransaksi;
use App\Models\User;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class BoardingKeuanganSiswaResource extends Resource
{
    use HasModulePermissions;
    use HasOptimizedAdminTable;

    protected static ?string $model = BoardingKeuanganSiswa::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';

    protected static string|\UnitEnum|null $navigationGroup = 'Boarding';

    protected static ?string $navigationLabel = 'Sistem Keuangan Kas';

    protected static ?string $modelLabel = 'sistem keuangan kas';

    protected static ?string $pluralModelLabel = 'Sistem Keuangan Kas';

    protected static ?int $navigationSort = 50;

    protected static ?string $permissionPrefix = 'boarding_keuangan';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Profil Murid Boarding')
                    ->description('Profil ini dibuat otomatis dari data murid aktif. Gunakan halaman ini terutama untuk mencatat transaksi.')
                    ->collapsible()
                    ->collapsed()
                    ->columns(['default' => 1, 'md' => 2])
                    ->schema([
                        Forms\Components\Placeholder::make('murid')
                            ->label('Murid')
                            ->content(fn (?BoardingKeuanganSiswa $record): string => $record?->siswa
                                ? trim($record->siswa->nama.' - '.($record->siswa->rombel_saat_ini ?: 'Tanpa rombel'))
                                : '-'),
                        Forms\Components\Select::make('pamong_user_id')
                            ->label('Pamong Penanggung Jawab')
                            ->relationship(
                                name: 'pamongUser',
                                titleAttribute: 'name',
                                modifyQueryUsing: fn (Builder $query) => User::boardingPamongQuery()->orderBy('name')
                            )
                            ->searchable()
                            ->getSearchResultsUsing(fn (string $search): array => User::searchBoardingPamongOptions($search))
                            ->getOptionLabelUsing(fn ($value): ?string => User::resolveNameOptionLabel($value))
                            ->live()
                            ->afterStateUpdated(function (Set $set, ?string $state): void {
                                $set('pamong_nama', User::query()->whereKey($state)->value('name'));
                            })
                            ->default(fn (): ?int => auth()->user()?->isBoardingPamong() ? auth()->id() : null)
                            ->hidden(fn (): bool => ! static::pamongOwnerColumnAvailable())
                            ->disabled(fn (): bool => (bool) auth()->user()?->isBoardingPamong())
                            ->dehydrated(fn (): bool => static::pamongOwnerColumnAvailable())
                            ->required(fn (): bool => static::pamongOwnerColumnAvailable()),
                        Forms\Components\TextInput::make('pamong_nama')
                            ->label('Pamong Penanggung Jawab')
                            ->maxLength(100)
                            ->disabled()
                            ->dehydrated(),
                        Forms\Components\TextInput::make('angkatan_label')
                            ->label('Angkatan / Kelompok')
                            ->maxLength(100)
                            ->disabled(),
                        Forms\Components\Select::make('kategori_asrama')
                            ->label('Kategori Asrama')
                            ->disabled()
                            ->dehydrated(false)
                            ->options([
                                'putra' => 'Putra',
                                'putri' => 'Putri',
                                'campuran' => 'Campuran',
                            ]),
                        Forms\Components\Textarea::make('catatan')
                            ->label('Catatan')
                            ->rows(4)
                            ->columnSpanFull(),
                    ]),
                Section::make('Ringkasan Saldo Siswa')
                    ->columns(['default' => 1, 'md' => 2, 'xl' => 5])
                    ->visible(fn (?BoardingKeuanganSiswa $record): bool => filled($record))
                    ->schema([
                        Forms\Components\Placeholder::make('summary_titipan')
                            ->label('Kas Umum (Masuk)')
                            ->content(fn (?BoardingKeuanganSiswa $record): string => $record ? BoardingKeuanganSiswa::formatRupiah($record->total_titipan) : BoardingKeuanganSiswa::formatRupiah(0)),
                        Forms\Components\Placeholder::make('summary_pemberian')
                            ->label('Kas Kamar')
                            ->content(fn (?BoardingKeuanganSiswa $record): string => $record ? BoardingKeuanganSiswa::formatRupiah($record->total_pemberian) : BoardingKeuanganSiswa::formatRupiah(0)),
                        Forms\Components\Placeholder::make('summary_kas')
                            ->label('Qurban + Isrun')
                            ->content(fn (?BoardingKeuanganSiswa $record): string => $record ? BoardingKeuanganSiswa::formatRupiah($record->total_kas) : BoardingKeuanganSiswa::formatRupiah(0)),
                        Forms\Components\Placeholder::make('summary_custom')
                            ->label('Kategori Custom')
                            ->content(fn (?BoardingKeuanganSiswa $record): string => $record ? BoardingKeuanganSiswa::formatRupiah($record->total_kategori_custom) : BoardingKeuanganSiswa::formatRupiah(0)),
                        Forms\Components\Placeholder::make('summary_saldo')
                            ->label('Saldo Tersisa')
                            ->content(fn (?BoardingKeuanganSiswa $record): string => $record ? BoardingKeuanganSiswa::formatRupiah($record->saldo_tersisa) : BoardingKeuanganSiswa::formatRupiah(0)),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return static::optimizeAdminTable(
            $table,
            searchPlaceholder: 'Cari murid, pamong, atau kategori asrama...',
            emptyStateHeading: 'Belum ada data keuangan murid aktif',
            emptyStateDescription: 'Data akan muncul otomatis mengikuti murid aktif yang masuk ke scope Anda.'
        )
            ->defaultSort('updated_at', 'desc')
            ->recordUrl(fn (BoardingKeuanganSiswa $record): string => static::getUrl('edit', ['record' => $record]))
            ->columns([
                Tables\Columns\TextColumn::make('siswa.nama')
                    ->label('Murid')
                    ->searchable()
                    ->sortable()
                    ->description(function (BoardingKeuanganSiswa $record): ?string {
                        return collect([
                            $record->siswa?->rombel_saat_ini ? 'Rombel '.$record->siswa->rombel_saat_ini : null,
                            filled($record->pamong_nama) ? 'Pamong '.$record->pamong_nama : null,
                        ])->filter()->implode(' | ') ?: null;
                    })
                    ->wrap(),
                Tables\Columns\TextColumn::make('titipan_total')
                    ->label('Masuk')
                    ->state(fn (BoardingKeuanganSiswa $record): int => $record->total_titipan)
                    ->formatStateUsing(fn (int $state): string => BoardingKeuanganSiswa::formatRupiah($state))
                    ->color('success'),
                Tables\Columns\TextColumn::make('total_keluar_hitung')
                    ->label('Keluar')
                    ->state(fn (BoardingKeuanganSiswa $record): int => $record->total_keluar)
                    ->formatStateUsing(fn (int $state): string => BoardingKeuanganSiswa::formatRupiah($state))
                    ->color('danger'),
                Tables\Columns\TextColumn::make('saldo_tersisa_hitung')
                    ->label('Saldo')
                    ->state(fn (BoardingKeuanganSiswa $record): int => $record->saldo_tersisa)
                    ->formatStateUsing(fn (int $state): string => BoardingKeuanganSiswa::formatRupiah($state))
                    ->color(fn (int $state): string => $state < 0 ? 'danger' : 'success'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('kategori_asrama')
                    ->label('Kategori')
                    ->options([
                        'putra' => 'Putra',
                        'putri' => 'Putri',
                        'campuran' => 'Campuran',
                    ]),
                Tables\Filters\Filter::make('kategori_transaksi')
                    ->label('Kategori Transaksi')
                    ->visible(fn (): bool => static::categorySchemaAvailable())
                    ->form([
                        Forms\Components\Select::make('slug')
                            ->label('Kategori')
                            ->native(false)
                            ->searchable()
                            ->options(fn (): array => static::categorySchemaAvailable()
                                ? BoardingKeuanganKategori::filterSlugOptions(limit: 50)
                                : [])
                            ->getSearchResultsUsing(fn (string $search): array => static::categorySchemaAvailable()
                                ? BoardingKeuanganKategori::filterSlugOptions(search: $search, limit: 50)
                                : [])
                            ->getOptionLabelUsing(fn ($value): ?string => BoardingKeuanganKategori::resolveOptionSlugLabel($value))
                            ->placeholder('Semua kategori'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $slug = $data['slug'] ?? null;

                        if (! filled($slug)) {
                            return $query;
                        }

                        return $query->whereHas(
                            'transaksis',
                            fn (Builder $transaksiQuery) => $transaksiQuery->forCategorySlug((string) $slug)
                        );
                    }),
                Tables\Filters\SelectFilter::make('pamong_nama')
                    ->label('Pamong')
                    ->options(fn (): array => BoardingKeuanganSiswa::pamongNamaOptions(auth()->user()))
                    ->visible(fn (): bool => ! auth()->user()?->isBoardingPamong()),
                Tables\Filters\SelectFilter::make('pamong_user_id')
                    ->label('Owner Pamong')
                    ->relationship(
                        name: 'pamongUser',
                        titleAttribute: 'name',
                        modifyQueryUsing: fn (Builder $query) => User::boardingPamongQuery()->orderBy('name')
                    )
                    ->visible(fn (): bool => static::pamongOwnerColumnAvailable() && ! auth()->user()?->isBoardingPamong()),
            ])
            ->actions([
                EditAction::make()
                    ->label('Transaksi')
                    ->icon('heroicon-o-banknotes'),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            TransaksisRelationManager::class,
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        BoardingKeuanganSiswa::syncVisibleStudentRecordsForUser(auth()->user());
        BoardingKeuanganKategori::ensureBuiltinsSeeded();

        $query = parent::getEloquentQuery()
            ->visibleToUser(auth()->user())
            ->whereHas('siswa', fn (Builder $studentQuery) => $studentQuery->where('status', 'aktif'))
            ->with('siswa:id,nama,rombel_saat_ini')
            ->withSum([
                'transaksis as titipan_total' => fn (Builder $query) => $query->forSummaryBucket('titipan'),
            ], 'nominal')
            ->withSum([
                'transaksis as pemberian_total' => fn (Builder $query) => $query->forSummaryBucket('pemberian'),
            ], 'nominal')
            ->withSum([
                'transaksis as kas_total' => fn (Builder $query) => $query->forSummaryBucket('kas'),
            ], 'nominal')
            ->withSum([
                'transaksis as custom_total' => fn (Builder $query) => BoardingKeuanganTransaksi::kategoriRelationAvailable()
                    ? $query->whereHas('kategori', fn (Builder $kategoriQuery) => $kategoriQuery->where('is_system', false))
                    : $query->whereRaw('1 = 0'),
            ], 'nominal');

        if (static::pamongOwnerColumnAvailable()) {
            $query->with('pamongUser:id,name');
        }

        return $query;
    }

    public static function categorySchemaAvailable(): bool
    {
        return BoardingKeuanganTransaksi::kategoriRelationAvailable();
    }

    public static function pamongOwnerColumnAvailable(): bool
    {
        return BoardingKeuanganSiswa::pamongUserColumnAvailable();
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBoardingKeuanganSiswas::route('/'),
            'edit' => Pages\EditBoardingKeuanganSiswa::route('/{record}/edit'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
