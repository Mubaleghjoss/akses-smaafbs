<?php

namespace App\Filament\Resources\UksRecordResource\Pages;

use App\Filament\Resources\UksRecordResource;
use App\Models\DataSiswa;
use App\Models\UksRecord;
use App\Support\DataSiswa\DataSiswaSupport;
use App\Support\Uks\UksAnthropometrySupport;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Tables;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Livewire\Attributes\Url;

class ManageUksAnthropometry extends Page implements HasTable
{
    use Tables\Concerns\InteractsWithTable;

    #[Url(as: 'anthropometry_filter')]
    public ?string $anthropometryFilter = null;

    protected static string $resource = UksRecordResource::class;

    protected static ?string $title = 'Update Antropometri Murid';

    protected static ?string $breadcrumb = 'Antropometri Murid';

    protected string $view = 'filament.resources.uks-record-resource.pages.manage-uks-anthropometry';

    public function mount(): void
    {
        abort_unless(static::getResource()::canViewAny(), 403);

        if (filled($this->anthropometryFilter)) {
            $this->tableFilters = [
                'periode_ukur' => ['value' => $this->anthropometryFilter],
            ];
        }
    }

    public function getViewData(): array
    {
        $students = UksAnthropometrySupport::activeStudentsQuery(auth()->user())->get();

        return [
            'summaryMetrics' => [
                'total_students' => $students->count(),
                'average_weight' => $this->averageMeasurement($students, 'latest_berat_badan'),
                'average_height' => $this->averageMeasurement($students, 'latest_tinggi_badan'),
                'average_head_circumference' => $this->averageMeasurement($students, 'latest_lingkar_kepala'),
                'unmeasured_this_month' => UksAnthropometrySupport::unmeasuredThisMonthCount(auth()->user()),
            ],
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => UksAnthropometrySupport::activeStudentsQuery(auth()->user()))
            ->defaultSort('nama')
            ->striped()
            ->deferLoading()
            ->defaultPaginationPageOption(10)
            ->paginated([10, 25, 50, 100])
            ->persistSearchInSession()
            ->persistFiltersInSession()
            ->searchDebounce('600ms')
            ->searchPlaceholder('Cari nama murid atau rombel...')
            ->poll(null)
            ->emptyStateHeading('Belum ada murid aktif')
            ->emptyStateDescription('Data murid aktif sesuai scope akan tampil di sini untuk update antropometri.')
            ->columns([
                Tables\Columns\TextColumn::make('nama')
                    ->label('Murid')
                    ->searchable()
                    ->description(fn (DataSiswa $record): string => $record->rombel_saat_ini ?: 'Rombel belum terisi')
                    ->wrap(),
                Tables\Columns\TextColumn::make('status_antropometri')
                    ->label('Status')
                    ->badge()
                    ->state(function (DataSiswa $record): string {
                        return $this->hasMeasurementData($record) ? 'Sudah diukur' : 'Belum ada data';
                    })
                    ->color(fn (DataSiswa $record): string => $this->hasMeasurementData($record) ? 'success' : 'gray')
                    ->description(function (DataSiswa $record): ?string {
                        $date = $record->getAttribute('latest_measurement_date');

                        if (! filled($date)) {
                            return null;
                        }

                        return 'Update '.Carbon::parse($date)->translatedFormat('d M Y');
                    })
                    ->wrap(),
                Tables\Columns\TextColumn::make('ringkasan_antropometri')
                    ->label('Pengukuran Terkini')
                    ->state(fn (DataSiswa $record): string => $this->measurementSummary($record))
                    ->wrap(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('rombel_saat_ini')
                    ->label('Rombel')
                    ->options(fn (): array => DataSiswaSupport::rombelOptions(auth()->user())),
                Tables\Filters\SelectFilter::make('status_ukur')
                    ->label('Status Antropometri')
                    ->options([
                        'ada' => 'Sudah diukur',
                        'belum' => 'Belum ada data',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return match ($data['value'] ?? null) {
                            'ada' => $this->applyMeasurementPresenceFilter($query, true),
                            'belum' => $this->applyMeasurementPresenceFilter($query, false),
                            default => $query,
                        };
                    }),
                Tables\Filters\SelectFilter::make('periode_ukur')
                    ->label('Periode Ukur')
                    ->options([
                        'bulan_ini' => 'Sudah diukur bulan ini',
                        'belum_bulan_ini' => 'Belum diukur bulan ini',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return match ($data['value'] ?? null) {
                            'bulan_ini' => $this->applyMeasurementMonthFilter($query, true),
                            'belum_bulan_ini' => $this->applyMeasurementMonthFilter($query, false),
                            default => $query,
                        };
                    }),
            ])
            ->actions([
                Action::make('updateAnthropometry')
                    ->label('Update')
                    ->icon('heroicon-o-pencil-square')
                    ->color('warning')
                    ->slideOver()
                    ->modalWidth('lg')
                    ->modalHeading(fn (DataSiswa $record): string => 'Update antropometri: '.$record->nama)
                    ->modalDescription('Isi pengukuran terbaru murid. Jika tanggal sama, data antropometri hari itu akan diperbarui.')
                    ->modalSubmitActionLabel('Simpan')
                    ->fillForm(function (DataSiswa $record): array {
                        $date = $record->getAttribute('latest_measurement_date');

                        return [
                            'tanggal_sakit' => filled($date)
                                ? Carbon::parse($date)->toDateString()
                                : now()->toDateString(),
                            'berat_badan' => $record->getAttribute('latest_berat_badan'),
                            'tinggi_badan' => $record->getAttribute('latest_tinggi_badan'),
                            'lingkar_kepala' => $record->getAttribute('latest_lingkar_kepala'),
                            'catatan' => $record->getAttribute('latest_measurement_note'),
                        ];
                    })
                    ->form([
                        Forms\Components\DatePicker::make('tanggal_sakit')
                            ->label('Tanggal Pengukuran')
                            ->default(now()->toDateString())
                            ->required(),
                        Grid::make(['default' => 1, 'md' => 3])
                            ->schema([
                                Forms\Components\TextInput::make('berat_badan')
                                    ->label('Berat (kg)')
                                    ->numeric()
                                    ->step('0.01')
                                    ->minValue(0)
                                    ->required(),
                                Forms\Components\TextInput::make('tinggi_badan')
                                    ->label('Tinggi (cm)')
                                    ->numeric()
                                    ->step('0.01')
                                    ->minValue(0)
                                    ->required(),
                                Forms\Components\TextInput::make('lingkar_kepala')
                                    ->label('Lingkar Kepala (cm)')
                                    ->numeric()
                                    ->step('0.01')
                                    ->minValue(0)
                                    ->required(),
                            ]),
                        Forms\Components\Textarea::make('catatan')
                            ->label('Catatan')
                            ->rows(3)
                            ->placeholder('Opsional')
                            ->columnSpanFull(),
                    ])
                    ->action(function (DataSiswa $record, array $data): void {
                        abort_unless(static::getResource()::canCreate(), 403);

                        $this->saveAnthropometry($record, $data);
                        $this->flushCachedTableRecords();

                        Notification::make()
                            ->title('Antropometri murid tersimpan.')
                            ->success()
                            ->send();
                    })
                    ->visible(fn (): bool => static::getResource()::canCreate()),
            ]);
    }

    protected function applyMeasurementPresenceFilter(Builder $query, bool $hasMeasurements): Builder
    {
        $method = $hasMeasurements ? 'whereExists' : 'whereNotExists';

        return $query->{$method}(function ($subQuery): void {
            $subQuery
                ->selectRaw('1')
                ->from('uks_records')
                ->where(function ($builder): void {
                    if (UksAnthropometrySupport::hasStudentColumn()) {
                        $builder
                            ->whereColumn('uks_records.siswa_id', 'data_siswa.id')
                            ->orWhere(function ($legacyQuery): void {
                                $legacyQuery
                                    ->whereNull('uks_records.siswa_id')
                                    ->whereColumn('uks_records.nama_siswa', 'data_siswa.nama');
                            });

                        return;
                    }

                    $builder->whereColumn('uks_records.nama_siswa', 'data_siswa.nama');
                })
                ->where(function ($builder): void {
                    $builder
                        ->whereNotNull('uks_records.berat_badan')
                        ->orWhereNotNull('uks_records.tinggi_badan')
                        ->orWhereNotNull('uks_records.lingkar_kepala');
                });
        });
    }

    protected function applyMeasurementMonthFilter(Builder $query, bool $measuredThisMonth): Builder
    {
        $method = $measuredThisMonth ? 'whereExists' : 'whereNotExists';
        $monthStart = now()->startOfMonth()->toDateString();
        $nextMonthStart = now()->startOfMonth()->addMonth()->toDateString();

        return $query->{$method}(function ($subQuery) use ($monthStart, $nextMonthStart): void {
            $subQuery
                ->selectRaw('1')
                ->from('uks_records')
                ->where(function ($builder): void {
                    if (UksAnthropometrySupport::hasStudentColumn()) {
                        $builder
                            ->whereColumn('uks_records.siswa_id', 'data_siswa.id')
                            ->orWhere(function ($legacyQuery): void {
                                $legacyQuery
                                    ->whereNull('uks_records.siswa_id')
                                    ->whereColumn('uks_records.nama_siswa', 'data_siswa.nama');
                            });

                        return;
                    }

                    $builder->whereColumn('uks_records.nama_siswa', 'data_siswa.nama');
                })
                ->whereDate('uks_records.tanggal_sakit', '>=', $monthStart)
                ->whereDate('uks_records.tanggal_sakit', '<', $nextMonthStart)
                ->where(function ($builder): void {
                    $builder
                        ->whereNotNull('uks_records.berat_badan')
                        ->orWhereNotNull('uks_records.tinggi_badan')
                        ->orWhereNotNull('uks_records.lingkar_kepala');
                });
        });
    }

    protected function saveAnthropometry(DataSiswa $student, array $data): void
    {
        $query = UksRecord::query()
            ->whereDate('tanggal_sakit', $data['tanggal_sakit'])
            ->where('kategori', UksRecord::ANTHROPOMETRY_CATEGORY)
            ->where(function (Builder $builder) use ($student): void {
                if (UksAnthropometrySupport::hasStudentColumn()) {
                    $builder
                        ->where('siswa_id', $student->getKey())
                        ->orWhere(function (Builder $legacyQuery) use ($student): void {
                            $legacyQuery
                                ->whereNull('siswa_id')
                                ->where('nama_siswa', $student->nama);
                        });

                    return;
                }

                $builder->where('nama_siswa', $student->nama);
            })
            ->latest('id');

        $record = $query->first() ?? new UksRecord();
        $payload = [
            'nama_siswa' => $student->nama,
            'kelas' => $student->rombel_saat_ini,
            'tanggal_sakit' => $data['tanggal_sakit'],
            'kategori' => UksRecord::ANTHROPOMETRY_CATEGORY,
            'penanganan' => UksRecord::ANTHROPOMETRY_HANDLING,
            'berat_badan' => round((float) $data['berat_badan'], 2),
            'tinggi_badan' => round((float) $data['tinggi_badan'], 2),
            'lingkar_kepala' => round((float) $data['lingkar_kepala'], 2),
            'catatan' => filled($data['catatan'] ?? null) ? trim((string) $data['catatan']) : null,
        ];

        if (UksAnthropometrySupport::hasStudentColumn()) {
            $payload['siswa_id'] = $student->getKey();
        }

        if (UksAnthropometrySupport::hasAdminIdColumn() && blank($record->admin_id)) {
            $payload['admin_id'] = auth()->id();
        }

        $record->fill($payload);
        $record->save();
    }

    protected function hasMeasurementData(DataSiswa $student): bool
    {
        return filled($student->getAttribute('latest_berat_badan'))
            || filled($student->getAttribute('latest_tinggi_badan'))
            || filled($student->getAttribute('latest_lingkar_kepala'));
    }

    protected function measurementSummary(DataSiswa $student): string
    {
        $parts = array_filter([
            filled($student->getAttribute('latest_berat_badan'))
                ? 'BB '.number_format((float) $student->getAttribute('latest_berat_badan'), 2, ',', '.').' kg'
                : null,
            filled($student->getAttribute('latest_tinggi_badan'))
                ? 'TB '.number_format((float) $student->getAttribute('latest_tinggi_badan'), 2, ',', '.').' cm'
                : null,
            filled($student->getAttribute('latest_lingkar_kepala'))
                ? 'LK '.number_format((float) $student->getAttribute('latest_lingkar_kepala'), 2, ',', '.').' cm'
                : null,
        ]);

        return $parts !== [] ? implode(' | ', $parts) : 'Belum ada data antropometri';
    }

    protected function averageMeasurement(Collection $students, string $attribute): float
    {
        $average = $students
            ->pluck($attribute)
            ->filter(fn ($value): bool => $value !== null && $value !== '')
            ->map(fn ($value): float => (float) $value)
            ->avg();

        return round((float) ($average ?? 0), 2);
    }
}
