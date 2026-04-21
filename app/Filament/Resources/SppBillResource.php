<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasOptimizedAdminTable;
use App\Filament\Concerns\HasConfirmedDeleteActions;
use App\Filament\Resources\SppBillResource\Pages;
use App\Models\DataSiswa;
use App\Models\SppBill;
use App\Models\SppFeeType;
use App\Models\SppSetting;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SppBillResource extends Resource
{
    use HasConfirmedDeleteActions;
    use HasOptimizedAdminTable;

    protected static ?string $model = SppBill::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static string|\UnitEnum|null $navigationGroup = 'SPP';

    protected static ?int $navigationSort = 20;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Tagihan')
                    ->columns(['default' => 1, 'md' => 2])
                    ->schema([
                        Forms\Components\Select::make('siswa_id')
                            ->label('Siswa')
                            ->required()
                            ->searchable()
                            ->getSearchResultsUsing(function (string $search): array {
                                return DataSiswa::query()
                                    ->where('nama', 'like', '%'.$search.'%')
                                    ->orWhere('nipd', 'like', '%'.$search.'%')
                                    ->orWhere('nisn', 'like', '%'.$search.'%')
                                    ->orderBy('nama')
                                    ->limit(50)
                                    ->pluck('nama', 'id')
                                    ->toArray();
                            })
                            ->getOptionLabelUsing(fn ($value): ?string => DataSiswa::query()->whereKey($value)->value('nama')),
                        Forms\Components\Select::make('fee_type_id')
                            ->label('Jenis Biaya')
                            ->searchable()
                            ->options(fn (): array => SppFeeType::searchOptionLabels(limit: 25))
                            ->getSearchResultsUsing(fn (string $search): array => SppFeeType::searchOptionLabels($search))
                            ->getOptionLabelUsing(fn ($value): ?string => SppFeeType::resolveOptionLabel($value))
                            ->live()
                            ->afterStateUpdated(function ($state, callable $set) {
                                if (! $state) {
                                    return;
                                }
                                $amount = SppFeeType::query()->whereKey($state)->value('amount');
                                if (is_numeric($amount)) {
                                    $set('amount', (int) $amount);
                                }
                            })
                            ->nullable(),
                        Forms\Components\Select::make('period_month')
                            ->label('Bulan')
                            ->required()
                            ->options(collect(range(1, 12))->mapWithKeys(fn ($m) => [$m => sprintf('%02d', $m)])->all())
                            ->default((int) now()->format('n')),
                        Forms\Components\TextInput::make('period_year')
                            ->label('Tahun')
                            ->required()
                            ->numeric()
                            ->default((int) now()->format('Y')),
                        Forms\Components\TextInput::make('amount')
                            ->label('Jumlah')
                            ->required()
                            ->numeric(),
                        Forms\Components\TextInput::make('paid_amount')
                            ->label('Terbayar')
                            ->numeric()
                            ->default(0),
                        Forms\Components\DatePicker::make('due_date')
                            ->label('Jatuh Tempo')
                            ->nullable(),
                        Forms\Components\Textarea::make('notes')
                            ->label('Catatan')
                            ->columnSpanFull()
                            ->maxLength(255)
                            ->default(null),
                    ]),

                Section::make('Status')
                    ->columns(['default' => 1, 'md' => 2])
                    ->collapsed()
                    ->schema([
                        Forms\Components\Select::make('status')
                            ->required()
                            ->options([
                                'unpaid' => 'Belum Bayar',
                                'paid' => 'Lunas',
                                'cancelled' => 'Dibatalkan',
                            ])
                            ->default('unpaid'),
                        Forms\Components\Select::make('payment_status')
                            ->required()
                            ->options([
                                'none' => 'None',
                                'partial' => 'Partial',
                                'paid' => 'Paid',
                            ])
                            ->default('none'),
                        Forms\Components\TextInput::make('payment_notes')
                            ->label('Catatan Pembayaran')
                            ->maxLength(255)
                            ->default(null),
                        Forms\Components\DateTimePicker::make('wa_sent_at')
                            ->label('WA Sent At'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return static::optimizeAdminTable(
            $table,
            searchPlaceholder: 'Cari siswa, rombel, nomor WA, billing, atau jenis biaya...',
            emptyStateHeading: 'Belum ada tagihan SPP',
            emptyStateDescription: 'Tagihan akan muncul di sini setelah dibuat dari halaman SPP.'
        )
            ->columns([
                Tables\Columns\TextColumn::make('siswa.nama')
                    ->label('Siswa')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('siswa.rombel_saat_ini')
                    ->label('Rombel')
                    ->searchable(),
                Tables\Columns\TextColumn::make('siswa.wa_ortu')
                    ->label('WA Ortu')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->searchable(),
                Tables\Columns\TextColumn::make('siswa.billing_code')
                    ->label('Billing')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->searchable(),
                Tables\Columns\TextColumn::make('feeType.name')
                    ->label('Jenis')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->searchable(),
                Tables\Columns\TextColumn::make('period_year')
                    ->label('Tahun')
                    ->sortable(),
                Tables\Columns\TextColumn::make('period_month')
                    ->label('Bulan')
                    ->formatStateUsing(fn ($state) => sprintf('%02d', (int) $state))
                    ->sortable(),
                Tables\Columns\TextColumn::make('amount')
                    ->label('Tagihan')
                    ->money('IDR', locale: 'id')
                    ->sortable(),
                Tables\Columns\TextColumn::make('paid_amount')
                    ->label('Terbayar')
                    ->money('IDR', locale: 'id')
                    ->sortable(),
                Tables\Columns\TextColumn::make('payment_status')
                    ->label('Status Bayar')
                    ->badge(),
                Tables\Columns\TextColumn::make('due_date')
                    ->label('Jatuh Tempo')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('wa_sent_count')
                    ->label('WA')
                    ->alignCenter()
                    ->sortable(),
                Tables\Columns\TextColumn::make('wa_sent_at')
                    ->label('WA Terakhir')
                    ->since()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('payment_status')
                    ->label('Status Bayar')
                    ->options([
                        'none' => 'None',
                        'partial' => 'Partial',
                        'paid' => 'Paid',
                    ]),
                Tables\Filters\SelectFilter::make('period_year')
                    ->label('Tahun')
                    ->options(fn () => SppBill::query()->select('period_year')->distinct()->orderByDesc('period_year')->pluck('period_year', 'period_year')->toArray()),
            ])
            ->actions([
                Action::make('bayar')
                    ->label('Bayar')
                    ->icon('heroicon-o-banknotes')
                    ->color('success')
                    ->form([
                        Forms\Components\TextInput::make('wa_ortu')
                            ->label('No WA Ortu')
                            ->helperText('Nomor akan disimpan ke data siswa.')
                            ->default(fn (SppBill $record) => $record->siswa?->wa_ortu)
                            ->required(),
                        Forms\Components\TextInput::make('bayar_sekarang')
                            ->label('Bayar Sekarang')
                            ->numeric()
                            ->minValue(0)
                            ->required(),
                        Forms\Components\Textarea::make('payment_notes')
                            ->label('Catatan Pembayaran')
                            ->maxLength(255)
                            ->nullable(),
                    ])
                    ->action(function (SppBill $record, array $data): void {
                        if ($record->siswa && ($data['wa_ortu'] ?? null) !== null) {
                            $record->siswa->update(['wa_ortu' => (string) $data['wa_ortu']]);
                        }

                        $paidNow = (int) $data['bayar_sekarang'];
                        $newPaid = max(0, ((int) $record->paid_amount) + $paidNow);
                        $amount = (int) $record->amount;

                        $paymentStatus = 'none';
                        $status = 'unpaid';

                        if ($newPaid <= 0) {
                            $paymentStatus = 'none';
                            $status = 'unpaid';
                        } elseif ($newPaid < $amount) {
                            $paymentStatus = 'partial';
                            $status = 'unpaid';
                        } else {
                            $paymentStatus = 'paid';
                            $status = 'paid';
                            $newPaid = $amount;
                        }

                        $record->update([
                            'paid_amount' => $newPaid,
                            'payment_status' => $paymentStatus,
                            'status' => $status,
                            'payment_notes' => $data['payment_notes'] ?? $record->payment_notes,
                        ]);

                        Notification::make()
                            ->title('Pembayaran tersimpan')
                            ->success()
                            ->send();
                    }),

                Action::make('kirim_wa')
                    ->label('Kirim WA')
                    ->icon('heroicon-o-chat-bubble-left-right')
                    ->color('primary')
                    ->form([
                        Forms\Components\TextInput::make('wa_ortu')
                            ->label('No WA Ortu')
                            ->default(fn (SppBill $record) => $record->siswa?->wa_ortu)
                            ->required(),
                        Forms\Components\Textarea::make('message')
                            ->label('Pesan')
                            ->rows(8)
                            ->default(fn (SppBill $record) => self::buildWaMessage($record))
                            ->required(),
                    ])
                    ->modalSubmitActionLabel('Buka WhatsApp')
                    ->action(function (SppBill $record, array $data) {
                        if ($record->siswa && ($data['wa_ortu'] ?? null) !== null) {
                            $record->siswa->update(['wa_ortu' => (string) $data['wa_ortu']]);
                        }

                        $phone = self::normalizePhone((string) $data['wa_ortu']);
                        if ($phone === null) {
                            Notification::make()
                                ->title('No WA tidak valid')
                                ->danger()
                                ->send();

                            return null;
                        }

                        $record->update([
                            'wa_sent_at' => now(),
                            'wa_sent_count' => ((int) ($record->wa_sent_count ?? 0)) + 1,
                        ]);

                        $url = 'https://wa.me/'.$phone.'?text='.urlencode((string) $data['message']);

                        return redirect()->away($url);
                    }),

                EditAction::make(),
                static::makeDeleteTableAction('tagihan SPP'),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    static::makeDeleteBulkTableAction(),
                ]),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with([
            'siswa:id,nama,rombel_saat_ini,wa_ortu,billing_code',
            'feeType:id,name',
        ]);
    }

    protected static function buildWaMessage(SppBill $bill): string
    {
        $student = $bill->siswa;

        $amount = (int) $bill->amount;
        $paid = (int) $bill->paid_amount;
        $remaining = max(0, $amount - $paid);

        $outstandingBills = collect();
        $rincianBiaya = '-';
        $totalTunggakan = $remaining;

        if ($student) {
            $outstandingBills = SppBill::query()
                ->with('feeType')
                ->where('siswa_id', $student->id)
                ->where('status', '!=', 'cancelled')
                ->orderBy('period_year')
                ->orderBy('period_month')
                ->get()
                ->filter(fn (SppBill $b) => ((int) $b->amount - (int) $b->paid_amount) > 0);

            $totalTunggakan = (int) $outstandingBills
                ->sum(fn (SppBill $b) => max(0, (int) $b->amount - (int) $b->paid_amount));

            $rincianBiaya = $outstandingBills
                ->map(function (SppBill $b) {
                    $sisa = max(0, (int) $b->amount - (int) $b->paid_amount);
                    $jenis = (string) ($b->feeType?->name ?: 'Biaya');
                    $periode = sprintf('%02d/%d', (int) $b->period_month, (int) $b->period_year);

                    return "- {$periode} {$jenis}: Rp ".number_format($sisa, 0, ',', '.');
                })
                ->values()
                ->implode("\n");

            if ($rincianBiaya === '') {
                $rincianBiaya = '-';
            }
        }

        $defaultTemplate = "Assalamu'alaikum\n".
            "Info tagihan SPP:\n".
            "Nama: {nama_siswa}\n".
            "Rombel: {rombel}\n".
            "Periode: {bulan}/{tahun}\n".
            "Tagihan: Rp {tagihan}\n".
            "Terbayar: Rp {terbayar}\n".
            "Sisa: Rp {sisa}\n".
            "Status: {status}\n".
            'Billing: {billing_code}';

        $settings = SppSetting::query()->orderBy('id')->first();
        $template = (string) ($settings?->wa_template ?: $defaultTemplate);

        $kodeTagihan = (string) ($student?->billing_code ?? '-');
        $tautan = $kodeTagihan !== '-' ? (url('/tagihan/detail').'?code='.urlencode($kodeTagihan)) : '-';

        $replacements = [
            // template lama
            '{nama_siswa}' => (string) ($student?->nama ?? '-'),
            '{rombel}' => (string) ($student?->rombel_saat_ini ?? '-'),
            '{bulan}' => sprintf('%02d', (int) $bill->period_month),
            '{tahun}' => (string) ((int) $bill->period_year),
            '{tagihan}' => number_format($amount, 0, ',', '.'),
            '{terbayar}' => number_format($paid, 0, ',', '.'),
            '{sisa}' => number_format($remaining, 0, ',', '.'),
            '{status}' => (string) ($bill->payment_status ?? '-'),
            '{billing_code}' => $kodeTagihan,

            // template baru (sesuai pesan Anda)
            '{nama}' => (string) ($student?->nama ?? '-'),
            '{rincian_biaya}' => $rincianBiaya,
            '{total_tunggakan}' => number_format($totalTunggakan, 0, ',', '.'),
            '{jumlah}' => number_format($remaining, 0, ',', '.'),
            '{jatuh_tempo}' => $bill->due_date?->format('d/m/Y') ?? '-',
            '{kode_tagihan}' => $kodeTagihan,
            '{tautan}' => $tautan,

            // bonus kompatibilitas
            '{periode}' => sprintf('%02d %d', (int) $bill->period_month, (int) $bill->period_year),
        ];

        return strtr($template, $replacements);
    }

    protected static function normalizePhone(string $raw): ?string
    {
        $settings = SppSetting::query()->orderBy('id')->first();
        $country = (string) ($settings?->wa_country_code ?: '+62');
        $countryDigits = preg_replace('/\D+/', '', $country) ?: '62';

        $digits = preg_replace('/\D+/', '', $raw) ?? '';
        if ($digits === '') {
            return null;
        }

        // 08xxx -> 628xxx
        if (str_starts_with($digits, '0')) {
            $digits = $countryDigits.substr($digits, 1);
        }

        // +62xxx already becomes 62xxx from regex.
        return $digits;
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSppBills::route('/'),
            'create' => Pages\CreateSppBill::route('/create'),
            'edit' => Pages\EditSppBill::route('/{record}/edit'),
        ];
    }
}
