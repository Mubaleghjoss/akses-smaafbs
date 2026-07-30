<?php

namespace App\Filament\Resources;

use App\Enums\Assessment\AssessmentPeriodStatus;
use App\Enums\Assessment\AssessmentType;
use App\Enums\Assessment\ScoreSource;
use App\Filament\Concerns\HasAssessmentPermissions;
use App\Filament\Concerns\HasOptimizedAdminTable;
use App\Filament\Resources\AssessmentSchemeResource\Pages;
use App\Models\Assessment\AssessmentPeriod;
use App\Models\Assessment\AssessmentScheme;
use App\Models\Assessment\Subject;
use App\Models\Rombel;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\View as SchemaView;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\ValidationException;

class AssessmentSchemeResource extends Resource
{
    use HasAssessmentPermissions;
    use HasOptimizedAdminTable;

    protected static ?string $model = AssessmentScheme::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-adjustments-horizontal';

    protected static string|\UnitEnum|null $navigationGroup = 'Manajemen Sekolah';

    protected static ?string $navigationLabel = 'Komponen dan Bobot';

    protected static ?string $modelLabel = 'skema penilaian';

    protected static ?string $pluralModelLabel = 'Komponen dan Bobot';

    protected static ?int $navigationSort = 11;

    protected static ?string $slug = 'penilaian/pengaturan/komponen-bobot';

    protected static string $assessmentManagePermission = 'penilaian.period.manage';

    public static function canEdit(Model $record): bool
    {
        return static::canAccess()
            && parent::canEdit($record)
            && $record instanceof AssessmentScheme
            && $record->period?->status === AssessmentPeriodStatus::DRAFT;
    }

    public static function canDelete(Model $record): bool
    {
        return static::canEdit($record) && ! $record->components()->whereHas('scores')->exists();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            SchemaView::make('filament.resources.assessment-scheme-resource.partials.scheme-guide')
                ->columnSpanFull(),
            Section::make('Cakupan Skema')
                ->description('Kosongkan mapel atau kelas untuk membuat skema default periode. Skema yang lebih spesifik akan diprioritaskan.')
                ->columns(['default' => 1, 'md' => 2])
                ->schema([
                    Forms\Components\Select::make('assessment_period_id')
                        ->label('Periode')
                        ->relationship(
                            'period',
                            'name',
                            modifyQueryUsing: fn ($query) => $query->where('status', AssessmentPeriodStatus::DRAFT->value),
                        )
                        ->searchable()
                        ->preload()
                        ->live()
                        ->required(),
                    Forms\Components\TextInput::make('name')
                        ->label('Nama Skema')
                        ->required()
                        ->maxLength(150),
                    Forms\Components\Select::make('assessment_subject_id')
                        ->label('Mata Pelajaran')
                        ->relationship(
                            'subject',
                            'name',
                            modifyQueryUsing: fn ($query) => $query->where('is_active', true),
                        )
                        ->searchable()
                        ->preload()
                        ->placeholder('Semua mapel'),
                    Forms\Components\Select::make('source_rombel_id')
                        ->label('Kelas')
                        ->options(fn (Get $get): array => static::rombelOptionsForPeriod(
                            (int) ($get('assessment_period_id') ?? 0),
                        ))
                        ->searchable()
                        ->preload()
                        ->placeholder('Semua kelas')
                        ->helperText('Hanya kelas yang dipilih pada periode draf ini. Kosongkan untuk semua kelas.'),
                    Forms\Components\TextInput::make('rounding_precision')
                        ->label('Presisi Pembulatan')
                        ->numeric()
                        ->minValue(0)
                        ->maxValue(4)
                        ->default(2)
                        ->required(),
                    Forms\Components\TextInput::make('minimum_score')
                        ->label('Nilai Minimum')
                        ->numeric()
                        ->default(0)
                        ->required(),
                    Forms\Components\TextInput::make('maximum_score')
                        ->label('Nilai Maksimum')
                        ->numeric()
                        ->default(100)
                        ->gt('minimum_score')
                        ->required(),
                    Forms\Components\Toggle::make('is_active')
                        ->label('Skema Aktif')
                        ->default(true)
                        ->inline(false),
                ]),
            Section::make('Komponen Nilai')
                ->description('Total bobot komponen aktif wajib tepat 100%. Komponen referensi ASTS hanya digunakan pada ASAS.')
                ->schema([
                    Forms\Components\Placeholder::make('weight_total_preview')
                        ->label('Status Total Bobot')
                        ->content(fn (Get $get): HtmlString => static::weightPreview(
                            $get('components'),
                        )),
                    Forms\Components\Repeater::make('components')
                        ->relationship()
                        ->label('Komponen')
                        ->minItems(1)
                        ->defaultItems(1)
                        ->addActionLabel('Tambah Komponen')
                        ->reorderableWithButtons()
                        ->orderColumn('sort_order')
                        ->columns(['default' => 1, 'md' => 2, 'xl' => 4])
                        ->schema([
                            Forms\Components\TextInput::make('code')
                                ->label('Kode')
                                ->required()
                                ->maxLength(40),
                            Forms\Components\TextInput::make('name')
                                ->label('Nama Komponen')
                                ->required()
                                ->maxLength(150)
                                ->columnSpan(['default' => 1, 'xl' => 2]),
                            Forms\Components\TextInput::make('domain')
                                ->label('Domain/Kompetensi')
                                ->maxLength(100),
                            Forms\Components\TextInput::make('weight')
                                ->label('Bobot')
                                ->numeric()
                                ->suffix('%')
                                ->minValue(0)
                                ->maxValue(100)
                                ->live(onBlur: true)
                                ->required(),
                            Forms\Components\TextInput::make('maximum_score')
                                ->label('Skor Maksimum')
                                ->numeric()
                                ->minValue(0.01)
                                ->default(100)
                                ->required(),
                            Forms\Components\Select::make('score_source')
                                ->label('Sumber')
                                ->options(collect(ScoreSource::cases())
                                    ->mapWithKeys(fn (ScoreSource $source): array => [$source->value => $source->label()])
                                    ->all())
                                ->default(ScoreSource::MANUAL->value)
                                ->required()
                                ->native(false),
                            Forms\Components\Toggle::make('is_required')
                                ->label('Wajib Diisi')
                                ->default(true)
                                ->inline(false),
                            Forms\Components\Toggle::make('settings.is_active')
                                ->label('Komponen Aktif')
                                ->default(true)
                                ->live()
                                ->inline(false),
                        ]),
                ]),
            Section::make('Predikat dan Deskripsi')
                ->collapsed()
                ->columns(['default' => 1, 'md' => 2])
                ->schema([
                    Forms\Components\Repeater::make('settings.predicates')
                        ->label('Aturan Predikat')
                        ->addActionLabel('Tambah Predikat')
                        ->columns(['default' => 1, 'md' => 2])
                        ->schema([
                            Forms\Components\TextInput::make('label')
                                ->label('Predikat')
                                ->required()
                                ->maxLength(20),
                            Forms\Components\TextInput::make('minimum_score')
                                ->label('Nilai Minimum')
                                ->numeric()
                                ->required(),
                        ])
                        ->columnSpanFull(),
                    Forms\Components\TextInput::make('settings.fallback_predicate')
                        ->label('Predikat di Bawah Batas')
                        ->maxLength(20),
                    Forms\Components\TextInput::make('settings.kkm')
                        ->label('KKM')
                        ->numeric()
                        ->minValue(0)
                        ->maxValue(100)
                        ->default(75)
                        ->required()
                        ->helperText('Batas ketuntasan hasil akhir setelah normalisasi ke skala 0–100.'),
                    Forms\Components\TextInput::make('settings.description_template')
                        ->label('Template Deskripsi')
                        ->helperText('Gunakan {strongest} dan {weakest}.')
                        ->maxLength(500)
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function weightPreview(mixed $components): HtmlString
    {
        $rows = collect(is_array($components) ? $components : []);
        $activeRows = $rows->filter(
            fn (mixed $component): bool => is_array($component)
                && data_get($component, 'settings.is_active', true) !== false,
        );
        $total = (float) $activeRows->sum(
            fn (array $component): float => (float) ($component['weight'] ?? 0),
        );
        $ready = $activeRows->isNotEmpty() && abs($total - 100.0) < 0.0001;
        $formatted = number_format($total, 2, ',', '.');
        $status = $ready
            ? 'Siap disimpan. Total bobot komponen aktif sudah tepat 100%.'
            : 'Belum siap. Ubah bobot komponen aktif sampai totalnya tepat 100%.';
        $class = $ready ? 'is-ready' : 'is-warning';

        return new HtmlString(
            '<div class="assessment-weight-preview '.$class.'">'
            .'<span>Total saat ini</span>'
            .'<strong>'.$formatted.'%</strong>'
            .'<small>'.$status.'</small>'
            .'</div>',
        );
    }

    public static function table(Table $table): Table
    {
        return static::optimizeAdminTable(
            $table,
            searchPlaceholder: 'Cari skema atau periode...',
            emptyStateHeading: 'Belum ada skema nilai',
            emptyStateDescription: 'Buat minimal satu skema dengan total bobot aktif 100% sebelum membuka periode.'
        )
            ->modifyQueryUsing(fn ($query) => $query
                ->with(['period', 'subject', 'sourceRombel', 'periodRombel', 'components'])
                ->withCount('components'))
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Skema')
                    ->description(fn (AssessmentScheme $record): string => $record->period?->name ?? '-')
                    ->searchable()
                    ->wrap(),
                Tables\Columns\TextColumn::make('subject.name')
                    ->label('Mapel')
                    ->placeholder('Semua')
                    ->visibleFrom('md'),
                Tables\Columns\TextColumn::make('source_rombel_name')
                    ->label('Kelas')
                    ->state(fn (AssessmentScheme $record): ?string => $record->sourceRombel?->nama
                        ?? $record->periodRombel?->rombel_name_snapshot)
                    ->placeholder('Semua')
                    ->visibleFrom('md'),
                Tables\Columns\TextColumn::make('components_count')
                    ->label('Komponen')
                    ->numeric(),
                Tables\Columns\TextColumn::make('total_weight')
                    ->label('Total Bobot')
                    ->state(fn (AssessmentScheme $record): string => number_format(static::activeWeight($record), 2).'%')
                    ->badge()
                    ->color(fn (AssessmentScheme $record): string => abs(static::activeWeight($record) - 100) < 0.0001 ? 'success' : 'danger'),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Aktif')
                    ->boolean()
                    ->visibleFrom('lg'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('assessment_period_id')
                    ->label('Periode')
                    ->relationship('period', 'name'),
            ])
            ->actions([
                EditAction::make()->visible(fn (AssessmentScheme $record): bool => static::canEdit($record)),
                DeleteAction::make()
                    ->visible(fn (AssessmentScheme $record): bool => static::canDelete($record))
                    ->databaseTransaction()
                    ->before(function (AssessmentScheme $record): void {
                        $period = AssessmentPeriod::query()
                            ->whereKey($record->assessment_period_id)
                            ->lockForUpdate()
                            ->firstOrFail();
                        $scheme = AssessmentScheme::query()
                            ->whereKey($record->getKey())
                            ->lockForUpdate()
                            ->firstOrFail();

                        abort_unless(
                            $period->status === AssessmentPeriodStatus::DRAFT
                                && static::canDelete($scheme),
                            403,
                        );
                    }),
            ])
            ->bulkActions([]);
    }

    public static function validateSchemeData(array $data, ?int $ignoreSchemeId = null): array
    {
        $period = AssessmentPeriod::query()->find((int) ($data['assessment_period_id'] ?? 0));

        if (! $period || $period->status !== AssessmentPeriodStatus::DRAFT) {
            throw ValidationException::withMessages([
                'data.assessment_period_id' => 'Skema hanya dapat dibuat atau diubah pada periode berstatus Draf.',
            ]);
        }

        $sourceRombelId = (int) ($data['source_rombel_id'] ?? 0);
        $periodRombelIds = collect(data_get($period->settings, 'rombel_ids', []))
            ->map(fn (mixed $id): int => (int) $id)
            ->filter()
            ->unique();
        if ($sourceRombelId > 0 && (
            ! $periodRombelIds->contains($sourceRombelId)
            || ! Rombel::query()
                ->whereKey($sourceRombelId)
                ->where('is_active', true)
                ->exists()
        )) {
            throw ValidationException::withMessages([
                'data.source_rombel_id' => 'Kelas harus aktif dan termasuk dalam pilihan kelas periode ini.',
            ]);
        }

        // The snapshot relation is retained only for backward compatibility.
        // New configuration always resolves a logical source class at open time.
        $data['assessment_period_rombel_id'] = null;

        $subjectId = (int) ($data['assessment_subject_id'] ?? 0);
        if ($subjectId > 0 && ! Subject::query()
            ->whereKey($subjectId)
            ->where('is_active', true)
            ->exists()) {
            throw ValidationException::withMessages([
                'data.assessment_subject_id' => 'Mata pelajaran harus ditemukan dan masih aktif.',
            ]);
        }

        if ((bool) ($data['is_active'] ?? false)) {
            $duplicateScope = AssessmentScheme::query()
                ->where('assessment_period_id', $period->getKey())
                ->where('assessment_subject_id', $subjectId > 0 ? $subjectId : null)
                ->where('source_rombel_id', $sourceRombelId > 0 ? $sourceRombelId : null)
                ->whereNull('assessment_period_rombel_id')
                ->when(
                    $ignoreSchemeId,
                    fn ($query, int $id) => $query->whereKeyNot($id),
                )
                ->where('is_active', true)
                ->exists();

            if ($duplicateScope) {
                throw ValidationException::withMessages([
                    'data.name' => 'Sudah ada skema aktif untuk kombinasi periode, mapel, dan kelas yang sama.',
                ]);
            }
        }

        $minimumScore = (float) ($data['minimum_score'] ?? 0);
        $maximumScore = (float) ($data['maximum_score'] ?? 0);
        if ($maximumScore <= $minimumScore) {
            throw ValidationException::withMessages([
                'data.maximum_score' => 'Nilai maksimum harus lebih besar daripada nilai minimum.',
            ]);
        }

        $precision = (int) ($data['rounding_precision'] ?? 0);
        if ($precision < 0 || $precision > 4) {
            throw ValidationException::withMessages([
                'data.rounding_precision' => 'Presisi pembulatan harus berada pada rentang 0 sampai 4.',
            ]);
        }

        $kkm = data_get($data, 'settings.kkm');
        if (! is_numeric($kkm) || (float) $kkm < 0 || (float) $kkm > 100) {
            throw ValidationException::withMessages([
                'data.settings.kkm' => 'KKM wajib berupa angka pada rentang 0 sampai 100.',
            ]);
        }

        $components = collect($data['components'] ?? []);

        if ($components->isEmpty()) {
            throw ValidationException::withMessages(['data.components' => 'Skema wajib memiliki minimal satu komponen.']);
        }

        $codes = $components
            ->pluck('code')
            ->map(fn (mixed $code): string => mb_strtoupper(trim((string) $code)))
            ->filter();
        if ($codes->count() !== $components->count() || $codes->unique()->count() !== $codes->count()) {
            throw ValidationException::withMessages([
                'data.components' => 'Setiap komponen wajib memiliki kode yang unik dalam satu skema.',
            ]);
        }

        foreach ($components as $component) {
            $componentMaximum = (float) ($component['maximum_score'] ?? 0);
            $componentWeight = (float) ($component['weight'] ?? -1);
            $source = ScoreSource::tryFrom((string) ($component['score_source'] ?? ''));

            if ($componentMaximum <= 0 || $componentWeight < 0 || $componentWeight > 100 || ! $source) {
                throw ValidationException::withMessages([
                    'data.components' => 'Skor maksimum, bobot, atau sumber salah satu komponen tidak valid.',
                ]);
            }

            if ($source === ScoreSource::ASTS_SNAPSHOT
                && $period->type !== AssessmentType::ASAS) {
                throw ValidationException::withMessages([
                    'data.components' => 'Komponen Snapshot ASTS hanya dapat digunakan pada periode ASAS.',
                ]);
            }
        }

        $active = $components->filter(fn (array $component): bool => data_get($component, 'settings.is_active', true) !== false);
        $weight = (float) $active->sum(fn (array $component): float => (float) ($component['weight'] ?? 0));

        if (abs($weight - 100.0) > 0.0001) {
            throw ValidationException::withMessages([
                'data.components' => 'Total bobot komponen aktif harus tepat 100%. Saat ini '.number_format($weight, 2).'%.',
            ]);
        }

        return $data;
    }

    protected static function activeWeight(AssessmentScheme $scheme): float
    {
        $components = $scheme->relationLoaded('components')
            ? $scheme->components
            : $scheme->components()->get(['weight', 'settings']);

        return (float) $components
            ->filter(fn ($component): bool => data_get($component->settings, 'is_active', true) !== false)
            ->sum(fn ($component): float => (float) $component->weight);
    }

    /**
     * @return array<int, string>
     */
    public static function rombelOptionsForPeriod(int $periodId): array
    {
        $period = AssessmentPeriod::query()->find($periodId);
        $ids = collect(data_get($period?->settings, 'rombel_ids', []))
            ->map(fn (mixed $id): int => (int) $id)
            ->filter()
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return [];
        }

        return Rombel::query()
            ->whereIn('id', $ids)
            ->where('is_active', true)
            ->orderBy('nama')
            ->pluck('nama', 'id')
            ->all();
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAssessmentSchemes::route('/'),
            'create' => Pages\CreateAssessmentScheme::route('/create'),
            'edit' => Pages\EditAssessmentScheme::route('/{record}/edit'),
        ];
    }
}
