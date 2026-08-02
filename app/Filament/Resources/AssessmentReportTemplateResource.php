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
            default => null,
        };

        if (! $type || ($data['view_path'] ?? null) !== $expectedView) {
            throw ValidationException::withMessages([
                'data.view_path' => 'Layout rapor harus sesuai dengan jenis ASTS atau ASAS yang dipilih.',
            ]);
        }

        if ((int) ($data['version'] ?? 0) < 1) {
            throw ValidationException::withMessages([
                'data.version' => 'Versi template minimal 1.',
            ]);
        }

        $settings = is_array($data['settings'] ?? null) ? $data['settings'] : [];
        $settings = app(AssessmentReportLayout::class)->validateAndNormalize($settings);
        $data['settings'] = app(AssessmentReportWatermark::class)->optimizeSettings($settings);

        return $data;
    }

    public static function identityIsComplete(ReportTemplate $template): bool
    {
        $settings = is_array($template->settings) ? $template->settings : [];

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
            Section::make('Versi Template Standar')
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
                        ->native(false),
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
                        ->label('Layout')
                        ->options([
                            'assessment.reports.asts' => 'Standar ASTS A4',
                            'assessment.reports.asas' => 'Standar ASAS A4',
                        ])
                        ->required()
                        ->native(false),
                    Forms\Components\DatePicker::make('effective_from')
                        ->label('Berlaku Mulai'),
                    Forms\Components\Hidden::make('is_active')
                        ->default(false),
                    Forms\Components\Placeholder::make('primary_status')
                        ->label('Status Template')
                        ->content(fn (?ReportTemplate $record): string => $record?->is_active
                            ? 'Template utama. Mengaktifkan template lain akan mengarsipkan template ini.'
                            : 'Draf/arsip. Simpan dan pratinjau dahulu, lalu gunakan aksi Jadikan Template Utama.'),
                ]),
            Section::make('Kop dan Judul')
                ->columns(['default' => 1, 'md' => 2])
                ->schema([
                    Forms\Components\TextInput::make('settings.school_name')
                        ->label('Nama Sekolah')
                        ->required()
                        ->maxLength(150),
                    Forms\Components\TextInput::make('settings.report_title')
                        ->label('Judul Dokumen')
                        ->required()
                        ->maxLength(150),
                    Forms\Components\Textarea::make('settings.school_address')
                        ->label('Alamat Sekolah')
                        ->rows(2)
                        ->maxLength(500)
                        ->columnSpanFull(),
                    Forms\Components\TextInput::make('settings.score_label')
                        ->label('Istilah Nilai')
                        ->default('Nilai Akhir')
                        ->maxLength(50),
                    Forms\Components\TextInput::make('settings.predicate_label')
                        ->label('Istilah Predikat')
                        ->default('Predikat')
                        ->maxLength(50),
                    Forms\Components\Toggle::make('settings.show_predicate')
                        ->label('Tampilkan Kolom Predikat')
                        ->default(true)
                        ->inline(false),
                    Forms\Components\Toggle::make('settings.show_description')
                        ->label('Tampilkan Kolom Capaian')
                        ->default(true)
                        ->inline(false),
                ]),
            Section::make('Tanda Tangan')
                ->columns(['default' => 1, 'md' => 2])
                ->schema([
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
            Section::make('Susunan Halaman Rapor')
                ->description('Pilih bagian yang ditampilkan, halaman 1–3, dan urutannya. Identitas, minimal satu bagian akademik, serta tanda tangan wajib tersedia.')
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
            Section::make('Watermark Opsional')
                ->description('Gambar disimpan privat dan dibekukan ke snapshot. Untuk template yang sudah dipakai, buat versi baru.')
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
                            ->formatStateUsing(fn (mixed $state): string => $state instanceof AssessmentType ? $state->label() : strtoupper((string) $state)),
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
                        TextEntry::make('settings.school_name')->label('Nama Sekolah')->placeholder('-'),
                        TextEntry::make('settings.school_address')->label('Alamat Sekolah')->placeholder('-'),
                        TextEntry::make('settings.principal_name')->label('Kepala Sekolah')->placeholder('-'),
                        TextEntry::make('settings.principal_identifier')->label('NIP/NIY')->placeholder('-'),
                        TextEntry::make('settings.place')->label('Tempat Terbit')->placeholder('-'),
                        TextEntry::make('settings.homeroom_title')->label('Sebutan Wali Kelas')->placeholder('-'),
                    ]),
                Section::make('Susunan dan Watermark')
                    ->columns(['default' => 1, 'md' => 2])
                    ->schema([
                        TextEntry::make('layout_summary')
                            ->label('Susunan Halaman')
                            ->state(fn (ReportTemplate $record): string => collect(data_get($record->settings, 'layout.sections', []))
                                ->filter(fn (mixed $section): bool => is_array($section) && (bool) ($section['enabled'] ?? true))
                                ->sortBy([['page', 'asc'], ['sort_order', 'asc']])
                                ->map(fn (array $section): string => 'Halaman '.($section['page'] ?? 1).' · '.(AssessmentReportLayout::sectionOptions()[$section['type'] ?? ''] ?? 'Bagian'))
                                ->implode("\n") ?: 'Layout standar satu halaman.')
                            ->listWithLineBreaks(),
                        TextEntry::make('watermark_summary')
                            ->label('Watermark')
                            ->state(fn (ReportTemplate $record): string => data_get($record->settings, 'watermark_enabled')
                                ? 'Aktif · '.data_get($record->settings, 'watermark_opacity', 10).'% · '.data_get($record->settings, 'watermark_position', 'center')
                                : 'Tidak aktif'),
                    ]),
            ]);
    }
}
