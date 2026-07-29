<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasConfirmedDeleteActions;
use App\Filament\Concerns\HasModulePermissions;
use App\Filament\Concerns\HasOptimizedAdminTable;
use App\Filament\Resources\PerpustakaanLiterasiMaterialResource\Pages;
use App\Filament\Resources\PerpustakaanLiterasiMaterialResource\RelationManagers\ResponsesRelationManager;
use App\Filament\Resources\PerpustakaanLiterasiMaterialResource\RelationManagers\SimilarityMatchesRelationManager;
use App\Jobs\QueueLiteracySimilarityReanalysis;
use App\Models\DataSiswa;
use App\Models\PerpustakaanLiterasiMaterial;
use App\Models\PerpustakaanLiterasiQuestion;
use App\Models\PerpustakaanLiterasiResponse;
use App\Models\PerpustakaanLiterasiSimilarityMatch;
use App\Support\Perpustakaan\LiterasiAnalytics;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\View as SchemaView;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema as SchemaFacade;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PerpustakaanLiterasiMaterialResource extends Resource
{
    use HasConfirmedDeleteActions;
    use HasModulePermissions;
    use HasOptimizedAdminTable;

    protected static ?string $model = PerpustakaanLiterasiMaterial::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-book-open';

    protected static string|\UnitEnum|null $navigationGroup = 'Manajemen Sekolah';

    protected static ?int $navigationSort = 9;

    protected static ?string $navigationLabel = 'Literasi Numerasi';

    protected static ?string $modelLabel = 'materi literasi numerasi';

    protected static ?string $pluralModelLabel = 'Literasi Numerasi';

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
                    ->description('Status Aktif mengatur kemunculan materi di daftar publik. Direct link tetap dapat dipakai setelah waktu buka.')
                    ->columns(['default' => 1, 'md' => 2])
                    ->schema([
                        Forms\Components\TextInput::make('title')
                            ->label('Judul Materi')
                            ->required()
                            ->maxLength(180)
                            ->columnSpanFull(),
                        Forms\Components\Select::make('program_category')
                            ->label('Kategori Soal')
                            ->options(PerpustakaanLiterasiMaterial::programCategoryOptions())
                            ->native(false)
                            ->required()
                            ->rules([Rule::in(array_keys(PerpustakaanLiterasiMaterial::programCategoryOptions()))])
                            ->validationMessages([
                                'required' => 'Kategori soal wajib dipilih.',
                                'in' => 'Kategori soal yang dipilih tidak valid.',
                            ])
                            ->helperText('Pilih kategori program agar siswa dan guru bisa membedakan jenis soal.')
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
                        Forms\Components\RichEditor::make('reading_content')
                            ->label('Isi Bacaan')
                            ->toolbarButtons([
                                ['bold', 'italic', 'underline', 'strike', 'textColor', 'highlight', 'clearFormatting'],
                                ['h2', 'h3', 'paragraph', 'lead', 'small'],
                                ['alignStart', 'alignCenter', 'alignEnd', 'alignJustify'],
                                ['blockquote', 'bulletList', 'orderedList'],
                                ['grid', 'table', 'attachFiles'],
                                ['undo', 'redo'],
                            ])
                            ->floatingToolbars([
                                'grid' => ['gridDelete'],
                                'table' => [
                                    'tableAddColumnBefore', 'tableAddColumnAfter', 'tableDeleteColumn',
                                    'tableAddRowBefore', 'tableAddRowAfter', 'tableDeleteRow',
                                    'tableMergeCells', 'tableSplitCell',
                                    'tableToggleHeaderRow', 'tableToggleHeaderCell',
                                    'tableDelete',
                                ],
                            ])
                            ->fileAttachmentsDisk('public')
                            ->fileAttachmentsDirectory('literasi/materials/reading')
                            ->fileAttachmentsVisibility('public')
                            ->fileAttachmentsAcceptedFileTypes(['image/png', 'image/jpeg', 'image/webp', 'image/gif'])
                            ->fileAttachmentsMaxSize(4096)
                            ->resizableImages()
                            ->customTextColors()
                            ->maxLength(20000)
                            ->helperText(fn (Get $get): string => $get('show_reading_latex_tools')
                                ? 'Editor mendukung tebal, miring, heading, warna teks, tabel, kolom, dan upload gambar langsung di posisi kursor. Rumus LaTeX tetap bisa diketik manual, contoh: \(x^2\), \(\frac{a}{b}\), \(\sqrt{x}\).'
                                : 'Editor mendukung tebal, miring, heading, warna teks, tabel, kolom, dan upload gambar langsung di posisi kursor.')
                            ->columnSpanFull(),
                        Forms\Components\Textarea::make('instructions')
                            ->label('Arahan / Tatib Pengerjaan')
                            ->rows(4)
                            ->maxLength(4000)
                            ->helperText('Opsional. Jika kosong, halaman publik memakai arahan default anti menyontek.')
                            ->columnSpanFull(),
                        Forms\Components\Toggle::make('student_verification_enabled')
                            ->label('Wajibkan verifikasi siswa')
                            ->default(true)
                            ->helperText('Jika aktif, siswa harus mengisi NISN atau tanggal lahir yang cocok saat mengirim jawaban.'),
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
                        Forms\Components\TextInput::make('video_url')
                            ->label('Link Video YouTube / Google Drive')
                            ->url()
                            ->maxLength(1000)
                            ->helperText('Jika link YouTube atau file Google Drive valid, video ditampilkan sebagai frame di halaman publik.'),
                        Forms\Components\Toggle::make('is_active')
                            ->label('Tampilkan di daftar publik')
                            ->helperText('Jika dimatikan, materi disembunyikan dari daftar tetapi direct link tetap dapat dibuka dan menerima jawaban setelah waktu buka.')
                            ->default(true),
                        Forms\Components\DateTimePicker::make('opens_at')
                            ->label('Mulai Dibuka')
                            ->seconds(false)
                            ->native(true)
                            ->displayFormat('d/m/Y H:i'),
                        Forms\Components\DateTimePicker::make('closes_at')
                            ->label('Ditutup Pada')
                            ->seconds(false)
                            ->native(true)
                            ->displayFormat('d/m/Y H:i'),
                    ]),
                Section::make('Pertanyaan')
                    ->description('Pertanyaan dikunci setelah ada responden agar jawaban dan analisa tetap konsisten.')
                    ->schema([
                        Forms\Components\Repeater::make('questions')
                            ->relationship()
                            ->mutateRelationshipDataBeforeFillUsing(
                                fn (array $data): array => static::prepareQuestionDataForForm($data),
                            )
                            ->mutateRelationshipDataBeforeCreateUsing(
                                fn (array $data): array => static::prepareQuestionDataForPersistence($data),
                            )
                            ->mutateRelationshipDataBeforeSaveUsing(
                                fn (array $data): array => static::prepareQuestionDataForPersistence($data),
                            )
                            ->orderColumn('sort_order')
                            ->collapsed()
                            ->reorderableWithButtons()
                            ->cloneable()
                            ->defaultItems(1)
                            ->addActionLabel('Tambah Pertanyaan')
                            ->itemLabel(fn (array $state): string => filled($state['prompt'] ?? null)
                                ? Str::limit((string) $state['prompt'], 70)
                                : 'Pertanyaan baru')
                            ->disabled(fn (?PerpustakaanLiterasiMaterial $record): bool => $record?->hasResponses() ?? false)
                            ->schema([
                                Forms\Components\Select::make('question_type')
                                    ->label('Jenis Pertanyaan')
                                    ->options(PerpustakaanLiterasiQuestion::typeOptions())
                                    ->default(PerpustakaanLiterasiQuestion::TYPE_ESSAY)
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(function (mixed $state, Set $set): void {
                                        $set('configuration', static::defaultQuestionConfiguration((string) $state));
                                    })
                                    ->helperText('Pilih Esai, tabel Benar/Salah, atau Menjodohkan. Jenis pertanyaan dikunci setelah ada responden.')
                                    ->columnSpanFull(),
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
                                Forms\Components\Repeater::make('configuration.items')
                                    ->label('Pernyataan Benar / Salah')
                                    ->visible(fn (Get $get): bool => $get('question_type') === PerpustakaanLiterasiQuestion::TYPE_TRUE_FALSE)
                                    ->required(fn (Get $get): bool => $get('question_type') === PerpustakaanLiterasiQuestion::TYPE_TRUE_FALSE)
                                    ->defaultItems(2)
                                    ->minItems(2)
                                    ->reorderableWithButtons()
                                    ->addActionLabel('Tambah Pernyataan')
                                    ->table([
                                        TableColumn::make('Kolom A · Pernyataan')
                                            ->markAsRequired()
                                            ->width('70%'),
                                        TableColumn::make('Kolom B · Kunci Jawaban')
                                            ->markAsRequired()
                                            ->width('30%'),
                                    ])
                                    ->compact()
                                    ->schema([
                                        Forms\Components\Hidden::make('id')
                                            ->default(fn (): string => (string) Str::uuid()),
                                        Forms\Components\Textarea::make('statement')
                                            ->label('Pernyataan')
                                            ->required()
                                            ->rows(2),
                                        Forms\Components\Select::make('correct')
                                            ->label('Kunci Jawaban')
                                            ->options([
                                                '1' => 'Benar',
                                                '0' => 'Salah',
                                            ])
                                            ->default('1')
                                            ->required()
                                            ->native(false),
                                    ])
                                    ->columns(1)
                                    ->columnSpanFull(),
                                Forms\Components\Repeater::make('configuration.pairs')
                                    ->label('Tabel Pasangan Soal dan Jawaban')
                                    ->visible(fn (Get $get): bool => $get('question_type') === PerpustakaanLiterasiQuestion::TYPE_MATCHING)
                                    ->required(fn (Get $get): bool => $get('question_type') === PerpustakaanLiterasiQuestion::TYPE_MATCHING)
                                    ->defaultItems(2)
                                    ->minItems(2)
                                    ->reorderableWithButtons()
                                    ->addActionLabel('Tambah Pasangan')
                                    ->helperText('Setiap baris adalah satu kunci pasangan. Murid hanya melihat item kiri dan kanan, bukan susunan kuncinya.')
                                    ->table([
                                        TableColumn::make('Kolom A · Soal')
                                            ->markAsRequired()
                                            ->width('50%'),
                                        TableColumn::make('Kolom B · Jawaban')
                                            ->markAsRequired()
                                            ->width('50%'),
                                    ])
                                    ->compact()
                                    ->schema([
                                        Forms\Components\Hidden::make('left_id')
                                            ->default(fn (): string => (string) Str::uuid()),
                                        Forms\Components\Hidden::make('right_id')
                                            ->default(fn (): string => (string) Str::uuid()),
                                        Forms\Components\Textarea::make('left_label')
                                            ->label('Kolom A · Soal')
                                            ->required()
                                            ->rows(2),
                                        Forms\Components\Textarea::make('right_label')
                                            ->label('Kolom B · Jawaban')
                                            ->required()
                                            ->rows(2),
                                    ])
                                    ->columns(1)
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
                                Forms\Components\Toggle::make('plagiarism_detection_enabled')
                                    ->label('Aktifkan deteksi plagiasi')
                                    ->default(true)
                                    ->live()
                                    ->visible(fn (Get $get): bool => ($get('question_type') ?: PerpustakaanLiterasiQuestion::TYPE_ESSAY) === PerpustakaanLiterasiQuestion::TYPE_ESSAY)
                                    ->helperText('Aktif: jawaban soal ini dibandingkan dengan jawaban siswa lain pada materi yang sama. Jawaban sama persis selalu muncul sebagai 100%; jawaban mirip muncul mulai 50%.'),
                                Forms\Components\Placeholder::make('plagiarism_detection_help')
                                    ->label('Cara kerja deteksi dan kunci jawaban')
                                    ->content(fn (Get $get): HtmlString => new HtmlString((bool) $get('plagiarism_detection_enabled')
                                        ? '<div class="text-sm leading-6 text-gray-600 dark:text-gray-300">'.
                                            '<strong>Deteksi aktif:</strong> sistem membuat indikasi plagiasi untuk jawaban yang sama persis atau mirip minimal 50%. '.
                                            'Jika kunci jawaban diisi, jawaban yang sama dengan kunci otomatis Benar, tetapi tetap masuk daftar plagiasi bila mirip/sama dengan jawaban siswa lain.'.
                                        '</div>'
                                        : '<div class="text-sm leading-6 text-gray-600 dark:text-gray-300">'.
                                            '<strong>Deteksi tidak aktif:</strong> sistem tidak membuat indikasi plagiasi untuk soal ini. '.
                                            'Jika kunci jawaban diisi, jawaban yang sama dengan kunci otomatis dinilai Benar dan tidak masuk Daftar Plagiat Per Kelas; jawaban berbeda tetap Belum dinilai untuk diperiksa guru.'.
                                        '</div>'))
                                    ->visible(fn (Get $get): bool => ($get('question_type') ?: PerpustakaanLiterasiQuestion::TYPE_ESSAY) === PerpustakaanLiterasiQuestion::TYPE_ESSAY)
                                    ->columnSpanFull(),
                                Forms\Components\Textarea::make('answer_key')
                                    ->label('Kunci Jawaban')
                                    ->rows(3)
                                    ->maxLength(4000)
                                    ->helperText(fn (Get $get): string => (bool) $get('plagiarism_detection_enabled')
                                        ? 'Mode deteksi aktif: jawaban yang sama dengan kunci otomatis Benar, dan tetap dicek plagiasi terhadap jawaban siswa lain.'
                                        : 'Mode deteksi tidak aktif: jawaban yang sama dengan kunci otomatis Benar dan tidak masuk Daftar Plagiat Per Kelas; jawaban berbeda tetap Belum dinilai.')
                                    ->visible(fn (Get $get): bool => ($get('question_type') ?: PerpustakaanLiterasiQuestion::TYPE_ESSAY) === PerpustakaanLiterasiQuestion::TYPE_ESSAY)
                                    ->columnSpanFull(),
                                Forms\Components\Toggle::make('speech_input_enabled')
                                    ->label('Izinkan murid menjawab dengan suara')
                                    ->default(false)
                                    ->visible(fn (Get $get): bool => ($get('question_type') ?: PerpustakaanLiterasiQuestion::TYPE_ESSAY) === PerpustakaanLiterasiQuestion::TYPE_ESSAY)
                                    ->helperText('Bahasa Indonesia. Aplikasi hanya menyimpan teks hasil dikte, bukan rekaman suara.')
                                    ->columnSpanFull(),
                                Forms\Components\Select::make('answer_length_preset')
                                    ->label('Preset Panjang Jawaban')
                                    ->options([
                                        '500' => 'Singkat - 500 karakter',
                                        '1000' => 'Sedang - 1.000 karakter',
                                        '2000' => 'Esai - 2.000 karakter',
                                        '4000' => 'Panjang - 4.000 karakter',
                                        'custom' => 'Atur sendiri',
                                    ])
                                    ->default('1000')
                                    ->dehydrated(false)
                                    ->live()
                                    ->afterStateHydrated(function (Forms\Components\Select $component, mixed $record): void {
                                        $max = $record instanceof PerpustakaanLiterasiQuestion
                                            ? (int) $record->max_characters
                                            : 1000;
                                        $component->state(in_array($max, [500, 1000, 2000, 4000], true) ? (string) $max : 'custom');
                                    })
                                    ->afterStateUpdated(function (mixed $state, Set $set): void {
                                        if ($state !== 'custom' && is_numeric($state)) {
                                            $set('max_characters', (int) $state);
                                        }
                                    })
                                    ->helperText('Pilih cepat sesuai jenis soal. Gunakan Atur sendiri untuk kebutuhan khusus.')
                                    ->visible(fn (Get $get): bool => ($get('question_type') ?: PerpustakaanLiterasiQuestion::TYPE_ESSAY) === PerpustakaanLiterasiQuestion::TYPE_ESSAY),
                                Forms\Components\TextInput::make('min_characters')
                                    ->label('Minimal Karakter Jawaban')
                                    ->required()
                                    ->numeric()
                                    ->minValue(0)
                                    ->maxValue(8000)
                                    ->default(20)
                                    ->visible(fn (Get $get): bool => ($get('question_type') ?: PerpustakaanLiterasiQuestion::TYPE_ESSAY) === PerpustakaanLiterasiQuestion::TYPE_ESSAY),
                                Forms\Components\TextInput::make('max_characters')
                                    ->label('Maksimal Karakter Jawaban')
                                    ->required()
                                    ->numeric()
                                    ->minValue(fn (Get $get): int => max(1, (int) ($get('min_characters') ?: 0)))
                                    ->maxValue(8000)
                                    ->default(1000)
                                    ->helperText('Sistem menolak penurunan batas jika ada jawaban tersimpan yang lebih panjang. Siswa melihat penghitung sebelum mengirim.')
                                    ->visible(fn (Get $get): bool => ($get('question_type') ?: PerpustakaanLiterasiQuestion::TYPE_ESSAY) === PerpustakaanLiterasiQuestion::TYPE_ESSAY),
                                Forms\Components\Toggle::make('is_required')
                                    ->label('Wajib diisi')
                                    ->default(true),
                            ])
                            ->columns(['default' => 1, 'md' => 2]),
                    ]),
                SchemaView::make('filament.resources.perpustakaan-literasi-material-resource.partials.latex-picker-assets'),
            ]);
    }

    public static function defaultQuestionConfiguration(string $questionType): ?array
    {
        return match (PerpustakaanLiterasiQuestion::normalizeType($questionType)) {
            PerpustakaanLiterasiQuestion::TYPE_TRUE_FALSE => [
                'items' => collect(range(1, 2))
                    ->map(fn (): array => [
                        'id' => (string) Str::uuid(),
                        'statement' => '',
                        'correct' => '1',
                    ])
                    ->all(),
            ],
            PerpustakaanLiterasiQuestion::TYPE_MATCHING => [
                'pairs' => collect(range(1, 2))
                    ->map(fn (): array => [
                        'left_id' => (string) Str::uuid(),
                        'left_label' => '',
                        'right_id' => (string) Str::uuid(),
                        'right_label' => '',
                    ])
                    ->all(),
            ],
            default => null,
        };
    }

    /**
     * Convert the canonical matching JSON into one editable table row per pair.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function prepareQuestionDataForForm(array $data): array
    {
        $questionType = PerpustakaanLiterasiQuestion::normalizeType($data['question_type'] ?? null);
        $configuration = is_array($data['configuration'] ?? null) ? $data['configuration'] : [];

        if ($questionType === PerpustakaanLiterasiQuestion::TYPE_MATCHING) {
            $rightById = collect($configuration['right'] ?? [])
                ->filter(fn (mixed $item): bool => is_array($item) && filled($item['id'] ?? null))
                ->keyBy(fn (array $item): string => (string) $item['id']);

            $pairs = collect($configuration['left'] ?? [])
                ->filter(fn (mixed $item): bool => is_array($item))
                ->map(function (array $left) use ($rightById): array {
                    $rightId = trim((string) ($left['correct_target_id'] ?? ''));
                    $right = $rightById->get($rightId, []);

                    return [
                        'left_id' => trim((string) ($left['id'] ?? '')) ?: (string) Str::uuid(),
                        'left_label' => (string) ($left['label'] ?? ''),
                        'right_id' => $rightId ?: (string) Str::uuid(),
                        'right_label' => is_array($right) ? (string) ($right['label'] ?? '') : '',
                    ];
                })
                ->values()
                ->all();

            $data['configuration'] = [
                'pairs' => $pairs !== [] ? $pairs : static::defaultQuestionConfiguration($questionType)['pairs'],
            ];

            return $data;
        }

        if ($questionType === PerpustakaanLiterasiQuestion::TYPE_TRUE_FALSE) {
            $data['configuration'] = [
                'items' => collect($configuration['items'] ?? [])
                    ->filter(fn (mixed $item): bool => is_array($item))
                    ->map(fn (array $item): array => [
                        'id' => trim((string) ($item['id'] ?? '')) ?: (string) Str::uuid(),
                        'statement' => (string) ($item['statement'] ?? ''),
                        'correct' => filter_var($item['correct'] ?? false, FILTER_VALIDATE_BOOLEAN) ? '1' : '0',
                    ])
                    ->values()
                    ->all(),
            ];

            return $data;
        }

        $data['configuration'] = null;

        return $data;
    }

    /**
     * Convert the compact admin table back into the canonical objective JSON.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function prepareQuestionDataForPersistence(array $data): array
    {
        $questionType = PerpustakaanLiterasiQuestion::normalizeType($data['question_type'] ?? null);
        $configuration = is_array($data['configuration'] ?? null) ? $data['configuration'] : [];

        if ($questionType === PerpustakaanLiterasiQuestion::TYPE_MATCHING) {
            $pairs = collect($configuration['pairs'] ?? [])
                ->filter(fn (mixed $item): bool => is_array($item))
                ->map(fn (array $item): array => [
                    'left_id' => trim((string) ($item['left_id'] ?? '')) ?: (string) Str::uuid(),
                    'left_label' => trim((string) ($item['left_label'] ?? '')),
                    'right_id' => trim((string) ($item['right_id'] ?? '')) ?: (string) Str::uuid(),
                    'right_label' => trim((string) ($item['right_label'] ?? '')),
                ])
                ->values();

            $data['configuration'] = [
                'version' => 1,
                'left' => $pairs
                    ->map(fn (array $pair): array => [
                        'id' => $pair['left_id'],
                        'label' => $pair['left_label'],
                        'correct_target_id' => $pair['right_id'],
                    ])
                    ->all(),
                'right' => $pairs
                    ->map(fn (array $pair): array => [
                        'id' => $pair['right_id'],
                        'label' => $pair['right_label'],
                    ])
                    ->all(),
            ];

            return $data;
        }

        if ($questionType === PerpustakaanLiterasiQuestion::TYPE_TRUE_FALSE) {
            $data['configuration'] = [
                'version' => 1,
                'items' => collect($configuration['items'] ?? [])
                    ->filter(fn (mixed $item): bool => is_array($item))
                    ->map(fn (array $item): array => [
                        'id' => trim((string) ($item['id'] ?? '')) ?: (string) Str::uuid(),
                        'statement' => trim((string) ($item['statement'] ?? '')),
                        'correct' => filter_var($item['correct'] ?? false, FILTER_VALIDATE_BOOLEAN),
                    ])
                    ->values()
                    ->all(),
            ];

            return $data;
        }

        $data['configuration'] = null;

        return $data;
    }

    public static function table(Table $table): Table
    {
        return static::optimizeAdminTable(
            $table,
            searchPlaceholder: 'Cari judul materi...',
            emptyStateHeading: 'Belum ada materi literasi numerasi',
            emptyStateDescription: 'Buat materi Literasi Numerasi agar siswa bisa membaca dan mengirim jawaban.'
        )
            ->heading('Daftar Materi')
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\ViewColumn::make('title')
                    ->label('Materi')
                    ->searchable()
                    ->sortable()
                    ->view('filament.resources.perpustakaan-literasi-material-resource.partials.material-table-cell')
                    ->extraAttributes(['class' => 'literasi-material-title-column']),
                Tables\Columns\TextColumn::make('schedule_window')
                    ->label('Durasi Soal')
                    ->state(fn (PerpustakaanLiterasiMaterial $record): HtmlString => static::scheduleWindowHtml($record))
                    ->html(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('program_category')
                    ->label('Kategori Soal')
                    ->options([
                        '__blank' => PerpustakaanLiterasiMaterial::uncategorizedProgramLabel(),
                    ] + PerpustakaanLiterasiMaterial::programCategoryOptions())
                    ->query(function (Builder $query, array $data): Builder {
                        $value = $data['value'] ?? null;

                        if ($value === null || $value === '') {
                            return $query;
                        }

                        return $value === '__blank'
                            ? $query->whereNull('program_category')
                            : $query->where('program_category', $value);
                    }),
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Status Aktif'),
            ])
            ->recordUrl(fn (PerpustakaanLiterasiMaterial $record): string => static::getUrl('view', ['record' => $record]))
            ->actions([
                ViewAction::make()
                    ->label('Detail'),
                EditAction::make(),
                Action::make('setProgramCategory')
                    ->label('Pilih Kategori')
                    ->icon('heroicon-o-tag')
                    ->color('warning')
                    ->visible(fn (PerpustakaanLiterasiMaterial $record): bool => static::canEdit($record))
                    ->fillForm(fn (PerpustakaanLiterasiMaterial $record): array => [
                        'program_category' => $record->program_category,
                    ])
                    ->form([
                        Forms\Components\Select::make('program_category')
                            ->label('Kategori Soal')
                            ->options(PerpustakaanLiterasiMaterial::programCategoryOptions())
                            ->required()
                            ->native(false),
                    ])
                    ->modalHeading(fn (PerpustakaanLiterasiMaterial $record): string => 'Pilih kategori untuk '.$record->title)
                    ->modalSubmitActionLabel('Simpan Kategori')
                    ->action(function (PerpustakaanLiterasiMaterial $record, array $data): void {
                        $record->forceFill([
                            'program_category' => $data['program_category'] ?? null,
                        ])->save();

                        Notification::make()
                            ->title('Kategori soal diperbarui.')
                            ->success()
                            ->send();
                    }),
                Action::make('setInstructions')
                    ->label('Atur Tatib')
                    ->icon('heroicon-o-clipboard-document-list')
                    ->color('gray')
                    ->visible(fn (PerpustakaanLiterasiMaterial $record): bool => static::canEdit($record))
                    ->fillForm(fn (PerpustakaanLiterasiMaterial $record): array => [
                        'instructions' => $record->instructionsText(),
                    ])
                    ->form([
                        Forms\Components\Textarea::make('instructions')
                            ->label('Arahan / Tatib Pengerjaan')
                            ->rows(7)
                            ->helperText('Kosongkan untuk mengikuti tatib default dari tombol Setting Tatib.')
                            ->columnSpanFull(),
                    ])
                    ->modalHeading(fn (PerpustakaanLiterasiMaterial $record): string => 'Atur tatib '.$record->title)
                    ->modalSubmitActionLabel('Simpan Tatib')
                    ->action(function (PerpustakaanLiterasiMaterial $record, array $data): void {
                        $instructions = trim((string) ($data['instructions'] ?? ''));

                        $record->forceFill([
                            'instructions' => $instructions !== '' ? $instructions : null,
                        ])->save();

                        Notification::make()
                            ->title('Tatib materi diperbarui.')
                            ->success()
                            ->send();
                    }),
                Action::make('reanalyzeSimilarity')
                    ->label('Analisa Ulang')
                    ->icon('heroicon-o-arrow-path')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->modalHeading('Analisa ulang plagiasi materi?')
                    ->modalDescription('Sistem akan menghitung ulang indikasi plagiasi untuk semua jawaban pada materi ini sesuai pengaturan soal terbaru.')
                    ->modalSubmitActionLabel('Analisa Ulang')
                    ->visible(fn (PerpustakaanLiterasiMaterial $record): bool => static::canEdit($record) && $record->hasResponses())
                    ->action(function (PerpustakaanLiterasiMaterial $record): void {
                        $total = PerpustakaanLiterasiResponse::query()
                            ->where('material_id', $record->getKey())
                            ->count();

                        QueueLiteracySimilarityReanalysis::dispatch($record->getKey());

                        Notification::make()
                            ->title('Analisa plagiasi masuk antrean')
                            ->body(number_format($total, 0, ',', '.').' responden akan dianalisa bertahap di background.')
                            ->success()
                            ->send();
                    }),
                Action::make('toggleActive')
                    ->label(fn (PerpustakaanLiterasiMaterial $record): string => $record->is_active ? 'Nonaktifkan' : 'Aktifkan')
                    ->icon(fn (PerpustakaanLiterasiMaterial $record): string => $record->is_active ? 'heroicon-o-eye-slash' : 'heroicon-o-check-circle')
                    ->color(fn (PerpustakaanLiterasiMaterial $record): string => $record->is_active ? 'warning' : 'success')
                    ->visible(fn (PerpustakaanLiterasiMaterial $record): bool => static::canEdit($record))
                    ->requiresConfirmation()
                    ->modalHeading(fn (PerpustakaanLiterasiMaterial $record): string => $record->is_active ? 'Nonaktifkan materi ini?' : 'Aktifkan materi ini?')
                    ->modalDescription(fn (PerpustakaanLiterasiMaterial $record): string => $record->is_active
                        ? 'Materi tidak akan tampil di halaman publik Literasi Numerasi.'
                        : 'Materi aktif akan tampil lagi jika jadwal buka/tutupnya masih sesuai.')
                    ->modalSubmitActionLabel(fn (PerpustakaanLiterasiMaterial $record): string => $record->is_active ? 'Nonaktifkan' : 'Aktifkan')
                    ->action(function (PerpustakaanLiterasiMaterial $record): void {
                        $record->forceFill([
                            'is_active' => ! $record->is_active,
                        ])->save();

                        Notification::make()
                            ->title($record->is_active ? 'Materi diaktifkan.' : 'Materi dinonaktifkan.')
                            ->success()
                            ->send();
                    }),
                DeleteAction::make()
                    ->label('Hapus')
                    ->requiresConfirmation()
                    ->modalHeading('Hapus materi dan history responden?')
                    ->modalDescription('Materi akan masuk daftar terhapus. History siswa, jawaban, dan data plagiasi ikut disembunyikan dari daftar aktif, tetapi masih bisa dipulihkan dari halaman History Pengerjaan Siswa.')
                    ->modalSubmitActionLabel('Ya, hapus sementara')
                    ->successNotificationTitle('Materi dan history responden masuk daftar terhapus.'),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    BulkAction::make('setSelectedProgramCategory')
                        ->label('Pilih Kategori')
                        ->icon('heroicon-o-tag')
                        ->color('warning')
                        ->visible(fn (): bool => static::canCreate())
                        ->form([
                            Forms\Components\Select::make('program_category')
                                ->label('Kategori Soal')
                                ->options(PerpustakaanLiterasiMaterial::programCategoryOptions())
                                ->required()
                                ->native(false),
                        ])
                        ->modalHeading('Pilih kategori untuk materi terpilih')
                        ->modalDescription('Kategori akan diterapkan ke semua materi yang sedang dipilih.')
                        ->modalSubmitActionLabel('Simpan Kategori')
                        ->deselectRecordsAfterCompletion()
                        ->action(function (iterable $records, array $data): void {
                            $updated = 0;

                            foreach ($records as $record) {
                                if (! $record instanceof PerpustakaanLiterasiMaterial) {
                                    continue;
                                }

                                $record->forceFill([
                                    'program_category' => $data['program_category'] ?? null,
                                ])->save();

                                $updated++;
                            }

                            Notification::make()
                                ->title('Kategori soal terpilih diperbarui.')
                                ->body(number_format($updated, 0, ',', '.').' materi diperbarui.')
                                ->success()
                                ->send();
                        }),
                    BulkAction::make('deactivateSelected')
                        ->label('Nonaktifkan Terpilih')
                        ->icon('heroicon-o-eye-slash')
                        ->color('warning')
                        ->visible(fn (): bool => static::canCreate())
                        ->requiresConfirmation()
                        ->modalHeading('Nonaktifkan materi terpilih?')
                        ->modalDescription('Materi yang dipilih tidak akan tampil di halaman publik Literasi Numerasi.')
                        ->modalSubmitActionLabel('Nonaktifkan')
                        ->deselectRecordsAfterCompletion()
                        ->action(function (iterable $records): void {
                            $updated = 0;

                            foreach ($records as $record) {
                                if (! $record instanceof PerpustakaanLiterasiMaterial || ! $record->is_active) {
                                    continue;
                                }

                                $record->forceFill(['is_active' => false])->save();
                                $updated++;
                            }

                            Notification::make()
                                ->title('Materi terpilih dinonaktifkan.')
                                ->body(number_format($updated, 0, ',', '.').' materi aktif dinonaktifkan.')
                                ->success()
                                ->send();
                        }),
                    DeleteBulkAction::make()
                        ->label('Hapus Terpilih')
                        ->requiresConfirmation()
                        ->modalHeading('Hapus materi terpilih dan history respondennya?')
                        ->modalDescription('Semua materi terpilih akan masuk daftar terhapus. History siswa, jawaban, dan data plagiasi ikut disembunyikan dari daftar aktif dan bisa dipulihkan kembali.')
                        ->modalSubmitActionLabel('Ya, hapus sementara')
                        ->successNotificationTitle('Materi terpilih dan history respondennya masuk daftar terhapus.'),
                ]),
            ]);
    }

    public static function scheduleWindowHtml(PerpustakaanLiterasiMaterial $record): HtmlString
    {
        $opensAt = $record->opens_at?->format('d/m/Y H:i') ?? 'Langsung';
        $closesAt = $record->closes_at?->format('d/m/Y H:i') ?? 'Tanpa batas';

        return new HtmlString(
            '<div class="literasi-schedule-cell">'.
                '<div class="literasi-schedule-cell__row"><span>Mulai</span><strong>'.e($opensAt).'</strong></div>'.
                '<div class="literasi-schedule-cell__row"><span>Tutup</span><strong>'.e($closesAt).'</strong></div>'.
            '</div>'
        );
    }

    public static function questionsListHtml(PerpustakaanLiterasiMaterial $record): HtmlString
    {
        $questions = $record->questions()
            ->orderBy('sort_order')
            ->get();

        if ($questions->isEmpty()) {
            return new HtmlString('<div class="text-sm text-gray-500 dark:text-gray-400">Materi ini belum memiliki soal.</div>');
        }

        $items = $questions
            ->values()
            ->map(function (PerpustakaanLiterasiQuestion $question, int $index): string {
                $answerKeyHtml = filled($question->answer_key)
                    ? '<div class="mt-3 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 dark:border-emerald-500/30 dark:bg-emerald-500/10">'.
                        '<div class="text-xs font-semibold uppercase tracking-wide text-emerald-700 dark:text-emerald-300">Kunci Jawaban</div>'.
                        '<div class="mt-1 whitespace-pre-line text-sm leading-6 text-emerald-950 dark:text-emerald-100">'.e($question->answer_key).'</div>'.
                    '</div>'
                    : '';

                return '<article class="rounded-xl border border-gray-200 bg-white p-3 dark:border-white/10 dark:bg-white/5">'.
                    '<div class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Soal '.number_format($index + 1, 0, ',', '.').'</div>'.
                    '<div class="mt-2 whitespace-pre-line text-sm font-semibold leading-6 text-gray-950 dark:text-white">'.e($question->prompt).'</div>'.
                    $answerKeyHtml.
                '</article>';
            })
            ->implode('');

        return new HtmlString('<div class="grid gap-3 md:grid-cols-2">'.$items.'</div>');
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
                    ->columnSpanFull()
                    ->collapsible()
                    ->collapsed()
                    ->columns(['default' => 1, 'md' => 4])
                    ->schema([
                        TextEntry::make('responses_total')
                            ->label('Total Responden')
                            ->state(fn (PerpustakaanLiterasiMaterial $record): int => $record->responses()->count()
                                + (SchemaFacade::hasTable('perpustakaan_literasi_dispensations')
                                    ? $record->dispensations()->count()
                                    : 0))
                            ->helperText('Jawaban nyata + dispensasi'),
                        TextEntry::make('responses_today')
                            ->label('Responden Hari Ini')
                            ->state(fn (PerpustakaanLiterasiMaterial $record): int => $record->responses()
                                ->whereDate('submitted_at', now()->toDateString())
                                ->count()
                                + (SchemaFacade::hasTable('perpustakaan_literasi_dispensations')
                                    ? $record->dispensations()->whereDate('confirmed_at', now()->toDateString())->count()
                                    : 0))
                            ->helperText('Jawaban nyata + dispensasi'),
                        TextEntry::make('classes_total')
                            ->label('Kelas Mengisi')
                            ->state(function (PerpustakaanLiterasiMaterial $record): int {
                                $classes = $record->responses()
                                    ->whereNotNull('student_class_snapshot')
                                    ->pluck('student_class_snapshot');

                                if (SchemaFacade::hasTable('perpustakaan_literasi_dispensations')) {
                                    $classes = $classes->merge(
                                        $record->dispensations()
                                            ->whereNotNull('student_class_snapshot')
                                            ->pluck('student_class_snapshot'),
                                    );
                                }

                                return $classes->filter()->unique()->count();
                            }),
                        TextEntry::make('questions_total')
                            ->label('Jumlah Soal')
                            ->state(fn (PerpustakaanLiterasiMaterial $record): int => $record->questions()->count()),
                        TextEntry::make('program_category')
                            ->label('Kategori Soal')
                            ->badge()
                            ->state(fn (PerpustakaanLiterasiMaterial $record): string => $record->programCategoryLabel())
                            ->color(fn (PerpustakaanLiterasiMaterial $record): string => $record->programCategoryColor()),
                        TextEntry::make('public_url')
                            ->label('Link Publik')
                            ->state(fn (PerpustakaanLiterasiMaterial $record): string => $record->publicUrl())
                            ->url(fn (PerpustakaanLiterasiMaterial $record): string => $record->publicUrl())
                            ->openUrlInNewTab()
                            ->columnSpanFull(),
                    ]),
                Section::make('Daftar Soal')
                    ->columnSpanFull()
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        TextEntry::make('questions_list')
                            ->hiddenLabel()
                            ->state(fn (PerpustakaanLiterasiMaterial $record): HtmlString => static::questionsListHtml($record))
                            ->html()
                            ->columnSpanFull(),
                    ]),
                Section::make('Rekap Materi')
                    ->columnSpanFull()
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        TextEntry::make('monthly_ranking_analysis')
                            ->hiddenLabel()
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
            && static::userCanModule('manage');
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
                'title' => 'Rekap Bulan Ini',
                'description' => 'Nilai, responden, dan plagiasi untuk materi ini.',
                'compact' => true,
                'material' => $record,
                'canManageDispensations' => static::canEdit($record),
            ]
        )->render());
    }
}
