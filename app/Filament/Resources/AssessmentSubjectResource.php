<?php

namespace App\Filament\Resources;

use App\Actions\Assessment\SyncOpenPeriodSubjectsAction;
use App\Enums\Assessment\AssessmentPeriodStatus;
use App\Enums\Assessment\AssessmentType;
use App\Filament\Concerns\HasAssessmentPermissions;
use App\Filament\Concerns\HasOptimizedAdminTable;
use App\Filament\Resources\AssessmentSubjectResource\Pages;
use App\Models\Assessment\AssessmentPeriod;
use App\Models\Assessment\AssessmentScheme;
use App\Models\Assessment\Semester;
use App\Models\Assessment\Subject;
use App\Models\Assessment\SubjectCategory;
use App\Models\Assessment\TeachingAssignment;
use App\Models\GuruTendik;
use App\Models\Rombel;
use App\Models\User;
use App\Support\Assessment\AssessmentActionFailureNotification;
use App\Support\Assessment\AssessmentAuditLogger;
use App\Support\Assessment\AssessmentPageMap;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\ValidationException;
use Throwable;

class AssessmentSubjectResource extends Resource
{
    use HasAssessmentPermissions;
    use HasOptimizedAdminTable;

    protected static ?string $model = Subject::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-book-open';

    protected static ?string $navigationLabel = 'Mapel Penilaian';

    protected static ?string $modelLabel = 'mata pelajaran';

    protected static ?string $pluralModelLabel = 'Mata Pelajaran Penilaian';

    protected static ?string $slug = 'penilaian/pengaturan/mapel';

    protected static string $assessmentManagePermission = 'penilaian.period.manage';

    /** @var array<int, bool> */
    protected static array $teacherReadinessCache = [];

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Identitas Mata Pelajaran')
                ->description('Kode dipakai pada workbook dan penugasan guru. Kelompok serta urutan menentukan susunan rapor.')
                ->columns(['default' => 1, 'md' => 2])
                ->schema([
                    Forms\Components\TextInput::make('code')
                        ->label('Kode Mapel')
                        ->required()
                        ->maxLength(40)
                        ->unique(ignoreRecord: true),
                    Forms\Components\TextInput::make('name')
                        ->label('Nama Mapel')
                        ->required()
                        ->maxLength(150),
                    Forms\Components\Textarea::make('description')
                        ->label('Deskripsi')
                        ->rows(2)
                        ->maxLength(1000)
                        ->columnSpanFull(),
                ]),
            Section::make('Fallback Kelompok Lama dan Urutan Mapel')
                ->description('Kategori rapor utama dipilih per kelas melalui Atur Guru & Kelas. Field kelompok lama ini hanya dipertahankan untuk workbook dan data historis.')
                ->columns(['default' => 1, 'md' => 2])
                ->schema([
                    Forms\Components\TextInput::make('report_group_code')
                        ->label('Kode Kelompok')
                        ->required()
                        ->maxLength(40)
                        ->default('BELUM'),
                    Forms\Components\TextInput::make('report_group_name')
                        ->label('Nama Kelompok')
                        ->required()
                        ->maxLength(120)
                        ->default('Belum Dikelompokkan'),
                    Forms\Components\TextInput::make('report_group_sort_order')
                        ->label('Urutan Kelompok')
                        ->numeric()
                        ->integer()
                        ->minValue(0)
                        ->maxValue(999)
                        ->default(999)
                        ->required(),
                    Forms\Components\TextInput::make('sort_order')
                        ->label('Urutan Mapel')
                        ->numeric()
                        ->integer()
                        ->minValue(0)
                        ->maxValue(999)
                        ->default(0)
                        ->required(),
                    Forms\Components\Toggle::make('is_active')
                        ->label('Mapel Aktif')
                        ->default(true)
                        ->inline(false),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return static::optimizeAdminTable(
            $table,
            searchPlaceholder: 'Cari kode, mapel, atau kelompok...',
            emptyStateHeading: 'Belum ada mata pelajaran',
            emptyStateDescription: 'Tambah melalui halaman ini atau gunakan Impor Master Excel.'
        )
            ->defaultSort('sort_order')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with([
                'teachingAssignments' => fn ($assignments) => $assignments
                    ->where('is_active', true)
                    ->with(['teacher:id,nama', 'rombel:id,nama', 'category:id,name']),
            ]))
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Mata Pelajaran')
                    ->description(fn (Subject $record): string => $record->code)
                    ->searchable(['name', 'code'])
                    ->wrap(),
                Tables\Columns\TextColumn::make('report_group_name')
                    ->label('Kelompok Rapor')
                    ->description(fn (Subject $record): string => $record->report_group_code)
                    ->badge()
                    ->color(fn (Subject $record): string => $record->report_group_code === 'BELUM' ? 'warning' : 'info')
                    ->searchable()
                    ->wrap()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('active_plotting_summary')
                    ->label('Guru, Kelas, dan Kategori')
                    ->state(fn (Subject $record): string => static::plottingSummary($record))
                    ->wrap()
                    ->placeholder('Belum diplot')
                    ->searchable(false),
                Tables\Columns\TextColumn::make('report_group_sort_order')
                    ->label('Urutan Kelompok')
                    ->numeric()
                    ->visibleFrom('md'),
                Tables\Columns\TextColumn::make('sort_order')
                    ->label('Urutan Mapel')
                    ->numeric()
                    ->visibleFrom('md'),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Aktif')
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('semester_plotting')
                    ->label('Semester Plotting')
                    ->options(fn (): array => static::semesterOptions())
                    ->searchable()
                    ->preload()
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        filled($data['value'] ?? null),
                        fn (Builder $subjects): Builder => $subjects->whereHas(
                            'teachingAssignments',
                            fn (Builder $assignments): Builder => $assignments
                                ->where('assessment_semester_id', (int) $data['value'])
                                ->where('is_active', true),
                        ),
                    )),
            ])
            ->actions([
                Action::make('plotTeachers')
                    ->label('Atur Guru & Kelas')
                    ->icon('heroicon-o-user-group')
                    ->color('primary')
                    ->visible(fn (): bool => static::canManageAssessment())
                    ->slideOver()
                    ->modalWidth('2xl')
                    ->modalHeading(fn (Subject $record): string => 'Atur Guru & Kelas · '.$record->name)
                    ->modalDescription('Satu mapel hanya boleh memiliki satu guru penanggung jawab pada kelas yang sama. Data ini otomatis menjadi scope Input Nilai pada periode berikutnya.')
                    ->fillForm(fn (Subject $record): array => static::plottingFormData($record))
                    ->form([
                        Forms\Components\Select::make('semester_id')
                            ->label('Semester')
                            ->options(fn (): array => static::semesterOptions())
                            ->searchable()
                            ->preload()
                            ->native(false)
                            ->required(),
                        Forms\Components\Repeater::make('assignments')
                            ->label('Plotting Guru')
                            ->helperText('Guru yang belum memiliki akun tertaut atau akses Input Nilai ditandai belum siap dan tidak dapat disimpan.')
                            ->minItems(0)
                            ->defaultItems(0)
                            ->reorderable()
                            ->addActionLabel('Tambah plotting')
                            ->columns(['default' => 1, 'lg' => 2])
                            ->schema([
                                Forms\Components\Select::make('teacher_id')
                                    ->label('Guru Penanggung Jawab')
                                    ->hintAction(
                                        Action::make('openAssessmentAccountSettings')
                                            ->label('Atur akses akun')
                                            ->url(fn (): ?string => UserResource::canViewAny() ? UserResource::getUrl() : null)
                                            ->visible(fn (): bool => UserResource::canViewAny())
                                            ->openUrlInNewTab(),
                                    )
                                    ->options(fn (): array => static::teacherOptions())
                                    ->searchable()
                                    ->preload()
                                    ->native(false)
                                    ->disableOptionWhen(fn (string|int $value): bool => ! static::teacherIdReady((int) $value))
                                    ->required(),
                                Forms\Components\Select::make('category_id')
                                    ->label('Kategori Rapor')
                                    ->options(fn (): array => SubjectCategory::query()
                                        ->where('is_active', true)
                                        ->orderBy('sort_order')
                                        ->pluck('name', 'id')
                                        ->all())
                                    ->native(false)
                                    ->required(),
                                Forms\Components\Select::make('rombel_ids')
                                    ->label('Kelas')
                                    ->options(fn (): array => Rombel::query()
                                        ->where('is_active', true)
                                        ->orderBy('nama')
                                        ->pluck('nama', 'id')
                                        ->all())
                                    ->multiple()
                                    ->searchable()
                                    ->preload()
                                    ->native(false)
                                    ->required()
                                    ->columnSpanFull(),
                            ]),
                    ])
                    ->action(function (Subject $record, array $data): void {
                        try {
                            $summary = static::savePlotting($record, $data);
                            Notification::make()
                                ->title('Plotting guru tersimpan')
                                ->body("{$summary['active']} kelas aktif; {$summary['disabled']} plotting lama dinonaktifkan. Periode lama dan nilai siswa tidak berubah.")
                                ->success()
                                ->send();
                        } catch (Throwable $exception) {
                            report($exception);
                            AssessmentActionFailureNotification::send($exception, 'menyimpan plotting guru dan kelas');
                        }
                    }),
                Action::make('applyToOpenPeriod')
                    ->label('Terapkan ke Periode Terbuka')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->visible(fn (): bool => static::canManageAssessment())
                    ->modalWidth('xl')
                    ->modalHeading('Masukkan mapel ke periode terbuka')
                    ->modalDescription('Skema khusus tetap diprioritaskan. Jika diperlukan, sistem membuat satu fallback bersama dengan menyalin skema sumber.')
                    ->modalSubmitActionLabel('Masukkan Mapel')
                    ->form(fn (Subject $record): array => static::syncPeriodForm([(int) $record->getKey()]))
                    ->action(function (Subject $record, array $data, SyncOpenPeriodSubjectsAction $sync): void {
                        $period = null;
                        try {
                            $period = AssessmentPeriod::query()->findOrFail((int) $data['period_id']);
                            $actor = auth()->user();
                            abort_unless($actor instanceof User, 403);
                            $summary = $sync->execute($actor, $period, [(int) $record->getKey()], static::sourceSchemeId($data));
                            static::sendSyncSuccessNotification($period, $summary);
                        } catch (Throwable $exception) {
                            report($exception);
                            AssessmentActionFailureNotification::send($exception, 'menerapkan plotting ke periode berjalan', $period);
                        }
                    }),
                EditAction::make(),
                DeleteAction::make()
                    ->visible(fn (Subject $record): bool => ! $record->teachingAssignments()->exists()),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    BulkAction::make('syncSelectedToOpenPeriod')
                        ->label('Masukkan Mapel Terpilih ke Periode')
                        ->icon('heroicon-o-arrow-down-on-square-stack')
                        ->color('primary')
                        ->visible(fn (): bool => static::canManageAssessment())
                        ->modalWidth('xl')
                        ->modalHeading('Masukkan mapel terpilih ke periode terbuka')
                        ->modalDescription('Sinkronisasi bersifat aditif. Assignment lama, nilai, dan snapshot/PDF historis tidak dihapus atau ditimpa.')
                        ->modalSubmitActionLabel('Masukkan Mapel Terpilih')
                        ->form(fn (Collection $records): array => static::syncPeriodForm($records->modelKeys()))
                        ->deselectRecordsAfterCompletion()
                        ->action(function (Collection $records, array $data, SyncOpenPeriodSubjectsAction $sync): void {
                            $period = null;
                            try {
                                $period = AssessmentPeriod::query()->findOrFail((int) $data['period_id']);
                                $actor = auth()->user();
                                abort_unless($actor instanceof User, 403);
                                $summary = $sync->execute($actor, $period, $records->modelKeys(), static::sourceSchemeId($data));
                                static::sendSyncSuccessNotification($period, $summary);
                            } catch (Throwable $exception) {
                                report($exception);
                                AssessmentActionFailureNotification::send($exception, 'memasukkan mapel terpilih ke periode', $period);
                            }
                        }),
                    BulkAction::make('syncUnlockedPeriodMetadata')
                        ->label('Terapkan Kelompok ke Periode Berjalan')
                        ->icon('heroicon-o-arrow-path')
                        ->color('warning')
                        ->visible(fn (): bool => static::canManageAssessment())
                        ->requiresConfirmation()
                        ->modalHeading('Terapkan metadata rapor ke periode yang belum dikunci?')
                        ->modalDescription('Kelompok dan urutan mapel terpilih disalin hanya ke assignment periode yang belum Dikunci/Diterbitkan. Nilai siswa tidak diubah dan snapshot/PDF lama tidak disentuh.')
                        ->modalSubmitActionLabel('Ya, Terapkan')
                        ->deselectRecordsAfterCompletion()
                        ->action(function (Collection $records): void {
                            abort_unless(static::canManageAssessment(), 403);
                            $actor = auth()->user();
                            abort_unless($actor instanceof User, 403);
                            $updated = 0;

                            DB::transaction(function () use ($records, $actor, &$updated): void {
                                foreach ($records as $subject) {
                                    abort_unless($subject instanceof Subject, 422);
                                    $assignments = $subject->periodAssignments()
                                        ->with('sourceTeachingAssignment.category')
                                        ->whereHas('period', fn ($query) => $query->whereNotIn('status', [
                                            AssessmentPeriodStatus::LOCKED->value,
                                            AssessmentPeriodStatus::PUBLISHED->value,
                                        ]))
                                        ->lockForUpdate()
                                        ->get();

                                    foreach ($assignments as $assignment) {
                                        $old = [
                                            'group_code' => $assignment->subject_group_code_snapshot,
                                            'group_name' => $assignment->subject_group_name_snapshot,
                                            'group_sort_order' => $assignment->subject_group_sort_order_snapshot,
                                            'subject_sort_order' => $assignment->subject_sort_order_snapshot,
                                        ];
                                        $category = $assignment->sourceTeachingAssignment?->category;
                                        $new = [
                                            'group_code' => $category?->code ?: ($subject->report_group_code ?: 'BELUM'),
                                            'group_name' => $category?->name ?: ($subject->report_group_name ?: 'Belum Dikelompokkan'),
                                            'group_sort_order' => (int) ($category?->sort_order ?? $subject->report_group_sort_order ?? 999),
                                            'subject_sort_order' => (int) ($subject->sort_order ?? 0),
                                        ];

                                        if ($old === $new) {
                                            continue;
                                        }

                                        $assignment->forceFill([
                                            'subject_group_code_snapshot' => $new['group_code'],
                                            'subject_group_name_snapshot' => $new['group_name'],
                                            'subject_group_sort_order_snapshot' => $new['group_sort_order'],
                                            'subject_sort_order_snapshot' => $new['subject_sort_order'],
                                        ])->save();
                                        app(AssessmentAuditLogger::class)->record(
                                            actor: $actor,
                                            event: 'assignment.report_metadata_synchronized',
                                            subject: $assignment,
                                            oldValues: $old,
                                            newValues: $new,
                                            reason: 'Sinkronisasi eksplisit dari Mapel Penilaian sebelum periode dikunci.',
                                        );
                                        $updated++;
                                    }
                                }
                            }, 3);

                            Notification::make()
                                ->title('Metadata rapor diterapkan')
                                ->body($updated.' assignment periode berjalan diperbarui. Periode terkunci/diterbitkan dan PDF lama tidak berubah.')
                                ->{$updated > 0 ? 'success' : 'warning'}()
                                ->send();
                        }),
                ]),
            ]);
    }

    public static function syncAllActiveAction(): Action
    {
        return Action::make('syncAllActiveToOpenPeriod')
            ->label('Masukkan Semua Mapel Aktif ke Periode')
            ->icon('heroicon-o-arrow-down-on-square-stack')
            ->color('primary')
            ->visible(fn (): bool => static::canManageAssessment())
            ->modalWidth('xl')
            ->modalHeading('Masukkan semua mapel aktif ke periode terbuka')
            ->modalDescription('Sistem memeriksa seluruh plotting lebih dulu. Skema, assignment, nilai, dan PDF lama tetap dipertahankan.')
            ->modalSubmitActionLabel('Masukkan Semua Mapel')
            ->form(fn (): array => static::syncPeriodForm(
                Subject::query()->where('is_active', true)->orderBy('sort_order')->pluck('id')->map(fn ($id): int => (int) $id)->all(),
            ))
            ->action(function (array $data, SyncOpenPeriodSubjectsAction $sync): void {
                $period = null;
                try {
                    $period = AssessmentPeriod::query()->findOrFail((int) $data['period_id']);
                    $actor = auth()->user();
                    abort_unless($actor instanceof User, 403);
                    $subjectIds = Subject::query()->where('is_active', true)->pluck('id')->map(fn ($id): int => (int) $id)->all();
                    $summary = $sync->execute($actor, $period, $subjectIds, static::sourceSchemeId($data));
                    static::sendSyncSuccessNotification($period, $summary);
                } catch (Throwable $exception) {
                    report($exception);
                    AssessmentActionFailureNotification::send($exception, 'memasukkan semua mapel aktif ke periode', $period);
                }
            });
    }

    /** @param array<int, int|string> $subjectIds @return array<int, mixed> */
    private static function syncPeriodForm(array $subjectIds): array
    {
        return [
            Section::make('Tujuan Sinkronisasi')
                ->description('Form tetap satu kolom di HP. Pilih periode untuk melihat hitungan sebelum konfirmasi.')
                ->columns(1)
                ->schema([
                    Forms\Components\Select::make('period_id')
                        ->label('Periode Terbuka')
                        ->options(fn (): array => AssessmentPeriod::query()
                            ->where('status', AssessmentPeriodStatus::OPEN->value)
                            ->latest('id')
                            ->get()
                            ->mapWithKeys(fn (AssessmentPeriod $period): array => [
                                $period->getKey() => $period->name.' · '.$period->type->label(),
                            ])->all())
                        ->searchable()
                        ->preload()
                        ->native(false)
                        ->live()
                        ->required(),
                    Forms\Components\Select::make('source_scheme_id')
                        ->label('Skema Sumber Default')
                        ->helperText('Boleh dikosongkan bila periode sudah memiliki skema default atau hanya mempunyai satu skema aktif.')
                        ->options(fn (Get $get): array => static::schemeSourceOptions((int) ($get('period_id') ?? 0)))
                        ->searchable()
                        ->preload()
                        ->native(false)
                        ->live()
                        ->placeholder('Pilih otomatis jika memungkinkan'),
                    Forms\Components\Placeholder::make('sync_preview')
                        ->label('Pratinjau Dampak')
                        ->content(fn (Get $get): HtmlString => static::syncPreviewHtml(
                            $subjectIds,
                            (int) ($get('period_id') ?? 0),
                            filled($get('source_scheme_id')) ? (int) $get('source_scheme_id') : null,
                        )),
                ]),
        ];
    }

    /** @return array<int, string> */
    private static function schemeSourceOptions(int $periodId): array
    {
        if ($periodId <= 0) {
            return [];
        }

        return AssessmentScheme::query()
            ->with(['subject:id,name', 'sourceRombel:id,nama'])
            ->where('assessment_period_id', $periodId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->mapWithKeys(function (AssessmentScheme $scheme): array {
                $scope = $scheme->subject?->name ?? 'Semua mapel';
                $class = $scheme->sourceRombel?->nama ?? 'semua kelas';

                return [$scheme->getKey() => $scheme->name.' · '.$scope.' · '.$class];
            })
            ->all();
    }

    /** @param array<int, int|string> $subjectIds */
    private static function syncPreviewHtml(array $subjectIds, int $periodId, ?int $sourceSchemeId): HtmlString
    {
        if ($periodId <= 0) {
            return new HtmlString('<p>Pilih periode untuk menghitung mapel, kelas, assignment, dan skema.</p>');
        }

        try {
            $period = AssessmentPeriod::query()->findOrFail($periodId);
            $summary = app(SyncOpenPeriodSubjectsAction::class)->preview($period, $subjectIds, $sourceSchemeId);
            $scheme = $summary['default_scheme_created']
                ? 'Skema default akan dibuat dari skema sumber.'
                : 'Skema default periode sudah tersedia.';

            return new HtmlString(
                '<p><strong>'.e($summary['period_name']).'</strong></p>'
                .'<p>'.e("{$summary['subject_count']} mapel · {$summary['class_count']} kelas · {$summary['plotting_count']} plotting").'</p>'
                .'<p>'.e("{$summary['created']} assignment dibuat · {$summary['updated']} diperbarui · {$summary['unchanged']} tetap").'</p>'
                .((int) ($summary['protected'] ?? 0) > 0 ? '<p>'.e("{$summary['protected']} assignment lama diproteksi karena mapelnya memiliki data noneditable; metadata lama tidak diubah.").'</p>' : '')
                .'<p>'.e($scheme).' Tidak ada assignment lama yang dihapus.</p>',
            );
        } catch (Throwable $exception) {
            $message = $exception instanceof ValidationException
                ? collect($exception->errors())->flatten()->first()
                : $exception->getMessage();

            return new HtmlString('<p><strong>Kendala:</strong> '.e((string) $message).'</p>');
        }
    }

    /** @param array<string, mixed> $data */
    private static function sourceSchemeId(array $data): ?int
    {
        return filled($data['source_scheme_id'] ?? null) ? (int) $data['source_scheme_id'] : null;
    }

    /** @param array<string, mixed> $summary */
    private static function sendSyncSuccessNotification(AssessmentPeriod $period, array $summary): void
    {
        $type = $period->type instanceof AssessmentType
            ? $period->type
            : AssessmentType::from((string) $period->type);
        $pages = AssessmentPageMap::for($type);
        $inputPage = $pages['input'];
        $statusPage = $pages['status'];
        $reportsPage = $pages['reports'];
        $actions = [];
        foreach ([
            ['name' => 'input', 'label' => 'Buka Input Nilai', 'page' => $inputPage],
            ['name' => 'status', 'label' => 'Status Pengumpulan', 'page' => $statusPage],
            ['name' => 'reports', 'label' => 'Proses Rapor', 'page' => $reportsPage],
        ] as $link) {
            if ($link['page']::canAccess()) {
                $actions[] = Action::make($link['name'])
                    ->label($link['label'])
                    ->url($link['page']::getUrl(['period' => $period->getKey()]));
            }
        }

        Notification::make()
            ->title('Mapel berhasil dimasukkan ke periode')
            ->body("{$summary['created']} dibuat, {$summary['updated']} diperbarui, {$summary['unchanged']} tetap".((int) ($summary['protected'] ?? 0) > 0 ? ", termasuk {$summary['protected']} assignment lama yang diproteksi" : '').'. Nilai dan rapor lama tidak berubah; mapel baru masuk rapor resmi setelah nilai lengkap dan periode dikunci kembali.')
            ->success()
            ->actions($actions)
            ->send();
    }

    private static function plottingSummary(Subject $subject): string
    {
        $rows = $subject->relationLoaded('teachingAssignments')
            ? $subject->teachingAssignments
            : $subject->teachingAssignments()
                ->with(['teacher:id,nama', 'rombel:id,nama', 'category:id,name'])
                ->where('is_active', true)
                ->orderBy('assessment_semester_id', 'desc')
                ->orderBy('rombel_name_snapshot')
                ->get();

        if ($rows->isEmpty()) {
            return 'Belum diplot';
        }

        return $rows
            ->groupBy(fn (TeachingAssignment $row): string => ($row->teacher?->nama ?? $row->teacher_name_snapshot).'|'.($row->category?->name ?? 'Tanpa kategori'))
            ->map(function (Collection $assignments, string $key): string {
                [$teacher, $category] = explode('|', $key, 2);
                $classes = $assignments->map(fn (TeachingAssignment $row): string => $row->rombel?->nama ?? $row->rombel_name_snapshot)
                    ->unique()
                    ->implode(', ');

                return "{$teacher}: {$classes} ({$category})";
            })
            ->implode("\n");
    }

    /** @return array<int, string> */
    private static function semesterOptions(): array
    {
        return Semester::query()
            ->with('academicYear:id,name')
            ->latest('is_active')
            ->latest('id')
            ->get()
            ->mapWithKeys(fn (Semester $semester): array => [
                $semester->getKey() => trim(($semester->academicYear?->name ?? '').' · '.$semester->name)
                    .($semester->is_active ? ' · Aktif' : ''),
            ])
            ->all();
    }

    /** @return array<int, string> */
    private static function teacherOptions(): array
    {
        return GuruTendik::query()
            ->with('userAccount.roles.permissions', 'userAccount.permissions')
            ->whereHas('userAccount')
            ->orderBy('nama')
            ->get()
            ->mapWithKeys(function (GuruTendik $teacher): array {
                $ready = static::teacherAccountReady($teacher);
                static::$teacherReadinessCache[(int) $teacher->getKey()] = $ready;

                return [$teacher->getKey() => $teacher->nama.($ready ? '' : ' · Belum siap input nilai')];
            })
            ->all();
    }

    /** @return array<string, mixed> */
    private static function plottingFormData(Subject $subject): array
    {
        $semesterId = (int) (Semester::query()->where('is_active', true)->latest('id')->value('id')
            ?? Semester::query()->latest('id')->value('id'));
        $rows = $subject->teachingAssignments()
            ->where('assessment_semester_id', $semesterId)
            ->where('is_active', true)
            ->orderBy('rombel_name_snapshot')
            ->get()
            ->groupBy(fn (TeachingAssignment $row): string => $row->teacher_id.'|'.$row->assessment_subject_category_id)
            ->map(function (Collection $assignments): array {
                $first = $assignments->first();

                return [
                    'teacher_id' => $first->teacher_id,
                    'category_id' => $first->assessment_subject_category_id,
                    'rombel_ids' => $assignments->pluck('rombel_id')->map(fn ($id): int => (int) $id)->values()->all(),
                ];
            })
            ->values()
            ->all();

        return ['semester_id' => $semesterId ?: null, 'assignments' => $rows];
    }

    /** @return array<int, string> */
    private static function openPeriodOptions(Subject $subject): array
    {
        $semesterIds = $subject->teachingAssignments()
            ->where('is_active', true)
            ->select('assessment_semester_id')
            ->distinct()
            ->pluck('assessment_semester_id');

        return AssessmentPeriod::query()
            ->whereIn('assessment_semester_id', $semesterIds)
            ->where('status', AssessmentPeriodStatus::OPEN->value)
            ->latest('id')
            ->get()
            ->mapWithKeys(fn (AssessmentPeriod $period): array => [
                $period->getKey() => $period->name.' · '.$period->type->label(),
            ])
            ->all();
    }

    /** @param array<string, mixed> $data @return array{active:int,disabled:int} */
    private static function savePlotting(Subject $subject, array $data): array
    {
        abort_unless(static::canManageAssessment(), 403);
        $actor = auth()->user();
        abort_unless($actor instanceof User, 403);

        $semester = Semester::query()->findOrFail((int) ($data['semester_id'] ?? 0));
        $desired = [];

        foreach ((array) ($data['assignments'] ?? []) as $index => $row) {
            $teacher = GuruTendik::query()->with('userAccount.roles.permissions', 'userAccount.permissions')->find((int) ($row['teacher_id'] ?? 0));
            $category = SubjectCategory::query()->where('is_active', true)->find((int) ($row['category_id'] ?? 0));
            if (! $teacher || ! $category) {
                throw ValidationException::withMessages([
                    "assignments.{$index}" => 'Guru atau kategori tidak valid. Muat ulang pilihan lalu coba kembali.',
                ]);
            }
            if (! static::teacherAccountReady($teacher)) {
                throw ValidationException::withMessages([
                    "assignments.{$index}.teacher_id" => "Akun {$teacher->nama} belum tertaut atau belum memiliki akses Input dan Kirim Nilai. Atur akses akun terlebih dahulu.",
                ]);
            }

            foreach (array_map('intval', (array) ($row['rombel_ids'] ?? [])) as $rombelId) {
                if (isset($desired[$rombelId])) {
                    throw ValidationException::withMessages([
                        "assignments.{$index}.rombel_ids" => 'Satu kelas tidak boleh dipilih untuk dua guru pada mapel dan semester yang sama.',
                    ]);
                }
                $rombel = Rombel::query()->where('is_active', true)->find($rombelId);
                if (! $rombel) {
                    throw ValidationException::withMessages([
                        "assignments.{$index}.rombel_ids" => 'Salah satu kelas sudah tidak aktif. Muat ulang pilihan.',
                    ]);
                }
                $desired[$rombelId] = compact('teacher', 'category', 'rombel');
            }
        }

        return DB::transaction(function () use ($actor, $subject, $semester, $desired): array {
            $existing = TeachingAssignment::query()
                ->where('assessment_semester_id', $semester->getKey())
                ->where('assessment_subject_id', $subject->getKey())
                ->lockForUpdate()
                ->get();
            $keptIds = [];
            $disabled = 0;

            foreach ($desired as $rombelId => $row) {
                /** @var GuruTendik $teacher */
                $teacher = $row['teacher'];
                /** @var SubjectCategory $category */
                $category = $row['category'];
                /** @var Rombel $rombel */
                $rombel = $row['rombel'];
                $assignment = $existing
                    ->where('rombel_id', $rombelId)
                    ->firstWhere('teacher_id', $teacher->getKey());

                $values = [
                    'assessment_semester_id' => $semester->getKey(),
                    'assessment_subject_id' => $subject->getKey(),
                    'assessment_subject_category_id' => $category->getKey(),
                    'teacher_id' => $teacher->getKey(),
                    'rombel_id' => $rombel->getKey(),
                    'teacher_name_snapshot' => $teacher->nama,
                    'subject_name_snapshot' => $subject->name,
                    'rombel_name_snapshot' => $rombel->nama,
                    'is_active' => true,
                ];

                if (! $assignment) {
                    $assignment = TeachingAssignment::query()->create($values);
                    app(AssessmentAuditLogger::class)->record(
                        actor: $actor,
                        event: 'teaching_assignment.created_from_subject',
                        subject: $assignment,
                        newValues: $values,
                    );
                } else {
                    $old = $assignment->only(array_keys($values));
                    $assignment->forceFill($values)->save();
                    if ($old !== $assignment->only(array_keys($values))) {
                        app(AssessmentAuditLogger::class)->record(
                            actor: $actor,
                            event: 'teaching_assignment.updated_from_subject',
                            subject: $assignment,
                            oldValues: $old,
                            newValues: $assignment->only(array_keys($values)),
                        );
                    }
                }
                $keptIds[] = (int) $assignment->getKey();
            }

            foreach ($existing->whereNotIn('id', $keptIds)->where('is_active', true) as $assignment) {
                $assignment->forceFill(['is_active' => false])->save();
                app(AssessmentAuditLogger::class)->record(
                    actor: $actor,
                    event: 'teaching_assignment.deactivated_from_subject',
                    subject: $assignment,
                    oldValues: ['is_active' => true],
                    newValues: ['is_active' => false],
                );
                $disabled++;
            }

            return ['active' => count($desired), 'disabled' => $disabled];
        }, 3);
    }

    private static function teacherAccountReady(GuruTendik $teacher): bool
    {
        $account = $teacher->userAccount;

        return $account instanceof User
            && ($account->hasFullAdminAccess()
                || ($account->can('penilaian.input') && $account->can('penilaian.submit')));
    }

    private static function teacherIdReady(int $teacherId): bool
    {
        if (array_key_exists($teacherId, static::$teacherReadinessCache)) {
            return static::$teacherReadinessCache[$teacherId];
        }

        $teacher = GuruTendik::query()
            ->with('userAccount.roles.permissions', 'userAccount.permissions')
            ->find($teacherId);

        return static::$teacherReadinessCache[$teacherId] = $teacher instanceof GuruTendik
            && static::teacherAccountReady($teacher);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAssessmentSubjects::route('/'),
            'create' => Pages\CreateAssessmentSubject::route('/create'),
            'edit' => Pages\EditAssessmentSubject::route('/{record}/edit'),
        ];
    }
}
