<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasConfirmedDeleteActions;
use App\Filament\Concerns\HasModulePermissions;
use App\Filament\Concerns\HasOptimizedAdminTable;
use App\Filament\Resources\PerpustakaanLiterasiMaterialResource\Pages;
use App\Filament\Resources\PerpustakaanLiterasiMaterialResource\RelationManagers\ResponsesRelationManager;
use App\Filament\Resources\PerpustakaanLiterasiMaterialResource\RelationManagers\SimilarityMatchesRelationManager;
use App\Models\DataSiswa;
use App\Models\PerpustakaanLiterasiMaterial;
use App\Models\PerpustakaanLiterasiQuestion;
use App\Models\PerpustakaanLiterasiSimilarityMatch;
use App\Support\Perpustakaan\LiterasiAnalytics;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\View as SchemaView;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema as SchemaFacade;
use Illuminate\Support\HtmlString;

class PerpustakaanLiterasiMaterialResource extends Resource
{
    use HasConfirmedDeleteActions;
    use HasModulePermissions;
    use HasOptimizedAdminTable;

    protected static ?string $model = PerpustakaanLiterasiMaterial::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-book-open';

    protected static string|\UnitEnum|null $navigationGroup = 'Manajemen Sekolah';

    protected static ?int $navigationSort = 9;

    protected static ?string $navigationLabel = 'Literasi';

    protected static ?string $modelLabel = 'materi literasi';

    protected static ?string $pluralModelLabel = 'Literasi';

    protected static ?string $permissionPrefix = 'perpustakaan_literasi';

    public static function canAccess(): bool
    {
        return SchemaFacade::hasTable('perpustakaan_literasi_materials') && parent::canAccess();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Materi Bacaan')
                    ->description('Materi aktif akan muncul di menu publik Literacy Habituation Program.')
                    ->columns(['default' => 1, 'md' => 2])
                    ->schema([
                        Forms\Components\TextInput::make('title')
                            ->label('Judul Materi')
                            ->required()
                            ->maxLength(180)
                            ->columnSpanFull(),
                        Forms\Components\Checkbox::make('show_reading_latex_tools')
                            ->label('Tampilkan template rumus LaTeX')
                            ->live()
                            ->dehydrated(false)
                            ->default(fn (?PerpustakaanLiterasiMaterial $record = null): bool => static::containsLatex((string) $record?->reading_content))
                            ->afterStateHydrated(function (Forms\Components\Checkbox $component, ?PerpustakaanLiterasiMaterial $record = null): void {
                                $component->state(static::containsLatex((string) $record?->reading_content));
                            })
                            ->columnSpanFull(),
                        Forms\Components\Textarea::make('reading_content')
                            ->label('Isi Bacaan')
                            ->rows(10)
                            ->helperText(fn (Get $get): ?string => $get('show_reading_latex_tools') ? 'Rumus matematika bisa ditulis dengan LaTeX: \(x^2\), \(\frac{a}{b}\), \(\sqrt{x}\), atau \(\int_0^1 x\,dx\).' : null)
                            ->extraInputAttributes(['data-literacy-latex-target' => '1'])
                            ->columnSpanFull(),
                        SchemaView::make('filament.resources.perpustakaan-literasi-material-resource.partials.latex-picker')
                            ->visible(fn (Get $get): bool => (bool) $get('show_reading_latex_tools'))
                            ->columnSpanFull(),
                        Forms\Components\FileUpload::make('image_path')
                            ->label('Gambar Materi')
                            ->disk('public')
                            ->directory('literasi/materials')
                            ->image()
                            ->maxSize(4096),
                        Forms\Components\TextInput::make('google_drive_url')
                            ->label('Link Google Drive')
                            ->url()
                            ->maxLength(1000),
                        Forms\Components\Toggle::make('is_active')
                            ->label('Aktifkan materi')
                            ->default(true),
                        Forms\Components\DatePicker::make('opens_at')
                            ->label('Mulai Dibuka')
                            ->native(true)
                            ->displayFormat('d/m/Y'),
                        Forms\Components\DatePicker::make('closes_at')
                            ->label('Ditutup Pada')
                            ->native(true)
                            ->displayFormat('d/m/Y'),
                    ]),
                Section::make('Pertanyaan')
                    ->description('Pertanyaan dikunci setelah ada responden agar jawaban dan analisa tetap konsisten.')
                    ->schema([
                        Forms\Components\Repeater::make('questions')
                            ->relationship()
                            ->orderColumn('sort_order')
                            ->collapsed()
                            ->reorderableWithButtons()
                            ->cloneable()
                            ->defaultItems(1)
                            ->addActionLabel('Tambah Pertanyaan')
                            ->disabled(fn (?PerpustakaanLiterasiMaterial $record): bool => $record?->hasResponses() ?? false)
                            ->schema([
                                Forms\Components\Checkbox::make('show_question_latex_tools')
                                    ->label('Tampilkan template rumus LaTeX')
                                    ->live()
                                    ->dehydrated(false)
                                    ->default(fn ($record = null): bool => $record instanceof PerpustakaanLiterasiQuestion && static::containsLatex((string) $record->prompt))
                                    ->afterStateHydrated(function (Forms\Components\Checkbox $component, mixed $record = null): void {
                                        $component->state($record instanceof PerpustakaanLiterasiQuestion && static::containsLatex((string) $record->prompt));
                                    })
                                    ->columnSpanFull(),
                                Forms\Components\Textarea::make('prompt')
                                    ->label('Pertanyaan')
                                    ->required()
                                    ->rows(3)
                                    ->helperText(fn (Get $get): ?string => $get('show_question_latex_tools') ? 'Untuk rumus gunakan LaTeX, contoh: \(2^5\), \(\frac{3}{4}\), \(\sqrt{81}\), \(\int x^2\,dx\).' : null)
                                    ->extraInputAttributes(['data-literacy-latex-target' => '1'])
                                    ->columnSpanFull(),
                                SchemaView::make('filament.resources.perpustakaan-literasi-material-resource.partials.latex-picker')
                                    ->visible(fn (Get $get): bool => (bool) $get('show_question_latex_tools'))
                                    ->columnSpanFull(),
                                Forms\Components\FileUpload::make('image_path')
                                    ->label('Gambar Pertanyaan')
                                    ->disk('public')
                                    ->directory('literasi/questions')
                                    ->image()
                                    ->maxSize(4096),
                                Forms\Components\TextInput::make('google_drive_url')
                                    ->label('Link Google Drive')
                                    ->url()
                                    ->maxLength(1000),
                                Forms\Components\TextInput::make('min_characters')
                                    ->label('Minimal Karakter Jawaban')
                                    ->required()
                                    ->numeric()
                                    ->minValue(0)
                                    ->maxValue(8000)
                                    ->default(20),
                                Forms\Components\TextInput::make('max_characters')
                                    ->label('Maksimal Karakter Jawaban')
                                    ->required()
                                    ->numeric()
                                    ->minValue(fn (Get $get): int => max(1, (int) ($get('min_characters') ?: 0)))
                                    ->maxValue(8000)
                                    ->default(1000),
                                Forms\Components\Toggle::make('is_required')
                                    ->label('Wajib diisi')
                                    ->default(true),
                            ])
                            ->columns(['default' => 1, 'md' => 2]),
                    ]),
                SchemaView::make('filament.resources.perpustakaan-literasi-material-resource.partials.latex-picker-assets'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return static::optimizeAdminTable(
            $table,
            searchPlaceholder: 'Cari judul materi...',
            emptyStateHeading: 'Belum ada materi literasi',
            emptyStateDescription: 'Buat materi Literacy Habituation Program agar siswa bisa membaca dan mengirim jawaban.'
        )
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\ViewColumn::make('title')
                    ->label('Materi')
                    ->searchable()
                    ->sortable()
                    ->view('filament.resources.perpustakaan-literasi-material-resource.partials.material-table-cell')
                    ->extraAttributes(['class' => 'literasi-material-title-column']),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Aktif')
                    ->boolean()
                    ->visibleFrom('md'),
                Tables\Columns\TextColumn::make('responses_count')
                    ->label('Responden')
                    ->counts('responses')
                    ->badge()
                    ->visibleFrom('md'),
                Tables\Columns\TextColumn::make('graded_responses_summary')
                    ->label('Dinilai')
                    ->state(fn (PerpustakaanLiterasiMaterial $record): string => number_format((int) ($record->graded_responses_count ?? 0), 0, ',', '.')
                        .'/'.number_format((int) ($record->responses_count ?? 0), 0, ',', '.'))
                    ->description('responden selesai')
                    ->badge()
                    ->color(fn (PerpustakaanLiterasiMaterial $record): string => ((int) ($record->responses_count ?? 0) > 0 && (int) ($record->graded_responses_count ?? 0) >= (int) ($record->responses_count ?? 0)) ? 'success' : 'warning')
                    ->visibleFrom('md'),
                Tables\Columns\TextColumn::make('similarity_matches_count')
                    ->label('Indikasi Plagiat')
                    ->counts('similarityMatches')
                    ->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'danger' : 'gray')
                    ->visibleFrom('md'),
                Tables\Columns\TextColumn::make('confirmed_similarity_matches_count')
                    ->label('Konfirmasi Plagiat')
                    ->state(fn (PerpustakaanLiterasiMaterial $record): int => (int) ($record->confirmed_similarity_matches_count ?? 0))
                    ->description(fn (PerpustakaanLiterasiMaterial $record): string => 'dari '.number_format((int) ($record->similarity_matches_count ?? 0), 0, ',', '.').' indikasi')
                    ->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'danger' : 'gray')
                    ->visibleFrom('md'),
                Tables\Columns\TextColumn::make('closes_at')
                    ->label('Tutup')
                    ->date('d/m/Y')
                    ->placeholder('-')
                    ->visibleFrom('lg')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Diupdate')
                    ->since()
                    ->visibleFrom('lg')
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Status Aktif'),
            ])
            ->recordUrl(fn (PerpustakaanLiterasiMaterial $record): string => static::getUrl('view', ['record' => $record]))
            ->actions([
                ViewAction::make()
                    ->label('Detail'),
                EditAction::make(),
                static::makeDeleteTableAction('materi literasi')
                    ->visible(fn (PerpustakaanLiterasiMaterial $record): bool => ! $record->hasResponses()),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    static::makeDeleteBulkTableAction(),
                ]),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        $counts = [
            'questions',
            'responses',
            'responses as graded_responses_count' => fn (Builder $query): Builder => $query
                ->whereHas('answers')
                ->whereDoesntHave('answers', fn (Builder $answerQuery): Builder => $answerQuery->whereNull('is_correct')),
            'similarityMatches',
        ];

        if (SchemaFacade::hasTable('perpustakaan_literasi_similarity_matches')
            && SchemaFacade::hasColumn('perpustakaan_literasi_similarity_matches', 'review_status')) {
            $counts['similarityMatches as confirmed_similarity_matches_count'] = fn (Builder $query): Builder => $query
                ->where('review_status', PerpustakaanLiterasiSimilarityMatch::REVIEW_CONFIRMED);
        }

        return parent::getEloquentQuery()->withCount($counts);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Ringkasan Materi')
                    ->columns(['default' => 1, 'md' => 5])
                    ->schema([
                        TextEntry::make('responses_total')
                            ->label('Total Responden')
                            ->state(fn (PerpustakaanLiterasiMaterial $record): int => $record->responses()->count()),
                        TextEntry::make('responses_today')
                            ->label('Responden Hari Ini')
                            ->state(fn (PerpustakaanLiterasiMaterial $record): int => $record->responses()->whereDate('submitted_at', now()->toDateString())->count()),
                        TextEntry::make('classes_total')
                            ->label('Kelas Mengisi')
                            ->state(fn (PerpustakaanLiterasiMaterial $record): int => $record->responses()->whereNotNull('student_class_snapshot')->distinct()->count('student_class_snapshot')),
                        TextEntry::make('similarity_total')
                            ->label('Indikasi Plagiat')
                            ->state(fn (PerpustakaanLiterasiMaterial $record): int => $record->similarityMatches()->count())
                            ->badge()
                            ->color(fn (int $state): string => $state > 0 ? 'danger' : 'gray'),
                        TextEntry::make('similarity_confirmed')
                            ->label('Konfirmasi Plagiat')
                            ->state(fn (PerpustakaanLiterasiMaterial $record): int => SchemaFacade::hasColumn('perpustakaan_literasi_similarity_matches', 'review_status')
                                ? $record->similarityMatches()->where('review_status', PerpustakaanLiterasiSimilarityMatch::REVIEW_CONFIRMED)->count()
                                : 0)
                            ->badge()
                            ->color(fn (int $state): string => $state > 0 ? 'danger' : 'gray'),
                        TextEntry::make('public_url')
                            ->label('Link Publik')
                            ->state(fn (PerpustakaanLiterasiMaterial $record): string => $record->publicUrl())
                            ->url(fn (PerpustakaanLiterasiMaterial $record): string => $record->publicUrl())
                            ->openUrlInNewTab()
                            ->columnSpanFull(),
                    ]),
                Section::make('Analisa Per Kelas')
                    ->schema([
                        TextEntry::make('class_analysis')
                            ->label('Responden')
                            ->state(fn (PerpustakaanLiterasiMaterial $record): HtmlString => static::classAnalysisHtml($record))
                            ->html()
                            ->columnSpanFull(),
                    ]),
                Section::make('Ringkasan Plagiat Per Kelas')
                    ->schema([
                        TextEntry::make('plagiarism_summary')
                            ->label('Indikasi')
                            ->state(fn (PerpustakaanLiterasiMaterial $record): HtmlString => static::plagiarismSummaryHtml($record))
                            ->html()
                            ->columnSpanFull(),
                    ]),
                Section::make('Analisa Nilai & Ranking Bulan Ini')
                    ->schema([
                        TextEntry::make('monthly_ranking_analysis')
                            ->label('Rekap')
                            ->state(fn (PerpustakaanLiterasiMaterial $record): HtmlString => static::monthlyRankingAnalysisHtml($record))
                            ->html()
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            ResponsesRelationManager::class,
            SimilarityMatchesRelationManager::class,
        ];
    }

    public static function canDelete($record): bool
    {
        return $record instanceof PerpustakaanLiterasiMaterial
            && static::userCanModule('manage')
            && ! $record->hasResponses();
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPerpustakaanLiterasiMaterials::route('/'),
            'create' => Pages\CreatePerpustakaanLiterasiMaterial::route('/create'),
            'student-history' => Pages\StudentHistoryPerpustakaanLiterasi::route('/student-history'),
            'view' => Pages\ViewPerpustakaanLiterasiMaterial::route('/{record}'),
            'edit' => Pages\EditPerpustakaanLiterasiMaterial::route('/{record}/edit'),
        ];
    }

    public static function classAnalysisHtml(PerpustakaanLiterasiMaterial $record): HtmlString
    {
        $activeTotals = collect();

        if (SchemaFacade::hasTable('data_siswa') && SchemaFacade::hasColumn('data_siswa', 'rombel_saat_ini')) {
            $activeTotals = DataSiswa::query()
                ->select('rombel_saat_ini')
                ->selectRaw('count(*) as total')
                ->where('status', 'aktif')
                ->whereNotNull('rombel_saat_ini')
                ->where('rombel_saat_ini', '!=', '')
                ->groupBy('rombel_saat_ini')
                ->pluck('total', 'rombel_saat_ini');
        }

        $submittedTotals = $record->responses()
            ->select('student_class_snapshot')
            ->selectRaw('count(*) as total')
            ->whereNotNull('student_class_snapshot')
            ->where('student_class_snapshot', '!=', '')
            ->groupBy('student_class_snapshot')
            ->pluck('total', 'student_class_snapshot');

        $submittedTodayTotals = $record->responses()
            ->select('student_class_snapshot')
            ->selectRaw('count(*) as total')
            ->whereDate('submitted_at', now()->toDateString())
            ->whereNotNull('student_class_snapshot')
            ->where('student_class_snapshot', '!=', '')
            ->groupBy('student_class_snapshot')
            ->pluck('total', 'student_class_snapshot');

        $classes = $activeTotals->keys()
            ->merge($submittedTotals->keys())
            ->merge($submittedTodayTotals->keys())
            ->unique()
            ->sort()
            ->values();

        if ($classes->isEmpty()) {
            return new HtmlString('<div class="text-sm text-gray-500 dark:text-gray-400">Belum ada responden per kelas.</div>');
        }

        $items = $classes
            ->map(function (string $class) use ($activeTotals, $submittedTotals, $submittedTodayTotals): string {
                $submitted = (int) ($submittedTotals[$class] ?? 0);
                $submittedToday = (int) ($submittedTodayTotals[$class] ?? 0);
                $total = (int) ($activeTotals[$class] ?? 0);
                $summary = $total > 0 ? "{$submitted}/{$total}" : "{$submitted}/?";
                $todaySummary = $total > 0 ? "{$submittedToday}/{$total}" : "{$submittedToday}/?";

                return '<div class="rounded-xl border border-gray-200 bg-white p-3 dark:border-white/10 dark:bg-white/5">'.
                    '<div class="text-sm font-medium text-gray-900 dark:text-white">'.e($class).'</div>'.
                    '<div class="mt-1 text-lg font-semibold text-gray-950 dark:text-white">'.e($todaySummary).'</div>'.
                    '<div class="mt-1 text-xs text-gray-500 dark:text-gray-400">Hari ini | Total '.e($summary).'</div>'.
                    '</div>';
            })
            ->implode('');

        return new HtmlString('<div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">'.$items.'</div>');
    }

    public static function plagiarismSummaryHtml(PerpustakaanLiterasiMaterial $record): HtmlString
    {
        $rows = $record->similarityMatches()
            ->select('student_class_snapshot')
            ->selectRaw('count(*) as total')
            ->groupBy('student_class_snapshot')
            ->orderBy('student_class_snapshot')
            ->get();

        if ($rows->isEmpty()) {
            return new HtmlString('<div class="text-sm text-gray-500 dark:text-gray-400">Belum ada indikasi plagiat.</div>');
        }

        $items = $rows
            ->map(function ($row): string {
                $class = trim((string) $row->student_class_snapshot) ?: 'Tanpa kelas';

                return '<div class="rounded-xl border border-rose-200 bg-rose-50 p-3 text-rose-900 dark:border-rose-500/30 dark:bg-rose-500/10 dark:text-rose-100">'.
                    '<div class="text-sm font-medium">'.e($class).'</div>'.
                    '<div class="mt-1 text-lg font-semibold">'.number_format((int) $row->total, 0, ',', '.').' indikasi</div>'.
                    '</div>';
            })
            ->implode('');

        return new HtmlString('<div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">'.$items.'</div>');
    }

    protected static function containsLatex(string $value): bool
    {
        if (blank($value)) {
            return false;
        }

        return preg_match('/\\\\\(|\\\\\[|\\\\frac|\\\\sqrt|\\\\begin\{|\\\\int|\\\\cdot|\\\\times|\\\\leq|\\\\geq|\\\\neq|\\\\pm/u', $value) === 1;
    }

    public static function monthlyRankingAnalysisHtml(PerpustakaanLiterasiMaterial $record): HtmlString
    {
        return new HtmlString(view(
            'filament.resources.perpustakaan-literasi-material-resource.partials.analytics-panel',
            [
                'analytics' => LiterasiAnalytics::forMaterial($record),
                'title' => 'Analisa Materi Bulan Ini',
                'description' => 'Ranking dan rekap hanya untuk materi ini.',
            ]
        )->render());
    }
}
