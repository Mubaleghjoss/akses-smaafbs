<?php

namespace App\Filament\Resources\BoardingKeuanganSiswaResource\RelationManagers;

use App\Filament\Resources\BoardingKeuanganSiswaResource;
use App\Models\BoardingKeuanganKategori;
use App\Models\BoardingKeuanganSiswa;
use App\Models\BoardingKeuanganTransaksi;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\RawJs;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;
use Livewire\Attributes\On;

class TransaksisRelationManager extends RelationManager
{
    protected static string $relationship = 'transaksis';

    protected static ?string $title = 'Riwayat Transaksi Keuangan';

    #[On('refresh-boarding-keuangan-transaksis-relation-manager')]
    public function refreshRelationManager(): void
    {
        $this->resetPage();
        $this->flushCachedTableRecords();
    }

    public function form(Schema $schema): Schema
    {
        $categorySchemaAvailable = BoardingKeuanganTransaksi::kategoriRelationAvailable();

        BoardingKeuanganKategori::ensureBuiltinsSeeded();

        return $schema
            ->schema([
                Section::make('Transaksi Keuangan')
                    ->columns(['default' => 1, 'md' => 2])
                    ->schema([
                        Forms\Components\DatePicker::make('tanggal_transaksi')
                            ->label('Tanggal Transaksi')
                            ->required()
                            ->default(now()),
                        ...($categorySchemaAvailable
                            ? [
                                Forms\Components\Select::make('boarding_keuangan_kategori_id')
                                    ->label('Kategori Keuangan')
                                    ->required()
                                    ->live()
                                    ->searchable()
                                    ->getSearchResultsUsing(fn (string $search): array => BoardingKeuanganKategori::searchOptionIds($search))
                                    ->getOptionLabelUsing(fn ($value): ?string => BoardingKeuanganKategori::resolveOptionIdLabel($value))
                                    ->afterStateUpdated(function (Get $get, Set $set, mixed $state): void {
                                        $suggestedNominal = BoardingKeuanganTransaksi::preferredNominalForCategory(
                                            keuanganSiswaId: $this->getOwnerRecord()->getKey(),
                                            kategoriId: filled($state) ? (int) $state : null,
                                        );

                                        if ($suggestedNominal === null) {
                                            return;
                                        }

                                        $set('nominal', number_format($suggestedNominal, 0, ',', '.'));
                                    })
                                    ->createOptionForm([
                                        Forms\Components\TextInput::make('nama')
                                            ->label('Nama Kategori Baru')
                                            ->required()
                                            ->maxLength(100),
                                        Forms\Components\TextInput::make('default_nominal')
                                            ->label('Nominal Default')
                                            ->prefix('Rp.')
                                            ->inputMode('numeric')
                                            ->mask(RawJs::make('$money($input, \',\', \'.\', 0)'))
                                            ->stripCharacters([',', '.'])
                                            ->formatStateUsing(fn ($state): ?string => filled($state) ? number_format((int) $state, 0, ',', '.') : null)
                                            ->dehydrateStateUsing(fn ($state): ?int => blank($state) ? null : self::normalizeNominal($state))
                                            ->rules(['nullable', 'integer', 'min:0']),
                                    ])
                                    ->createOptionUsing(fn (array $data): int => BoardingKeuanganKategori::createCustom(
                                        $data['nama'],
                                        $data['default_nominal'] ?? null,
                                    )->id),
                                Forms\Components\Hidden::make('jenis_transaksi')
                                    ->dehydrateStateUsing(function (Get $get): ?string {
                                        $categoryId = $get('boarding_keuangan_kategori_id');

                                        if (! $categoryId) {
                                            return null;
                                        }

                                        $slug = BoardingKeuanganKategori::query()->whereKey($categoryId)->value('slug');

                                        return $slug ? 'kategori:'.$slug : null;
                                    }),
                            ]
                            : [
                                Forms\Components\Select::make('jenis_transaksi')
                                    ->label('Kategori Keuangan')
                                    ->required()
                                    ->options([
                                        'titipan_uang_saku' => 'Titipan Uang Saku (Legacy)',
                                        'pemberian_uang_saku' => 'Pemberian Uang Saku (Legacy)',
                                        'setoran_kas' => 'Setoran Kas (Legacy)',
                                    ])
                                    ->native(false),
                            ]),
                        Forms\Components\TextInput::make('nominal')
                            ->label('Nominal')
                            ->required()
                            ->prefix('Rp.')
                            ->inputMode('numeric')
                            ->mask(RawJs::make('$money($input, \',\', \'.\', 0)'))
                            ->stripCharacters([',', '.'])
                            ->formatStateUsing(fn ($state): ?string => filled($state) ? number_format((int) $state, 0, ',', '.') : null)
                            ->dehydrateStateUsing(fn ($state): int => self::normalizeNominal($state))
                            ->rules(['integer', 'min:0']),
                        Forms\Components\TextInput::make('periode_tahun')
                            ->label('Tahun Iuran')
                            ->numeric(),
                        Forms\Components\Select::make('periode_bulan')
                            ->label('Bulan Iuran')
                            ->options(collect(range(1, 12))->mapWithKeys(fn (int $month) => [
                                $month => str_pad((string) $month, 2, '0', STR_PAD_LEFT),
                            ])->all()),
                        Forms\Components\Textarea::make('keterangan')
                            ->label('Keterangan')
                            ->rows(4)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->deferLoading()
            ->defaultPaginationPageOption(10)
            ->paginated([10, 25, 50])
            ->defaultSort('tanggal_transaksi', 'desc')
            ->emptyStateHeading('Belum ada transaksi')
            ->emptyStateDescription('Gunakan tombol "Catat Uang Masuk" atau "Catat Uang Keluar" di bagian atas halaman untuk mulai mencatat transaksi.')
            ->modifyQueryUsing(fn ($query) => BoardingKeuanganTransaksi::kategoriRelationAvailable()
                ? $query
                    ->select([
                        'id',
                        'boarding_keuangan_siswa_id',
                        'tanggal_transaksi',
                        'arus',
                        'jenis_transaksi',
                        'boarding_keuangan_kategori_id',
                        'nominal',
                        'periode_bulan',
                        'periode_tahun',
                        'keterangan',
                        'created_at',
                    ])
                    ->with('kategori:id,nama,slug')
                : $query->select([
                    'id',
                    'boarding_keuangan_siswa_id',
                    'tanggal_transaksi',
                    'arus',
                    'jenis_transaksi',
                    'nominal',
                    'periode_bulan',
                    'periode_tahun',
                    'keterangan',
                    'created_at',
                ]))
            ->columns([
                Tables\Columns\TextColumn::make('tanggal_transaksi')
                    ->label('Tanggal')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('arus_transaksi')
                    ->label('Arus')
                    ->badge()
                    ->state(fn (BoardingKeuanganTransaksi $record): string => $record->isUangMasuk() ? 'Uang Masuk' : 'Uang Keluar')
                    ->color(fn (BoardingKeuanganTransaksi $record): string => $record->isUangMasuk() ? 'success' : 'danger'),
                Tables\Columns\TextColumn::make('jenis_transaksi')
                    ->label('Kategori')
                    ->badge()
                    ->state(fn (BoardingKeuanganTransaksi $record): string => $record->kategori_label),
                Tables\Columns\TextColumn::make('nominal_masuk')
                    ->label('Masuk')
                    ->state(fn (BoardingKeuanganTransaksi $record): string => $record->isUangMasuk()
                        ? BoardingKeuanganSiswa::formatRupiah((int) $record->nominal)
                        : '-')
                    ->color('success')
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy('nominal', $direction)),
                Tables\Columns\TextColumn::make('nominal_keluar')
                    ->label('Keluar')
                    ->state(fn (BoardingKeuanganTransaksi $record): string => ! $record->isUangMasuk()
                        ? BoardingKeuanganSiswa::formatRupiah((int) $record->nominal)
                        : '-')
                    ->color('danger'),
                Tables\Columns\TextColumn::make('periode_label')
                    ->label('Periode Kas')
                    ->visibleFrom('md')
                    ->state(function ($record): string {
                        if (! $record->periode_bulan || ! $record->periode_tahun) {
                            return '-';
                        }

                        return str_pad((string) $record->periode_bulan, 2, '0', STR_PAD_LEFT).'/'.$record->periode_tahun;
                    }),
                Tables\Columns\TextColumn::make('keterangan')
                    ->label('Keterangan')
                    ->limit(60)
                    ->wrap(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Dicatat')
                    ->since()
                    ->tooltip(fn (BoardingKeuanganTransaksi $record): string => $record->created_at?->translatedFormat('d M Y H:i') ?? '-')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\Filter::make('kategori')
                    ->label('Kategori')
                    ->visible(fn (): bool => BoardingKeuanganTransaksi::kategoriRelationAvailable())
                    ->form([
                        Forms\Components\Select::make('slug')
                            ->label('Kategori')
                            ->searchable()
                            ->getSearchResultsUsing(fn (string $search): array => BoardingKeuanganKategori::searchOptionSlugs($search))
                            ->getOptionLabelUsing(fn ($value): ?string => BoardingKeuanganKategori::resolveOptionSlugLabel($value))
                            ->placeholder('Semua kategori'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $slug = $data['slug'] ?? null;

                        if (! filled($slug)) {
                            return $query;
                        }

                        return $query->forCategorySlug((string) $slug);
                    }),
            ])
            ->headerActions([
                Action::make('hapusKategoriCustom')
                    ->label('Hapus Kategori Custom')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->visible(fn (): bool => BoardingKeuanganTransaksi::kategoriRelationAvailable() && BoardingKeuanganKategori::query()->where('is_system', false)->exists())
                    ->schema([
                        Forms\Components\Select::make('kategori_id')
                            ->label('Kategori Custom')
                            ->searchable()
                            ->getSearchResultsUsing(fn (string $search): array => BoardingKeuanganKategori::searchOptionIds(
                                search: $search,
                                system: false,
                            ))
                            ->getOptionLabelUsing(fn ($value): ?string => BoardingKeuanganKategori::resolveOptionIdLabel($value))
                            ->live()
                            ->required(),
                        Forms\Components\Placeholder::make('delete_category_help')
                            ->hiddenLabel()
                            ->content(fn (Get $get): HtmlString|string => $this->categoryDeleteHelp($get('kategori_id'))),
                    ])
                    ->action(function (array $data): void {
                        $kategori = BoardingKeuanganKategori::query()
                            ->where('is_system', false)
                            ->findOrFail($data['kategori_id']);

                        $usageCount = $kategori->usageCount();

                        if ($usageCount > 0) {
                            Notification::make()
                                ->danger()
                                ->title('Kategori belum bisa dihapus')
                                ->body($this->categoryDeleteHelp($kategori->id))
                                ->persistent()
                                ->send();

                            return;
                        }

                        $kategori->delete();

                        Notification::make()
                            ->success()
                            ->title('Kategori berhasil dihapus')
                            ->body('Kategori custom yang tidak dipakai transaksi sudah dihapus.')
                            ->send();
                    }),
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

    protected function getTableDescription(): ?string
    {
        $record = $this->getOwnerRecord();

        return 'Transaksi baru dicatat dari tombol cepat di bagian atas halaman, sedangkan bagian ini dipakai untuk melihat, mengubah, dan menghapus riwayat.'
            .' Saldo saat ini: '.BoardingKeuanganSiswa::formatRupiah((int) $record->saldo_tersisa).'.';
    }

    protected static function normalizeNominal(mixed $state): int
    {
        return (int) preg_replace('/\D+/', '', (string) $state);
    }

    protected function categoryDeleteHelp(?int $categoryId): HtmlString|string
    {
        if (! BoardingKeuanganTransaksi::kategoriRelationAvailable()) {
            return 'Skema kategori belum tersedia pada runtime ini.';
        }

        if (! $categoryId) {
            return 'Pilih kategori custom yang ingin dihapus.';
        }

        $kategori = BoardingKeuanganKategori::query()->find($categoryId);

        if (! $kategori) {
            return 'Kategori tidak ditemukan.';
        }

        $usageCount = $kategori->usageCount();

        if ($usageCount === 0) {
            return 'Kategori ini belum dipakai transaksi dan bisa dihapus.';
        }

        $url = static::filteredCategoryUrl($kategori->slug);

        return new HtmlString(
            'Ada '.$usageCount.' transaksi yang masih memakai kategori ini. '
            .'Silakan <a href="'.$url.'" class="text-primary-600 underline">lihat data terfilter</a> '
            .'lalu ubah transaksi tersebut ke kategori lain terlebih dahulu sebelum menghapus kategori ini.'
        );
    }

    protected static function filteredCategoryUrl(string $slug): string
    {
        return BoardingKeuanganSiswaResource::getUrl('index').'?'.http_build_query([
            'tableFilters' => [
                'kategori_transaksi' => [
                    'slug' => $slug,
                ],
            ],
        ]);
    }
}
