<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasOptimizedAdminTable;
use App\Filament\Concerns\HasModulePermissions;
use App\Filament\Resources\BoardingPencapaianResource\Pages;
use App\Models\BoardingBacaanAssessment;
use App\Models\BoardingHafalanAssessment;
use App\Models\BoardingHafalanPoint;
use App\Models\BoardingMaknaProgress;
use App\Models\BoardingPencapaian;
use App\Models\DataSiswa;
use App\Models\User;
use App\Support\DataSiswa\DataSiswaSupport;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class BoardingPencapaianResource extends Resource
{
    use HasOptimizedAdminTable;
    use HasModulePermissions;

    protected static ?string $model = BoardingPencapaian::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-academic-cap';

    protected static string|\UnitEnum|null $navigationGroup = 'Boarding';

    protected static ?string $navigationLabel = 'Pencapaian Target';

    protected static ?string $modelLabel = 'pencapaian target boarding';

    protected static ?string $pluralModelLabel = 'Pencapaian Target Boarding';

    protected static ?int $navigationSort = 20;

    protected static ?string $permissionPrefix = 'boarding_pencapaian';

    protected const HAFALAN_MATERI_LABELS = [
        'pegon_bacaan' => '1. Kelas Pegon Bacaan : Materi Hafalan',
        'lambatan' => '2. Kelas Lambatan : Materi Hafalan',
        'cepatan' => '3. Kelas Cepatan : Materi Hafalan',
        'materi_tambahan_hafalan' => '4. Kelas Materi Tambahan : Materi Hafalan',
    ];

    protected const MAKNA_MATERI_TARGET_KEYS = [
        'pegon_bacaan' => 'hadits_materi_materi_pegon',
        'lambatan' => 'hadits_materi_materi_lambatan',
        'cepatan' => 'hadits_materi_materi_cepatan',
        'seleksi_saringan' => 'hadits_materi_materi_saringan',
    ];

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Target dan Penanggung Jawab Murid')
                    ->description('Seluruh data target boarding terhubung ke siswa dan pamong penanggung jawabnya.')
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
                            ->helperText('Satu murid menyimpan satu profil pencapaian target yang terus diperbarui.')
                            ->required(),
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
                            ->default(fn (): ?int => auth()->user()?->isBoardingPamong() ? auth()->id() : null)
                            ->disabled(fn (): bool => (bool) auth()->user()?->isBoardingPamong())
                            ->dehydrated()
                            ->required(),
                        Forms\Components\Select::make('materi_rapot_scope')
                            ->label('Target Materi Aktif')
                            ->helperText('Pilih materi yang sedang diikuti murid. Rapot hanya menampilkan pilihan ini.')
                            ->options(BoardingPencapaian::materiRapotScopeOptions())
                            ->default(BoardingPencapaian::MATERI_RAPOT_SCOPE_BOARDING)
                            ->native(false)
                            ->selectablePlaceholder(false)
                            ->required(),
                        Forms\Components\DatePicker::make('tanggal_update_terakhir')
                            ->label('Tanggal Update Terakhir')
                            ->default(now())
                            ->disabled(),
                        Forms\Components\Select::make('status_pencapaian')
                            ->label('Status Pencapaian')
                            ->required()
                            ->default('proses')
                            ->options(BoardingPencapaian::statusOptions())
                            ->helperText('Status akan menyesuaikan otomatis dari detail target dan riwayat update.'),
                        Forms\Components\TextInput::make('target_jumlah_surat')
                            ->label('Target Rekap Surat')
                            ->numeric()
                            ->default(0)
                            ->minValue(0)
                            ->disabled(),
                        Forms\Components\TextInput::make('target_jumlah_doa')
                            ->label('Target Rekap Doa')
                            ->numeric()
                            ->default(0)
                            ->minValue(0)
                            ->disabled(),
                        Forms\Components\TextInput::make('target_jumlah_hadits')
                            ->label('Target Rekap Hadits')
                            ->numeric()
                            ->default(0)
                            ->minValue(0)
                            ->disabled(),
                        Forms\Components\TextInput::make('jumlah_surat_dihafal')
                            ->label('Realisasi Surat')
                            ->numeric()
                            ->default(0)
                            ->minValue(0)
                            ->disabled(),
                        Forms\Components\TextInput::make('jumlah_doa_dihafal')
                            ->label('Realisasi Doa')
                            ->numeric()
                            ->default(0)
                            ->minValue(0)
                            ->disabled(),
                        Forms\Components\TextInput::make('jumlah_hadits_dihafal')
                            ->label('Realisasi Hadits')
                            ->numeric()
                            ->default(0)
                            ->minValue(0)
                            ->disabled(),
                    ]),
                Section::make('Detail Target Boarding')
                    ->description('Gunakan detail target ini untuk mencatat daftar surat, doa, hadits, adab, dan target lain secara lebih rinci.')
                    ->schema([
                        Forms\Components\Repeater::make('details')
                            ->label('Detail Target')
                            ->relationship()
                            ->defaultItems(0)
                            ->collapsible()
                            ->reorderable(false)
                            ->addActionLabel('Tambah Detail Target')
                            ->itemLabel(fn (array $state): ?string => $state['nama_target'] ?? null)
                            ->schema([
                                Forms\Components\TextInput::make('urutan')
                                    ->label('Urutan')
                                    ->numeric()
                                    ->default(0)
                                    ->minValue(0),
                                Forms\Components\Select::make('kategori_detail')
                                    ->label('Kategori Detail')
                                    ->required()
                                    ->options(BoardingPencapaian::detailCategoryOptions()),
                                Forms\Components\TextInput::make('nama_target')
                                    ->label('Nama Target')
                                    ->required()
                                    ->maxLength(150),
                                Forms\Components\TextInput::make('satuan')
                                    ->label('Satuan')
                                    ->placeholder('surat / doa / hadits / kegiatan')
                                    ->maxLength(40),
                                Forms\Components\TextInput::make('target_nilai')
                                    ->label('Target')
                                    ->numeric()
                                    ->default(1)
                                    ->minValue(0)
                                    ->required(),
                                Forms\Components\TextInput::make('capaian_nilai')
                                    ->label('Capaian')
                                    ->numeric()
                                    ->default(0)
                                    ->minValue(0)
                                    ->required(),
                                Forms\Components\Select::make('status_detail')
                                    ->label('Status Detail')
                                    ->required()
                                    ->default('belum_mulai')
                                    ->live()
                                    ->afterStateUpdated(function (Set $set, ?string $state, Get $get): void {
                                        if ($state === 'tuntas' && blank($get('tuntas_at'))) {
                                            $set('tuntas_at', now()->format('Y-m-d'));
                                        }
                                    })
                                    ->options(BoardingPencapaian::detailStatusOptions()),
                                Forms\Components\DatePicker::make('tuntas_at')
                                    ->label('Tanggal Tuntas'),
                                Forms\Components\Textarea::make('detail')
                                    ->label('Keterangan Detail')
                                    ->rows(3)
                                    ->columnSpanFull(),
                            ])
                            ->columns(['default' => 1, 'md' => 2])
                            ->columnSpanFull(),
                    ]),
                Section::make('Riwayat Update Target Boarding')
                    ->description('Pamong dapat menambahkan progres naratif per tanggal. Rekap utama dan status akan tersinkron otomatis dari detail target dan update ini.')
                    ->schema([
                        Forms\Components\Repeater::make('updates')
                            ->label('Riwayat Update')
                            ->relationship()
                            ->defaultItems(0)
                            ->collapsible()
                            ->cloneable(false)
                            ->reorderable(false)
                            ->addActionLabel('Tambah Update Pencapaian')
                            ->itemLabel(fn (array $state): ?string => $state['judul_capaian'] ?? null)
                            ->schema([
                                Forms\Components\DatePicker::make('tanggal_update')
                                    ->label('Tanggal Update')
                                    ->required()
                                    ->default(now()),
                                Forms\Components\Select::make('kategori_update')
                                    ->label('Kategori Update')
                                    ->required()
                                    ->options(BoardingPencapaian::updateCategoryOptions()),
                                Forms\Components\TextInput::make('judul_capaian')
                                    ->label('Judul Capaian')
                                    ->required()
                                    ->maxLength(150),
                                Forms\Components\TextInput::make('jumlah_tambahan')
                                    ->label('Jumlah Tambahan')
                                    ->numeric()
                                    ->default(0)
                                    ->minValue(0),
                                Forms\Components\Select::make('status_update')
                                    ->label('Status Update')
                                    ->required()
                                    ->default('progres')
                                    ->options(BoardingPencapaian::updateStatusOptions()),
                                Forms\Components\TextInput::make('pamong_nama')
                                    ->label('Pamong / Penginput')
                                    ->default(fn (): ?string => auth()->user()?->name)
                                    ->maxLength(100),
                                Forms\Components\Textarea::make('detail')
                                    ->label('Detail Update')
                                    ->rows(3)
                                    ->columnSpanFull(),
                            ])
                            ->columns(['default' => 1, 'md' => 2])
                            ->columnSpanFull(),
                    ]),
                Section::make('Rekap Otomatis')
                    ->columns(['default' => 1, 'md' => 2])
                    ->schema([
                        Forms\Components\Textarea::make('surat_quran_tuntas')
                            ->label('Surat Quran yang Sudah Dituntaskan')
                            ->rows(4)
                            ->disabled()
                            ->columnSpanFull(),
                        Forms\Components\Textarea::make('hadits_tuntas')
                            ->label('Hadits yang Sudah Dituntaskan')
                            ->rows(4)
                            ->disabled()
                            ->columnSpanFull(),
                        Forms\Components\Textarea::make('hafalan_surat')
                            ->label('Surat yang Sudah Dihafal')
                            ->rows(4)
                            ->disabled()
                            ->columnSpanFull(),
                        Forms\Components\Textarea::make('hafalan_doa')
                            ->label('Doa yang Sudah Dihafal')
                            ->rows(4)
                            ->disabled()
                            ->columnSpanFull(),
                        Forms\Components\Textarea::make('hafalan_lainnya')
                            ->label('Hafalan Lainnya')
                            ->rows(4)
                            ->disabled()
                            ->columnSpanFull(),
                        Forms\Components\Textarea::make('target_berikutnya')
                            ->label('Target Berikutnya')
                            ->rows(4)
                            ->disabled()
                            ->columnSpanFull(),
                        Forms\Components\Textarea::make('catatan')
                            ->label('Catatan Pembinaan')
                            ->rows(4)
                            ->disabled()
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return static::optimizeAdminTable(
            $table,
            searchPlaceholder: 'Cari nama murid boarding...',
            emptyStateHeading: 'Belum ada data pencapaian boarding',
            emptyStateDescription: 'Daftar akan otomatis menampilkan semua murid sesuai scope boarding Anda.'
        )
            ->columns([
                Tables\Columns\TextColumn::make('siswa.nama')
                    ->label('Murid')
                    ->searchable()
                    ->wrap(),
                Tables\Columns\SelectColumn::make('materi_rapot_scope')
                    ->label('Target Rapot')
                    ->options(BoardingPencapaian::materiRapotScopeOptions())
                    ->native(false)
                    ->selectablePlaceholder(false)
                    ->rules(['required', 'string', 'max:20'])
                    ->disabled(fn (): bool => ! static::canEdit(null))
                    ->sortable(),
                Tables\Columns\TextColumn::make('ketercapaian_total')
                    ->label('Ketercapaian')
                    ->badge()
                    ->state(fn (BoardingPencapaian $record): string => static::resolveOverallAchievementPercentage($record).'%')
                    ->color(fn (BoardingPencapaian $record): string => static::resolveOverallAchievementColor($record))
                    ->wrap()
                    ->visibleFrom('md'),
                Tables\Columns\TextColumn::make('hafalan_ringkas')
                    ->label('Hafalan')
                    ->state(fn (BoardingPencapaian $record): string => static::resolveHafalanSummary($record))
                    ->wrap()
                    ->visibleFrom('lg'),
                Tables\Columns\TextColumn::make('makna_ringkas')
                    ->label('Makna')
                    ->state(fn (BoardingPencapaian $record): string => static::resolveMaknaSummary($record))
                    ->wrap()
                    ->visibleFrom('lg')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('bacaan_ringkas')
                    ->label('Bacaan')
                    ->state(fn (BoardingPencapaian $record): string => static::resolveBacaanSummary($record))
                    ->wrap()
                    ->visibleFrom('xl')
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('siswa_rombel')
                    ->label('Kelas')
                    ->placeholder('Semua kelas')
                    ->native(false)
                    ->searchable()
                    ->options(fn (): array => DataSiswaSupport::rombelOptions(auth()->user()))
                    ->query(function (Builder $query, array $data): Builder {
                        if (! filled($data['value'] ?? null)) {
                            return $query;
                        }

                        return $query->whereHas('siswa', fn (Builder $siswaQuery): Builder => $siswaQuery
                            ->where('rombel_saat_ini', $data['value']));
                    }),
                Tables\Filters\SelectFilter::make('siswa_jk')
                    ->label('Jenis Kelamin')
                    ->placeholder('Semua JK')
                    ->native(false)
                    ->options([
                        'L' => 'Laki-laki',
                        'P' => 'Perempuan',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        if (! filled($data['value'] ?? null)) {
                            return $query;
                        }

                        return $query->whereHas('siswa', fn (Builder $siswaQuery): Builder => $siswaQuery
                            ->where('jk', $data['value']));
                    }),
                Tables\Filters\SelectFilter::make('materi_rapot_scope')
                    ->label('Target Rapot')
                    ->options(BoardingPencapaian::materiRapotScopeOptions())
                    ->native(false),
            ])
            ->recordUrl(fn (BoardingPencapaian $record): string => BoardingPencapaian::normalizeMateriRapotScope($record->materi_rapot_scope) === BoardingPencapaian::MATERI_RAPOT_SCOPE_MT
                ? static::getUrl('mt', ['record' => $record])
                : static::getUrl('materi', ['record' => $record]))
            ->actions([
                Action::make('materi')
                    ->label('Materi')
                    ->icon('heroicon-o-clipboard-document-check')
                    ->color(fn (BoardingPencapaian $record): string => BoardingPencapaian::normalizeMateriRapotScope($record->materi_rapot_scope) === BoardingPencapaian::MATERI_RAPOT_SCOPE_MT
                        ? 'success'
                        : 'primary')
                    ->url(fn (BoardingPencapaian $record): string => BoardingPencapaian::normalizeMateriRapotScope($record->materi_rapot_scope) === BoardingPencapaian::MATERI_RAPOT_SCOPE_MT
                        ? static::getUrl('mt', ['record' => $record])
                        : static::getUrl('materi', ['record' => $record]))
                    ->visible(fn (): bool => static::canViewAny()),
            ])
            ->bulkActions([

            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        $user = auth()->user();

        $assessmentsCountSubquery = fn (?string $jenis = null, ?string $materiKey = null) => BoardingHafalanAssessment::query()
            ->join('boarding_hafalan_points', 'boarding_hafalan_points.id', '=', 'boarding_hafalan_assessments.boarding_hafalan_point_id')
            ->whereColumn('boarding_hafalan_assessments.boarding_pencapaian_id', 'boarding_pencapaians.id')
            ->where('boarding_hafalan_points.is_active', true)
            ->whereIn('boarding_hafalan_points.jenis', BoardingHafalanPoint::hafalanJenis())
            ->when(
                filled($jenis),
                fn ($query) => $query->where('boarding_hafalan_points.jenis', $jenis)
            )
            ->when(
                filled($materiKey),
                fn ($query) => $query->where('boarding_hafalan_points.materi_key', $materiKey)
            )
            ->selectRaw('count(*)');

        $maknaCountSubquery = fn (string $status) => BoardingMaknaProgress::query()
            ->whereColumn('boarding_makna_progresses.boarding_pencapaian_id', 'boarding_pencapaians.id')
            ->where('status', $status)
            ->selectRaw('count(*)');

        $query = parent::getEloquentQuery()
            ->select('boarding_pencapaians.*')
            ->with(['siswa:id,nama,rombel_saat_ini'])
            ->visibleToUser($user)
            ->whereHas('siswa', fn (Builder $query) => DataSiswa::applyVisibleScope($query, $user))
            ->selectSub(
                BoardingHafalanPoint::query()
                    ->where('boarding_hafalan_points.is_active', true)
                    ->whereIn('boarding_hafalan_points.jenis', BoardingHafalanPoint::hafalanJenis())
                    ->selectRaw('count(*)'),
                'hafalan_active_points_count'
            )
            ->selectSub($assessmentsCountSubquery(), 'hafalan_total_assessed_count')
            ->selectSub($assessmentsCountSubquery('surat'), 'hafalan_surat_count')
            ->selectSub($assessmentsCountSubquery('doa'), 'hafalan_doa_count')
            ->selectSub($assessmentsCountSubquery('dalil'), 'hafalan_dalil_count')
            ->selectSub($maknaCountSubquery('khatam'), 'makna_khatam_count')
            ->selectSub($maknaCountSubquery('sebagian'), 'makna_partial_count')
            ->selectSub(
                BoardingBacaanAssessment::query()
                    ->whereColumn('boarding_bacaan_assessments.boarding_pencapaian_id', 'boarding_pencapaians.id')
                    ->selectRaw('count(*)'),
                'bacaan_assessments_count'
            )
            ->selectSub(
                BoardingBacaanAssessment::query()
                    ->whereColumn('boarding_bacaan_assessments.boarding_pencapaian_id', 'boarding_pencapaians.id')
                    ->select('assessed_at')
                    ->orderByDesc('assessed_at')
                    ->orderByDesc('id')
                    ->limit(1),
                'bacaan_latest_assessed_at'
            );

        foreach (static::hafalanMateriLabels() as $materiKey => $label) {
            $query
                ->selectSub(
                    BoardingHafalanPoint::query()
                        ->where('boarding_hafalan_points.is_active', true)
                        ->where('boarding_hafalan_points.materi_key', $materiKey)
                        ->whereIn('boarding_hafalan_points.jenis', BoardingHafalanPoint::hafalanJenis())
                        ->selectRaw('count(*)'),
                    'hafalan_'.$materiKey.'_points_count'
                )
                ->selectSub(
                    $assessmentsCountSubquery(null, $materiKey),
                    'hafalan_'.$materiKey.'_assessed_count'
                );
        }

        foreach (static::maknaMateriTargetKeys() as $materiKey => $targetKey) {
            $query->selectSub(
                BoardingMaknaProgress::query()
                    ->whereColumn('boarding_makna_progresses.boarding_pencapaian_id', 'boarding_pencapaians.id')
                    ->where('target_key', $targetKey)
                    ->select('status')
                    ->limit(1),
                'makna_'.$materiKey.'_status'
            );
        }

        return $query;
    }

    protected static function hafalanMateriLabels(): array
    {
        return self::HAFALAN_MATERI_LABELS;
    }

    protected static function maknaMateriTargetKeys(): array
    {
        return self::MAKNA_MATERI_TARGET_KEYS;
    }

    protected static function resolveOverallAchievementPercentage(BoardingPencapaian $record): int
    {
        $hafalanPercent = static::resolveHafalanCompletionPercentage($record);
        $maknaPercent = static::resolveMaknaCompletionPercentage($record);
        $bacaanPercent = static::resolveBacaanCompletionPercentage($record);

        return (int) round(($hafalanPercent + $maknaPercent + $bacaanPercent) / 3);
    }

    protected static function resolveOverallAchievementColor(BoardingPencapaian $record): string
    {
        $percentage = static::resolveOverallAchievementPercentage($record);

        return match (true) {
            $percentage >= 75 => 'success',
            $percentage >= 40 => 'warning',
            $percentage > 0 => 'info',
            default => 'gray',
        };
    }

    protected static function resolveOverallAchievementSummary(BoardingPencapaian $record): string
    {
        return implode(' | ', [
            'Hafalan '.static::resolveHafalanCompletionPercentage($record).'%',
            'Makna '.static::resolveMaknaCompletionPercentage($record).'%',
            'Bacaan '.static::resolveBacaanCompletionPercentage($record).'%',
        ]);
    }

    protected static function resolveMobileRecordSummary(BoardingPencapaian $record): string
    {
        return collect([
            $record->siswa?->rombel_saat_ini ?: 'Tanpa rombel',
            'Target '.BoardingPencapaian::materiRapotScopeLabel($record->materi_rapot_scope),
            'Total '.static::resolveOverallAchievementPercentage($record).'%',
            'H '.static::resolveHafalanSummary($record),
            'M '.static::resolveMaknaSummary($record),
            'B '.static::resolveBacaanSummary($record),
        ])->implode(' | ');
    }

    protected static function resolveHafalanCompletionPercentage(BoardingPencapaian $record): int
    {
        $percentages = collect(static::hafalanMateriLabels())
            ->keys()
            ->map(function (string $materiKey) use ($record): float {
                $total = static::resolveHafalanMateriTotal($record, $materiKey);
                $count = static::resolveHafalanMateriCount($record, $materiKey);

                if ($total <= 0) {
                    return 0;
                }

                return ($count / $total) * 100;
            });

        if ($percentages->isEmpty()) {
            return 0;
        }

        return (int) round($percentages->avg() ?? 0);
    }

    protected static function resolveMaknaCompletionPercentage(BoardingPencapaian $record): int
    {
        $totalTargets = BoardingMaknaProgress::defaultTargetCount();

        if ($totalTargets <= 0) {
            return 0;
        }

        $khatamCount = (int) ($record->getAttribute('makna_khatam_count') ?? 0);
        $partialCount = (int) ($record->getAttribute('makna_partial_count') ?? 0);
        $progressUnits = $khatamCount + ($partialCount * 0.5);

        return (int) round(($progressUnits / $totalTargets) * 100);
    }

    protected static function resolveBacaanCompletionPercentage(BoardingPencapaian $record): int
    {
        $count = (int) ($record->getAttribute('bacaan_assessments_count') ?? 0);

        return min(100, $count * 25);
    }

    protected static function resolveHafalanSummary(BoardingPencapaian $record): string
    {
        $assessedCount = (int) ($record->getAttribute('hafalan_total_assessed_count') ?? 0);
        $activePointsCount = (int) ($record->getAttribute('hafalan_active_points_count') ?? 0);

        if ($activePointsCount <= 0) {
            return 'Belum ada master materi';
        }

        return "{$assessedCount} / {$activePointsCount} materi";
    }

    protected static function resolveHafalanMateriBreakdown(BoardingPencapaian $record): string
    {
        return collect(static::hafalanMateriLabels())
            ->map(function (string $label, string $materiKey) use ($record): string {
                $count = static::resolveHafalanMateriCount($record, $materiKey);
                $total = static::resolveHafalanMateriTotal($record, $materiKey);

                return "{$label}: {$count} / {$total} materi";
            })
            ->implode(' | ');
    }

    protected static function resolveMaknaSummary(BoardingPencapaian $record): string
    {
        $totalTargets = BoardingMaknaProgress::defaultTargetCount();
        $khatamCount = (int) ($record->getAttribute('makna_khatam_count') ?? 0);
        $partialCount = (int) ($record->getAttribute('makna_partial_count') ?? 0);
        $trackedCount = min($totalTargets, $khatamCount + $partialCount);

        return $trackedCount.'/'.$totalTargets.' materi';
    }

    protected static function resolveMaknaDetail(BoardingPencapaian $record): string
    {
        $totalTargets = BoardingMaknaProgress::defaultTargetCount();
        $khatamCount = (int) ($record->getAttribute('makna_khatam_count') ?? 0);
        $partialCount = (int) ($record->getAttribute('makna_partial_count') ?? 0);
        $blankCount = max($totalTargets - $khatamCount - $partialCount, 0);

        return "Khatam: {$khatamCount} | Sebagian: {$partialCount} | Belum diisi: {$blankCount}";
    }

    protected static function resolveBacaanSummary(BoardingPencapaian $record): string
    {
        $count = (int) ($record->getAttribute('bacaan_assessments_count') ?? 0);

        if ($count === 0) {
            return 'Belum ada riwayat';
        }

        return "{$count} simakan";
    }

    protected static function resolveBacaanDetail(BoardingPencapaian $record): string
    {
        $count = (int) ($record->getAttribute('bacaan_assessments_count') ?? 0);
        $latestDate = $record->getAttribute('bacaan_latest_assessed_at');

        if ($count === 0) {
            return 'Belum ada penilaian bacaan.';
        }

        $dateLabel = filled($latestDate)
            ? Carbon::parse($latestDate)->translatedFormat('d M Y')
            : '-';

        return 'Target bacaan bertambah seiring jumlah simakan. Terakhir '.$dateLabel.'.';
    }

    protected static function resolveHafalanMateriCount(BoardingPencapaian $record, string $materiKey): int
    {
        return (int) ($record->getAttribute('hafalan_'.$materiKey.'_assessed_count') ?? 0);
    }

    protected static function resolveHafalanMateriTotal(BoardingPencapaian $record, string $materiKey): int
    {
        return (int) ($record->getAttribute('hafalan_'.$materiKey.'_points_count') ?? 0);
    }

    protected static function resolveMaknaMateriWeight(string $status): float
    {
        return match ($status) {
            'khatam' => 1.0,
            'sebagian' => 0.5,
            default => 0.0,
        };
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageBoardingPencapaians::route('/'),
            'materi' => Pages\ManageMateriBoarding::route('/{record}/materi'),
            'hafalan' => Pages\ManageHafalan::route('/{record}/hafalan'),
            'makna' => Pages\ManageMakna::route('/{record}/makna'),
            'mt' => Pages\ManageMt::route('/{record}/mt'),
            'bacaan' => Pages\ManageBacaan::route('/{record}/bacaan'),
        ];
    }
}




