<?php

namespace App\Filament\Resources;

use App\Actions\Assessment\SetPrimaryReportTemplateAction;
use App\Enums\Assessment\AssessmentType;
use App\Filament\Concerns\HasAssessmentPermissions;
use App\Filament\Concerns\HasOptimizedAdminTable;
use App\Filament\Pages\Assessment\AsasReports;
use App\Filament\Pages\Assessment\AstsReports;
use App\Filament\Resources\AssessmentReportTemplateResource\Pages;
use App\Models\Assessment\ReportTemplate;
use App\Support\Assessment\AssessmentAuditLogger;
use App\Support\Assessment\Reporting\AssessmentReportLayout;
use App\Support\Assessment\Reporting\AssessmentReportWatermark;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class AssessmentReportTemplateResource extends Resource
{
    use HasAssessmentPermissions;
    use HasOptimizedAdminTable;

    protected static ?string $model = ReportTemplate::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-text';

    protected static string|\UnitEnum|null $navigationGroup = 'Manajemen Sekolah';

    protected static ?string $navigationLabel = 'Template Rapor';

    protected static ?string $modelLabel = 'template rapor';

    protected static ?string $pluralModelLabel = 'Template Rapor';

    protected static ?int $navigationSort = 12;

    protected static ?string $slug = 'penilaian/pengaturan/template-rapor';

    protected static string $assessmentManagePermission = 'penilaian.period.manage';

    public static function canEdit(Model $record): bool
    {
        return static::canAccess()
            && parent::canEdit($record)
            && $record instanceof ReportTemplate
            && ! $record->snapshots()->exists()
            && ! $record->classArtifacts()->exists();
    }

    public static function canDelete(Model $record): bool
    {
        return static::canEdit($record);
    }

    public static function validateTemplateData(array $data): array
    {
        $type = AssessmentType::tryFrom((string) ($data['type'] ?? ''));
        $expectedView = match ($type) {
            AssessmentType::ASTS => 'assessment.reports.asts',
            AssessmentType::ASAS => 'assessment.reports.asas',
            AssessmentType::ASAT => 'assessment.reports.asat',
            default => null,
        };

        if (! $type || ($data['view_path'] ?? null) !== $expectedView) {
            throw ValidationException::withMessages([
                'data.view_path' => 'Layout rapor harus sesuai dengan jenis ASTS, ASAS, atau ASAT yang dipilih.',
            ]);
        }

        if ((int) ($data['version'] ?? 0) < 1) {
            throw ValidationException::withMessages([
                'data.version' => 'Versi template minimal 1.',
            ]);
        }

        $settings = is_array($data['settings'] ?? null) ? $data['settings'] : [];
        $settings = static::normalizeReportHeaderSettings($settings);
        $settings = app(AssessmentReportLayout::class)->validateAndNormalize($settings);
        $data['settings'] = app(AssessmentReportWatermark::class)->optimizeSettings($settings);

        return $data;
    }

    /**
     * Keep report-header changes within the approved printable range.
     *
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    private static function normalizeReportHeaderSettings(array $settings): array
    {
        $layout = (string) data_get($settings, 'report_layout.identity_style', 'two_column_compact');
        $allowedLayouts = ['two_column_compact', 'one_column_full', 'two_column_wide_left'];
        if (! in_array($layout, $allowedLayouts, true)) {
            throw ValidationException::withMessages([
                'data.settings.report_layout.identity_style' => 'Gaya identitas rapor tidak dikenal.',
            ]);
        }

        foreach ([
            'identity_font_size' => [9, 12],
            'identity_table_spacing' => [0, 24],
            'kop_title_spacing' => [0, 16],
            'title_identity_spacing' => [0, 16],
            'logo_size' => [32, 60],
            'table_signature_spacing' => [0, 24],
            'signature_spacing' => [32, 110],
        ] as $field => [$min, $max]) {
            $defaults = [
                'identity_font_size' => 9,
                'identity_table_spacing' => 3,
                'kop_title_spacing' => 7,
                'title_identity_spacing' => 4,
                'logo_size' => 48,
                'table_signature_spacing' => 14,
                'signature_spacing' => 64,
            ];
            $value = data_get($settings, "report_layout.{$field}");
            // New spacing fields remain absent on old templates, preserving their frozen approved layout.
            if ($value === null && in_array($field, ['table_signature_spacing', 'signature_spacing'], true)) {
                continue;
            }
            $value ??= $defaults[$field];
            if (! is_numeric($value) || (float) $value < $min || (float) $value > $max) {
                throw ValidationException::withMessages([
                    "data.settings.report_layout.{$field}" => "Nilai harus antara {$min} dan {$max}.",
                ]);
            }

            data_set($settings, "report_layout.{$field}", (float) $value);
        }

        data_set($settings, 'report_layout.identity_style', $layout);
        $kopAlignment = (string) data_get($settings, 'report_layout.kop_alignment', 'center');
        if (! in_array($kopAlignment, ['center', 'left'], true)) {
            throw ValidationException::withMessages([
                'data.settings.report_layout.kop_alignment' => 'Perataan kop rapor tidak dikenal.',
            ]);
        }
        data_set($settings, 'report_layout.kop_alignment', $kopAlignment);
        data_set($settings, 'report_layout.show_logo', (bool) data_get($settings, 'report_layout.show_logo', true));

        foreach (['student_name' => 'Nama Siswa', 'student_number' => 'NIS/NISN', 'class' => 'Kelas', 'semester' => 'Semester', 'report_type' => 'Jenis Laporan'] as $field => $default) {
            $label = trim((string) data_get($settings, "report_layout.labels.{$field}", $default));
            if ($label === '' || mb_strlen($label) > 60) {
                throw ValidationException::withMessages([
                    "data.settings.report_layout.labels.{$field}" => 'Label wajib diisi dan maksimal 60 karakter.',
                ]);
            }

            data_set($settings, "report_layout.labels.{$field}", $label);
        }

        return $settings;
    }

    public static function identityIsComplete(ReportTemplate $template): bool
    {
        $settings = static::displaySettings($template);

        return filled(data_get($settings, 'school_name'))
            && filled(data_get($settings, 'principal_name'))
            && filled(data_get($settings, 'place'));
    }

    public static function isLocked(ReportTemplate $template): bool
    {
        return $template->snapshots()->exists() || $template->classArtifacts()->exists();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Identitas Template & Versi')
                ->description('Template hanya memakai layout standar aplikasi. HTML atau Blade bebas tidak dapat dimasukkan dari admin.')
                ->columns(['default' => 1, 'md' => 2])
                ->schema([
                    Forms\Components\TextInput::make('code')
                        ->label('Kode Template')
                        ->required()
                        ->maxLength(50),
                    Forms\Components\Select::make('type')
                        ->label('Jenis Rapor')
                        ->options(AssessmentType::options())
                        ->required()
                        ->native(false)
                        ->helperText('Pilih ASTS, ASAS, atau ASAT. Jenis ini menentukan judul, isi, dan format rapor.'),
                    Forms\Components\TextInput::make('name')
                        ->label('Nama Template')
                        ->required()
                        ->maxLength(150),
                    Forms\Components\TextInput::make('version')
                        ->label('Versi')
                        ->numeric()
                        ->minValue(1)
                        ->default(1)
                        ->required(),
                    Forms\Components\Select::make('view_path')
                        ->label('Layout Dokumen')
                        ->options([
                            'assessment.reports.asts' => 'Standar ASTS A4',
                            'assessment.reports.asas' => 'Standar ASAS A4',
                            'assessment.reports.asat' => 'Standar ASAT A4',
                        ])
                        ->required()
                        ->native(false)
                        ->helperText('Gunakan layout standar sesuai jenis rapor. Jangan mengubahnya kecuali memahami format dokumen resmi.'),
                    Forms\Components\DatePicker::make('effective_from')
                        ->label('Berlaku Mulai'),
                    Forms\Components\Hidden::make('is_active')
                        ->default(false),
                    Forms\Components\Placeholder::make('primary_status')
                        ->label('Status Template')
                        ->content(fn (?ReportTemplate $record): string => $record?->is_active
                            ? 'Template utama. Mengaktifkan template lain akan mengarsipkan template ini.'
                            : 'Draf/arsip. Simpan dan pratinjau dahulu, lalu gunakan aksi Jadikan Template Utama.'),
                    Forms\Components\Placeholder::make('version_help')
                        ->label('Jika Template Terkunci')
                        ->visible(fn (?ReportTemplate $record): bool => $record instanceof ReportTemplate && static::isLocked($record))
                        ->content('Template ini sudah dipakai oleh snapshot atau PDF kelas. Gunakan tombol Buat Versi Baru dari daftar/detail template agar rapor lama tidak berubah.')
                        ->columnSpanFull(),
                    Forms\Components\Placeholder::make('preview_help')
                        ->label('Pratinjau Aktif')
                        ->content(fn (?ReportTemplate $record): string => $record instanceof ReportTemplate
                            ? 'Klik Pratinjau Template di kanan atas setelah Simpan. Tampilan memakai data siswa contoh, tidak memerlukan periode terbit dan tidak menyimpan PDF.'
                            : 'Simpan template terlebih dahulu. Setelah tersimpan, tombol Pratinjau Template akan menampilkan data contoh tanpa memerlukan periode terbit.')
                        ->columnSpanFull(),
                ]),
            Section::make('Kop & Judul Rapor')
                ->description('Atur isi kop dan judul dokumen. Gunakan Pratinjau Template setelah menyimpan untuk melihat hasil dengan data contoh yang aman.')
                ->columns(['default' => 1, 'md' => 2])
                ->schema([
                    Forms\Components\TextInput::make('settings.foundation_name')
                        ->label('Nama Yayasan')
                        ->maxLength(150)
                        ->columnSpanFull(),
                    Forms\Components\TextInput::make('settings.school_name')
                        ->label('Nama Sekolah')
                        ->required()
                        ->maxLength(150),
                    Forms\Components\TextInput::make('settings.report_title')
                        ->label('Judul Dokumen/Rapor')
                        ->required()
                        ->maxLength(150)
                        ->helperText('Dipakai sebagai judul ASTS, ASAS, atau ASAT pada rapor baru dan pratinjau.'),
                    Forms\Components\Textarea::make('settings.school_address')
                        ->label('Alamat Sekolah')
                        ->rows(2)
                        ->maxLength(500)
                        ->columnSpanFull(),
                    Forms\Components\TextInput::make('settings.school_contact')
                        ->label('Kontak/WA/Email/Web')
                        ->maxLength(200)
                        ->helperText('Tulis dalam satu baris, misalnya WA | email | website.'),
                    Forms\Components\Select::make('settings.report_layout.kop_alignment')
                        ->label('Perataan Kop')
                        ->options(['center' => 'Tengah (standar)', 'left' => 'Kiri'])
                        ->default('center')
                        ->native(false),
                    Forms\Components\Toggle::make('settings.report_layout.show_logo')
                        ->label('Tampilkan Logo Sekolah')
                        ->default(true)
                        ->inline(false)
                        ->helperText('Menggunakan logo sekolah yang sudah tersedia; unggah logo diatur dari profil sekolah.'),
                    Forms\Components\TextInput::make('settings.report_layout.logo_size')
                        ->label('Ukuran Logo Kop')
                        ->numeric()
                        ->minValue(32)
                        ->maxValue(60)
                        ->default(48)
                        ->suffix('px'),
                    Forms\Components\TextInput::make('settings.score_label')
                        ->label('Istilah Nilai')
                        ->default('Nilai Akhir')
                        ->maxLength(50),
                    Forms\Components\TextInput::make('settings.predicate_label')
                        ->label('Istilah Predikat')
                        ->default('Predikat')
                        ->maxLength(50),
                    Forms\Components\TextInput::make('settings.description_label')
                        ->label('Istilah Capaian/Deskripsi')
                        ->default('Capaian Kompetensi')
                        ->maxLength(50),
                    Forms\Components\TextInput::make('settings.footer_text')
                        ->label('Teks Footer/Motto')
                        ->maxLength(200)
                        ->columnSpanFull()
                        ->helperText('Kosongkan untuk footer resmi sekolah dan tahun pelajaran.'),
                    Forms\Components\Toggle::make('settings.show_predicate')
                        ->label('Tampilkan Kolom Predikat')
                        ->default(true)
                        ->inline(false),
                    Forms\Components\Toggle::make('settings.show_description')
                        ->label('Tampilkan Kolom Capaian')
                        ->default(true)
                        ->inline(false),
                ]),
            Section::make('Identitas & Tabel Nilai')
                ->description('Atur label, susunan identitas, dan jarak menuju tabel nilai dengan batas aman. NIS pada rapor asli berasal dari Data Siswa NIPD.')
                ->columns(['default' => 1, 'md' => 2])
                ->schema([
                    Forms\Components\TextInput::make('settings.report_layout.kop_title_spacing')
                        ->label('Jarak kop ke judul')
                        ->numeric()
                        ->minValue(0)
                        ->maxValue(16)
                        ->step(0.5)
                        ->default(7)
                        ->suffix('pt'),
                    Forms\Components\TextInput::make('settings.report_layout.title_identity_spacing')
                        ->label('Jarak judul ke identitas')
                        ->numeric()
                        ->minValue(0)
                        ->maxValue(16)
                        ->step(0.5)
                        ->default(4)
                        ->suffix('pt'),
                    Forms\Components\Select::make('settings.report_layout.identity_style')
                        ->label('Susunan Identitas')
                        ->options([
                            'two_column_compact' => '2 kolom ringkas (standar)',
                            'one_column_full' => '1 kolom penuh',
                            'two_column_wide_left' => '2 kolom, sisi kiri lebih lebar',
                        ])
                        ->default('two_column_compact')
                        ->native(false)
                        ->helperText('Mengatur posisi blok identitas siswa sebelum tabel nilai.'),
                    Forms\Components\TextInput::make('settings.report_layout.identity_font_size')
                        ->label('Ukuran teks identitas (pt)')
                        ->numeric()
                        ->minValue(9)
                        ->maxValue(12)
                        ->step(0.5)
                        ->default(9)
                        ->suffix('pt')
                        ->helperText('Standar saat ini: 9 pt.'),
                    Forms\Components\TextInput::make('settings.report_layout.identity_table_spacing')
                        ->label('Jarak identitas ke tabel nilai')
                        ->numeric()
                        ->minValue(0)
                        ->maxValue(24)
                        ->step(0.5)
                        ->default(3)
                        ->suffix('pt')
                        ->helperText('Standar saat ini setara 3 pt; batasi agar rapor ASTS tetap dua halaman.'),
                    Forms\Components\TextInput::make('settings.report_layout.table_signature_spacing')
                        ->label('Jarak tabel ke tanda tangan')
                        ->numeric()
                        ->minValue(0)
                        ->maxValue(24)
                        ->step(0.5)
                        ->default(14)
                        ->suffix('pt'),
                    Forms\Components\TextInput::make('settings.report_layout.signature_spacing')
                        ->label('Ruang tanda tangan')
                        ->numeric()
                        ->minValue(32)
                        ->maxValue(110)
                        ->step(1)
                        ->default(64)
                        ->suffix('pt')
                        ->helperText('Tinggi ruang kosong untuk tanda tangan basah.'),
                    Forms\Components\TextInput::make('settings.report_layout.labels.student_name')
                        ->label('Label Nama Siswa')
                        ->default('Nama Siswa')
                        ->required()
                        ->maxLength(60),
                    Forms\Components\TextInput::make('settings.report_layout.labels.student_number')
                        ->label('Label NIS/NISN')
                        ->default('NIS/NISN')
                        ->required()
                        ->maxLength(60),
                    Forms\Components\TextInput::make('settings.report_layout.labels.class')
                        ->label('Label Kelas')
                        ->default('Kelas')
                        ->required()
                        ->maxLength(60),
                    Forms\Components\TextInput::make('settings.report_layout.labels.semester')
                        ->label('Label Semester')
                        ->default('Semester')
                        ->required()
                        ->maxLength(60),
                    Forms\Components\TextInput::make('settings.report_layout.labels.report_type')
                        ->label('Label Jenis Laporan')
                        ->default('Jenis Laporan')
                        ->required()
                        ->maxLength(60),
                ]),
            Section::make('Tanda Tangan')
                ->description('Nama wali kelas dan tanggal rapor tetap berasal dari snapshot. Perubahan template hanya berlaku pada snapshot/rapor baru.')
                ->columns(['default' => 1, 'md' => 2])
                ->schema([
                    Forms\Components\TextInput::make('settings.parent_signature_label')
                        ->label('Label Orang Tua/Wali')
                        ->default('Orang Tua/Wali')
                        ->maxLength(80),
                    Forms\Components\TextInput::make('settings.parent_signature_name')
                        ->label('Nama Orang Tua/Wali (opsional)')
                        ->maxLength(150),
                    Forms\Components\TextInput::make('settings.principal_signature_label')
                        ->label('Label Kepala Sekolah')
                        ->default('Kepala Sekolah')
                        ->maxLength(80),
                    Forms\Components\TextInput::make('settings.principal_name')
                        ->label('Nama Kepala Sekolah')
                        ->maxLength(150),
                    Forms\Components\TextInput::make('settings.principal_identifier')
                        ->label('NIP/NIY Kepala Sekolah')
                        ->maxLength(80),
                    Forms\Components\TextInput::make('settings.homeroom_title')
                        ->label('Sebutan Wali Kelas')
                        ->default('Wali Kelas')
                        ->maxLength(80),
                    Forms\Components\TextInput::make('settings.place')
                        ->label('Tempat Terbit')
                        ->maxLength(100),
                    Forms\Components\TextInput::make('settings.semester_status_label')
                        ->label('Istilah Status Semester')
                        ->default('Status Semester/Kenaikan Kelas')
                        ->maxLength(100)
                        ->visible(fn (Get $get): bool => (string) $get('type') === AssessmentType::ASAS->value),
                ]),
            Section::make('Tabel Nilai & Susunan Halaman')
                ->description('Pengaturan lanjutan: pilih bagian tabel nilai, halaman, dan urutannya. Biarkan nilai bawaan jika belum perlu penyesuaian.')
                ->collapsible()
                ->collapsed()
                ->schema([
                    Forms\Components\Repeater::make('settings.layout.sections')
                        ->label('Bagian Rapor')
                        ->default(AssessmentReportLayout::threePageDefaults())
                        ->minItems(3)
                        ->maxItems(16)
                        ->reorderable()
                        ->reorderableWithButtons()
                        ->addActionLabel('Tambah Bagian')
                        ->itemLabel(fn (?array $state): ?string => AssessmentReportLayout::sectionOptions()[$state['type'] ?? ''] ?? 'Bagian baru')
                        ->columns(['default' => 1, 'md' => 4])
                        ->schema([
                            Forms\Components\Select::make('type')
                                ->label('Jenis Bagian')
                                ->options(AssessmentReportLayout::sectionOptions())
                                ->required()
                                ->native(false),
                            Forms\Components\TextInput::make('title')
                                ->label('Judul pada Rapor')
                                ->maxLength(120),
                            Forms\Components\Select::make('page')
                                ->label('Halaman')
                                ->options([1 => 'Halaman 1', 2 => 'Halaman 2', 3 => 'Halaman 3'])
                                ->required()
                                ->native(false),
                            Forms\Components\TextInput::make('sort_order')
                                ->label('Urutan')
                                ->numeric()
                                ->integer()
                                ->minValue(0)
                                ->maxValue(999)
                                ->default(10)
                                ->required(),
                            Forms\Components\Toggle::make('enabled')
                                ->label('Tampilkan')
                                ->default(true)
                                ->inline(false),
                        ])
                        ->columnSpanFull(),
                ]),
            Section::make('Footer & Watermark')
                ->description('Pengaturan lanjutan untuk watermark. Footer diatur pada bagian Kop & Judul; watermark dibekukan ke snapshot dan tetap privat.')
                ->collapsible()
                ->collapsed()
                ->columns(['default' => 1, 'md' => 2])
                ->schema([
                    Forms\Components\Toggle::make('settings.watermark_enabled')
                        ->label('Tampilkan Watermark')
                        ->default(false)
                        ->live()
                        ->inline(false),
                    Forms\Components\FileUpload::make('settings.watermark_path')
                        ->label('Gambar Watermark')
                        ->disk('local')
                        ->directory('assessment-report-template-assets/uploads')
                        ->acceptedFileTypes(['image/png', 'image/jpeg', 'image/webp'])
                        ->maxSize(1024)
                        ->visibility('private')
                        ->required(fn (Get $get): bool => (bool) $get('settings.watermark_enabled'))
                        ->helperText('PNG/JPG/WebP maksimal 1 MB. Gambar dioptimalkan maksimal 1600 px.')
                        ->columnSpanFull(),
                    Forms\Components\Select::make('settings.watermark_opacity')
                        ->label('Transparansi')
                        ->options([
                            5 => '5% · sangat tipis',
                            10 => '10% · disarankan',
                            15 => '15%',
                            20 => '20%',
                            25 => '25% · paling tegas',
                        ])
                        ->default(10)
                        ->native(false),
                    Forms\Components\Select::make('settings.watermark_position')
                        ->label('Posisi')
                        ->options([
                            'top' => 'Bagian Atas',
                            'center' => 'Tengah',
                            'bottom' => 'Bagian Bawah',
                        ])
                        ->default('center')
                        ->native(false),
                    Forms\Components\Select::make('settings.watermark_width')
                        ->label('Ukuran')
                        ->options([
                            30 => '30% · kecil',
                            45 => '45%',
                            60 => '60% · disarankan',
                            75 => '75%',
                            90 => '90% · besar',
                        ])
                        ->default(60)
                        ->native(false),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return static::optimizeAdminTable(
            $table,
            searchPlaceholder: 'Cari template atau kode...',
            emptyStateHeading: 'Belum ada template rapor',
            emptyStateDescription: 'Jalankan assessment:install-defaults untuk memasang template ASTS dan ASAS standar.'
        )
            ->defaultSort('created_at', 'desc')
            ->contentGrid([
                'default' => 1,
                'md' => 2,
                'xl' => 2,
            ])
            ->recordClasses('assessment-template-card')
            ->columns([
                Stack::make([
                    Split::make([
                        Tables\Columns\TextColumn::make('name')
                            ->label('Template')
                            ->description(fn (ReportTemplate $record): string => "{$record->code} · v{$record->version}")
                            ->searchable(['name', 'code'])
                            ->weight('bold')
                            ->wrap(),
                        Tables\Columns\TextColumn::make('type')
                            ->label('Jenis')
                            ->badge()
                            ->formatStateUsing(fn (mixed $state): string => $state instanceof AssessmentType ? $state->label() : strtoupper((string) $state)),
                    ])->from('sm'),
                    Split::make([
                        Tables\Columns\TextColumn::make('primary_label')
                            ->label('Status')
                            ->state(fn (ReportTemplate $record): string => $record->is_active ? 'Template Utama' : 'Arsip/Draf')
                            ->badge()
                            ->color(fn (ReportTemplate $record): string => $record->is_active ? 'success' : 'gray'),
                        Tables\Columns\TextColumn::make('completeness_label')
                            ->label('Kelengkapan')
                            ->state(fn (ReportTemplate $record): string => static::identityIsComplete($record) ? 'Identitas Lengkap' : 'Belum Lengkap')
                            ->badge()
                            ->color(fn (ReportTemplate $record): string => static::identityIsComplete($record) ? 'success' : 'danger'),
                        Tables\Columns\TextColumn::make('lock_label')
                            ->label('Perubahan')
                            ->state(fn (ReportTemplate $record): string => ($record->snapshots_count + $record->class_artifacts_count) > 0 ? 'Terkunci' : 'Dapat Diubah')
                            ->badge()
                            ->color(fn (ReportTemplate $record): string => ($record->snapshots_count + $record->class_artifacts_count) > 0 ? 'warning' : 'info'),
                    ])->from('sm'),
                    Tables\Columns\TextColumn::make('usage_summary')
                        ->label('Penggunaan')
                        ->state(fn (ReportTemplate $record): string => "{$record->snapshots_count} snapshot · {$record->completed_class_pdfs_count} PDF kelas selesai · {$record->generation_runs_count} revisi")
                        ->icon('heroicon-o-archive-box')
                        ->wrap(),
                    Tables\Columns\TextColumn::make('period_usage')
                        ->label('Dipakai Periode')
                        ->state(function (ReportTemplate $record): string {
                            $periodNames = $record->generationRuns
                                ->pluck('period.name')
                                ->filter()
                                ->unique()
                                ->values();

                            return $periodNames->isEmpty()
                                ? 'Belum dipakai periode'
                                : 'Periode: '.$periodNames->implode(', ');
                        })
                        ->icon('heroicon-o-calendar-days')
                        ->wrap(),
                    Tables\Columns\TextColumn::make('effective_from')
                        ->label('Berlaku')
                        ->date('d/m/Y')
                        ->prefix('Berlaku mulai: ')
                        ->placeholder('Berlaku mulai: sekarang'),
                ])->space(2),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')->options(AssessmentType::options()),
            ])
            ->actions([
                ViewAction::make()
                    ->label('Lihat Detail')
                    ->button()
                    ->color('gray'),
                EditAction::make()->visible(fn (ReportTemplate $record): bool => static::canEdit($record)),
                Action::make('preview')
                    ->label('Pratinjau')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->button()
                    ->url(fn (ReportTemplate $record): string => ($record->type === AssessmentType::ASAS
                        ? AsasReports::getUrl(['template' => $record->getKey()])
                        : AstsReports::getUrl(['template' => $record->getKey()]))),
                Action::make('set_primary')
                    ->label('Jadikan Template Utama')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->button()
                    ->visible(fn (ReportTemplate $record): bool => ! $record->is_active && static::canManageAssessment())
                    ->authorize(fn (ReportTemplate $record): bool => Gate::allows('update', $record))
                    ->requiresConfirmation()
                    ->modalDescription('Template ini menjadi pilihan utama. Template utama lain dengan jenis yang sama otomatis diarsipkan tanpa mengubah snapshot lama.')
                    ->action(function (ReportTemplate $record): void {
                        app(SetPrimaryReportTemplateAction::class)->execute(auth()->user(), $record);
                        Notification::make()
                            ->title('Template utama diperbarui')
                            ->body('Template lain dengan jenis yang sama telah diarsipkan.')
                            ->success()
                            ->send();
                    }),
                Action::make('new_version')
                    ->label('Buat Versi Baru')
                    ->icon('heroicon-o-document-duplicate')
                    ->color('info')
                    ->authorize(fn (ReportTemplate $record): bool => static::canManageAssessment()
                        && Gate::allows('view', $record)
                        && Gate::allows('create', ReportTemplate::class))
                    ->visible(fn (): bool => static::canManageAssessment())
                    ->requiresConfirmation()
                    ->action(function (ReportTemplate $record, mixed $livewire): void {
                        abort_unless(static::canAccess() && static::canManageAssessment(), 403);
                        Gate::authorize('create', ReportTemplate::class);

                        $copy = DB::transaction(function () use ($record): ReportTemplate {
                            $versions = ReportTemplate::query()
                                ->where('code', $record->code)
                                ->lockForUpdate()
                                ->get();
                            $source = $versions->firstWhere('id', $record->getKey());
                            abort_unless($source instanceof ReportTemplate, 404);
                            Gate::authorize('view', $source);

                            $copy = $source->replicate();
                            $copy->version = ((int) $versions->max('version')) + 1;
                            $copy->is_active = false;
                            $copy->save();

                            app(AssessmentAuditLogger::class)->record(
                                actor: auth()->user(),
                                event: 'report_template.version_created',
                                subject: $copy,
                                oldValues: [
                                    'source_template_id' => $source->getKey(),
                                    'source_version' => $source->version,
                                ],
                                newValues: [
                                    'code' => $copy->code,
                                    'version' => $copy->version,
                                    'is_active' => false,
                                ],
                            );

                            return $copy;
                        }, 3);

                        Notification::make()
                            ->title("Versi {$copy->version} dibuat")
                            ->body('Versi baru belum aktif dan dapat disunting tanpa mengubah snapshot lama.')
                            ->success()
                            ->send();

                        $livewire->redirect(
                            static::getUrl('edit', ['record' => $copy]),
                            navigate: true,
                        );
                    }),
                DeleteAction::make()
                    ->visible(fn (ReportTemplate $record): bool => static::canDelete($record))
                    ->databaseTransaction()
                    ->before(function (ReportTemplate $record): void {
                        $template = ReportTemplate::query()
                            ->whereKey($record->getKey())
                            ->lockForUpdate()
                            ->firstOrFail();

                        abort_unless(static::canDelete($template), 403);
                    }),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAssessmentReportTemplates::route('/'),
            'create' => Pages\CreateAssessmentReportTemplate::route('/create'),
            'view' => Pages\ViewAssessmentReportTemplate::route('/{record}'),
            'edit' => Pages\EditAssessmentReportTemplate::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with('generationRuns.period')
            ->withCount([
                'snapshots',
                'classArtifacts',
                'generationRuns',
                'classArtifacts as completed_class_pdfs_count' => fn (Builder $query): Builder => $query
                    ->where('generation_status', 'completed')
                    ->whereNotNull('pdf_path'),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->columns(['default' => 1, 'md' => 2])
            ->schema([
                Section::make('Status dan Versi')
                    ->description('Template yang sudah dipakai dikunci agar snapshot dan PDF lama tidak berubah.')
                    ->columns(['default' => 1, 'md' => 2])
                    ->schema([
                        TextEntry::make('name')->label('Nama Template'),
                        TextEntry::make('code')->label('Kode'),
                        TextEntry::make('type')->label('Jenis')->badge()
                            ->formatStateUsing(fn (mixed $state): string => static::typeLabel($state)),
                        TextEntry::make('version')->label('Versi'),
                        IconEntry::make('is_active')->label('Template Utama')->boolean(),
                        TextEntry::make('change_status')->label('Status Perubahan')
                            ->state(fn (ReportTemplate $record): string => static::isLocked($record) ? 'Terkunci karena sudah memiliki snapshot/PDF.' : 'Masih dapat diubah.'),
                        TextEntry::make('usage')->label('Riwayat Penggunaan')
                            ->state(fn (ReportTemplate $record): string => $record->snapshots()->count()
                                .' snapshot · '
                                .$record->classArtifacts()
                                    ->where('generation_status', 'completed')
                                    ->whereNotNull('pdf_path')
                                    ->count()
                                .' PDF kelas selesai · '
                                .$record->generationRuns()->count()
                                .' revisi'),
                        TextEntry::make('period_usage')->label('Dipakai pada Periode')
                            ->state(fn (ReportTemplate $record): string => $record->generationRuns()
                                ->with('period')
                                ->get()
                                ->pluck('period.name')
                                ->filter()
                                ->unique()
                                ->values()
                                ->implode(', '))
                            ->placeholder('Belum dipakai periode'),
                        TextEntry::make('effective_from')->label('Berlaku Mulai')->date('d/m/Y')->placeholder('Sekarang'),
                    ]),
                Section::make('Sumber Data Rapor')
                    ->schema([
                        TextEntry::make('source_guide')
                            ->hiddenLabel()
                            ->state('Identitas sekolah berasal dari Profil Sekolah kecuali dioverride template. Logo berasal dari branding aplikasi. Kepala sekolah, tempat terbit, watermark, dan istilah kolom berasal dari template. Siswa, wali kelas, nilai, serta tanggal rapor berasal dari snapshot periode.')
                            ->columnSpanFull(),
                    ]),
                Section::make('Identitas dan Tanda Tangan')
                    ->columns(['default' => 1, 'md' => 2])
                    ->schema([
                        TextEntry::make('school_name')->label('Nama Sekolah')->state(fn (ReportTemplate $record): ?string => static::displayScalarSetting($record, 'school_name'))->placeholder('-'),
                        TextEntry::make('school_address')->label('Alamat Sekolah')->state(fn (ReportTemplate $record): ?string => static::displayScalarSetting($record, 'school_address'))->placeholder('-'),
                        TextEntry::make('principal_name')->label('Kepala Sekolah')->state(fn (ReportTemplate $record): ?string => static::displayScalarSetting($record, 'principal_name'))->placeholder('-'),
                        TextEntry::make('principal_identifier')->label('NIP/NIY')->state(fn (ReportTemplate $record): ?string => static::displayScalarSetting($record, 'principal_identifier'))->placeholder('-'),
                        TextEntry::make('place')->label('Tempat Terbit')->state(fn (ReportTemplate $record): ?string => static::displayScalarSetting($record, 'place'))->placeholder('-'),
                        TextEntry::make('homeroom_title')->label('Sebutan Wali Kelas')->state(fn (ReportTemplate $record): ?string => static::displayScalarSetting($record, 'homeroom_title'))->placeholder('-'),
                    ]),
                Section::make('Susunan dan Watermark')
                    ->columns(['default' => 1, 'md' => 2])
                    ->schema([
                        TextEntry::make('layout_summary')
                            ->label('Susunan Halaman')
                            ->state(fn (ReportTemplate $record): string => collect(static::layoutSections($record))
                                ->filter(fn (mixed $section): bool => is_array($section) && (bool) ($section['enabled'] ?? true))
                                ->sortBy([['page', 'asc'], ['sort_order', 'asc']])
                                ->map(fn (array $section): string => 'Halaman '.($section['page'] ?? 1).' · '.(AssessmentReportLayout::sectionOptions()[$section['type'] ?? ''] ?? 'Bagian'))
                                ->implode("\n") ?: 'Layout standar satu halaman.')
                            ->listWithLineBreaks(),
                        TextEntry::make('watermark_summary')
                            ->label('Watermark')
                            ->state(fn (ReportTemplate $record): string => static::watermarkIsEnabled($record)
                                ? 'Aktif · '.(static::displayScalarSetting($record, 'watermark_opacity') ?? '10').'% · '.(static::displayScalarSetting($record, 'watermark_position') ?? 'center')
                                : 'Tidak aktif'),
                    ]),
            ]);
    }

    /** @return array<string, mixed> */
    private static function displaySettings(ReportTemplate $template): array
    {
        return is_array($template->settings) ? $template->settings : [];
    }

    /** @return array<int, mixed> */
    private static function layoutSections(ReportTemplate $template): array
    {
        $sections = data_get(static::displaySettings($template), 'layout.sections', []);

        return is_array($sections) ? $sections : [];
    }

    private static function watermarkIsEnabled(ReportTemplate $template): bool
    {
        return filter_var(
            data_get(static::displaySettings($template), 'watermark_enabled', false),
            FILTER_VALIDATE_BOOLEAN,
        );
    }

    private static function displayScalarSetting(ReportTemplate $template, string $path): ?string
    {
        $value = data_get(static::displaySettings($template), $path);

        return is_scalar($value) || $value instanceof \Stringable
            ? (string) $value
            : null;
    }

    private static function typeLabel(mixed $type): string
    {
        if ($type instanceof AssessmentType) {
            return $type->label();
        }

        return is_scalar($type) ? strtoupper((string) $type) : '-';
    }
}
