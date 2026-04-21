<?php

namespace App\Filament\Resources\BoardingKeuanganSiswaResource\Pages;

use App\Filament\Resources\BoardingKeuanganSiswaResource;
use App\Models\BoardingKeuanganKategori;
use App\Models\BoardingKeuanganSiswa;
use App\Models\BoardingKeuanganTransaksi;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\RawJs;

class EditBoardingKeuanganSiswa extends EditRecord
{
    protected static string $resource = BoardingKeuanganSiswaResource::class;

    public function getTitle(): string
    {
        $studentName = $this->getRecord()->siswa?->nama;

        return filled($studentName)
            ? 'Transaksi Keuangan: '.$studentName
            : 'Transaksi Keuangan Murid';
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('catatUangMasuk')
                ->label('Catat Uang Masuk')
                ->icon('heroicon-o-arrow-down-circle')
                ->color('success')
                ->slideOver()
                ->modalWidth('lg')
                ->modalSubmitActionLabel('Simpan')
                ->schema($this->incomingTransactionSchema())
                ->action(function (array $data): void {
                    $this->createIncomingTransaction($data);
                }),
            Actions\Action::make('catatUangKeluar')
                ->label('Catat Uang Keluar')
                ->icon('heroicon-o-arrow-up-circle')
                ->color('danger')
                ->slideOver()
                ->modalWidth('lg')
                ->modalSubmitActionLabel('Simpan')
                ->schema($this->outgoingTransactionSchema())
                ->action(function (array $data): void {
                    $this->createOutgoingTransaction($data);
                }),
        ];
    }

    /**
     * @return array<int, Forms\Components\Component>
     */
    protected function incomingTransactionSchema(): array
    {
        return [
            Forms\Components\DatePicker::make('tanggal_transaksi')
                ->label('Tanggal Transaksi')
                ->required()
                ->default(now()),
            Forms\Components\TextInput::make('nominal')
                ->label('Nominal Uang Masuk')
                ->required()
                ->prefix('Rp.')
                ->inputMode('numeric')
                ->mask(RawJs::make('$money($input, \',\', \'.\', 0)'))
                ->stripCharacters([',', '.'])
                ->formatStateUsing(fn ($state): ?string => filled($state) ? number_format((int) $state, 0, ',', '.') : null)
                ->dehydrateStateUsing(fn ($state): int => $this->normalizeNominal($state))
                ->rules(['integer', 'min:0']),
            Forms\Components\Textarea::make('keterangan')
                ->label('Keterangan')
                ->rows(4)
                ->placeholder('Contoh: titipan wali murid atau penambahan saldo')
                ->columnSpanFull(),
        ];
    }

    /**
     * @return array<int, Forms\Components\Component>
     */
    protected function outgoingTransactionSchema(): array
    {
        $categorySchemaAvailable = BoardingKeuanganTransaksi::kategoriRelationAvailable();

        return [
            Forms\Components\DatePicker::make('tanggal_transaksi')
                ->label('Tanggal Transaksi')
                ->required()
                ->default(now()),
            ...($categorySchemaAvailable
                ? [
                    Forms\Components\Select::make('boarding_keuangan_kategori_id')
                        ->label('Kategori Pengeluaran')
                        ->required()
                        ->live()
                        ->default(fn (): ?int => BoardingKeuanganKategori::idBySlug('kas_kamar'))
                        ->searchable()
                        ->getSearchResultsUsing(fn (string $search): array => BoardingKeuanganKategori::searchOptionIds(
                            search: $search,
                            excludeSlugs: BoardingKeuanganTransaksi::INCOMING_CATEGORY_SLUGS,
                        ))
                        ->getOptionLabelUsing(fn ($value): ?string => BoardingKeuanganKategori::resolveOptionIdLabel($value))
                        ->afterStateUpdated(function (Set $set, mixed $state): void {
                            $suggestedNominal = BoardingKeuanganTransaksi::preferredNominalForCategory(
                                keuanganSiswaId: $this->getRecord()->getKey(),
                                kategoriId: filled($state) ? (int) $state : null,
                            );

                            if ($suggestedNominal === null) {
                                return;
                            }

                            $set('nominal', number_format($suggestedNominal, 0, ',', '.'));
                        }),
                ]
                : [
                    Forms\Components\Select::make('jenis_transaksi')
                        ->label('Kategori Pengeluaran')
                        ->required()
                        ->options([
                            'pemberian_uang_saku' => 'Pemberian Uang Saku',
                            'setoran_kas' => 'Setoran Kas',
                        ])
                        ->default('pemberian_uang_saku')
                        ->native(false),
                ]),
            Forms\Components\TextInput::make('nominal')
                ->label('Nominal Uang Keluar')
                ->required()
                ->prefix('Rp.')
                ->inputMode('numeric')
                ->mask(RawJs::make('$money($input, \',\', \'.\', 0)'))
                ->stripCharacters([',', '.'])
                ->formatStateUsing(fn ($state): ?string => filled($state) ? number_format((int) $state, 0, ',', '.') : null)
                ->dehydrateStateUsing(fn ($state): int => $this->normalizeNominal($state))
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
        ];
    }

    protected function createIncomingTransaction(array $data): void
    {
        $attributes = [
            'boarding_keuangan_siswa_id' => $this->getRecord()->getKey(),
            'tanggal_transaksi' => $data['tanggal_transaksi'],
            'nominal' => (int) $data['nominal'],
            'keterangan' => $data['keterangan'] ?? null,
            'arus' => 'masuk',
        ];

        if (BoardingKeuanganTransaksi::kategoriRelationAvailable()) {
            $attributes['boarding_keuangan_kategori_id'] = BoardingKeuanganKategori::idBySlug('kas_umum');
        } else {
            $attributes['jenis_transaksi'] = 'titipan_uang_saku';
        }

        BoardingKeuanganTransaksi::query()->create($attributes);

        $this->refreshPageState();

        Notification::make()
            ->success()
            ->title('Uang masuk berhasil dicatat.')
            ->send();
    }

    protected function createOutgoingTransaction(array $data): void
    {
        $attributes = [
            'boarding_keuangan_siswa_id' => $this->getRecord()->getKey(),
            'tanggal_transaksi' => $data['tanggal_transaksi'],
            'nominal' => (int) $data['nominal'],
            'periode_tahun' => $data['periode_tahun'] ?? null,
            'periode_bulan' => $data['periode_bulan'] ?? null,
            'keterangan' => $data['keterangan'] ?? null,
            'arus' => 'keluar',
        ];

        if (BoardingKeuanganTransaksi::kategoriRelationAvailable()) {
            $attributes['boarding_keuangan_kategori_id'] = (int) $data['boarding_keuangan_kategori_id'];
        } else {
            $attributes['jenis_transaksi'] = $data['jenis_transaksi'] ?? 'pemberian_uang_saku';
        }

        BoardingKeuanganTransaksi::query()->create($attributes);

        $this->refreshPageState();

        Notification::make()
            ->success()
            ->title('Uang keluar berhasil dicatat.')
            ->send();
    }

    protected function refreshPageState(): void
    {
        /** @var BoardingKeuanganSiswa $record */
        $record = $this->getRecord();

        $this->record = BoardingKeuanganSiswa::query()
            ->whereKey($record->getKey())
            ->with('siswa:id,nama,rombel_saat_ini')
            ->withSum([
                'transaksis as titipan_total' => fn ($query) => $query->forSummaryBucket('titipan'),
            ], 'nominal')
            ->withSum([
                'transaksis as pemberian_total' => fn ($query) => $query->forSummaryBucket('pemberian'),
            ], 'nominal')
            ->withSum([
                'transaksis as kas_total' => fn ($query) => $query->forSummaryBucket('kas'),
            ], 'nominal')
            ->withSum([
                'transaksis as custom_total' => fn ($query) => BoardingKeuanganTransaksi::kategoriRelationAvailable()
                    ? $query->whereHas('kategori', fn ($kategoriQuery) => $kategoriQuery->where('is_system', false))
                    : $query->whereRaw('1 = 0'),
            ], 'nominal')
            ->when(
                BoardingKeuanganSiswaResource::pamongOwnerColumnAvailable(),
                fn ($query) => $query->with('pamongUser:id,name'),
            )
            ->firstOrFail();

        $this->fillForm();
        $this->dispatch('refresh-boarding-keuangan-transaksis-relation-manager');
    }

    protected function normalizeNominal(mixed $state): int
    {
        return (int) preg_replace('/\D+/', '', (string) $state);
    }
}
