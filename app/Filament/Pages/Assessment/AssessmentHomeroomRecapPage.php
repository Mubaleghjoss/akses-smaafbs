<?php

namespace App\Filament\Pages\Assessment;

use App\Enums\Assessment\AssessmentPeriodStatus;
use App\Enums\Assessment\AssessmentType;
use App\Filament\Pages\Assessment\Concerns\HasAssessmentTypeNavigation;
use App\Models\Assessment\AssessmentPeriod;
use App\Models\Assessment\AssessmentPeriodHomeroom;
use App\Models\Assessment\AuditLog;
use App\Models\Assessment\HomeroomReport;
use App\Models\Assessment\ReportSnapshot;
use App\Models\User;
use App\Support\Assessment\AssessmentActionFailureNotification;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Url;
use Throwable;

abstract class AssessmentHomeroomRecapPage extends AssessmentPage
{
    use HasAssessmentTypeNavigation;

    /**
     * @var array<string, array{header: string, bulk_label: string, input: string, max: int}>
     */
    private const RECAP_FIELD_DEFINITIONS = [
        'sick_days' => [
            'header' => 'Sakit',
            'bulk_label' => 'Jumlah Hari Sakit',
            'input' => 'number',
            'max' => 366,
        ],
        'permission_days' => [
            'header' => 'Izin',
            'bulk_label' => 'Jumlah Hari Izin',
            'input' => 'number',
            'max' => 366,
        ],
        'absent_days' => [
            'header' => 'Alpa',
            'bulk_label' => 'Jumlah Hari Alpa',
            'input' => 'number',
            'max' => 366,
        ],
        'spiritual_predicate' => [
            'header' => 'Predikat Spiritual',
            'bulk_label' => 'Predikat Sikap Spiritual',
            'input' => 'predicate',
            'max' => 30,
        ],
        'spiritual_description' => [
            'header' => 'Deskripsi Spiritual',
            'bulk_label' => 'Deskripsi Sikap Spiritual',
            'input' => 'text',
            'max' => 2000,
        ],
        'social_predicate' => [
            'header' => 'Predikat Sosial',
            'bulk_label' => 'Predikat Sikap Sosial',
            'input' => 'predicate',
            'max' => 30,
        ],
        'social_description' => [
            'header' => 'Deskripsi Sosial',
            'bulk_label' => 'Deskripsi Sikap Sosial',
            'input' => 'text',
            'max' => 2000,
        ],
        'extracurricular_items' => [
            'header' => 'Ekstrakurikuler',
            'bulk_label' => 'Ekstrakurikuler',
            'input' => 'items',
            'max' => 4000,
        ],
        'achievement_items' => [
            'header' => 'Prestasi',
            'bulk_label' => 'Prestasi',
            'input' => 'items',
            'max' => 4000,
        ],
        'homeroom_note' => [
            'header' => 'Catatan Wali',
            'bulk_label' => 'Catatan Wali Kelas',
            'input' => 'text',
            'max' => 2000,
        ],
    ];

    /** @var array{header: string, bulk_label: string, input: string, max: int} */
    private const PROMOTION_STATUS_FIELD_DEFINITION = [
        'header' => 'Status Semester',
        'bulk_label' => 'Status Semester',
        'input' => 'text',
        'max' => 50,
    ];

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-user-group';

    protected static string $assessmentPermission = 'penilaian.homeroom';

    protected string $view = 'filament.pages.assessment.homeroom-recap';

    protected static AssessmentType $assessmentType;

    #[Url(as: 'period')]
    public ?int $periodId = null;

    #[Url(as: 'class')]
    public ?int $homeroomId = null;

    /** @var array<int, array<string, mixed>> */
    public array $reportRows = [];

    public ?array $homeroomMeta = null;

    /** @var array<int, int|string> */
    public array $selectedStudentIds = [];

    public string $bulkField = 'homeroom_note';

    public string $bulkValue = '';

    public bool $bulkFillEmptyOnly = true;

    /** @var array{name:string,description:string} */
    public array $bulkStructuredItem = [
        'name' => '',
        'description' => '',
    ];

    public string $bulkStructuredMode = 'append';

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return config('assessment.enabled')
            && Schema::hasTable('assessment_periods')
            && $user instanceof User
            && $user->canAccessNavigationItem(static::navigationAccessClass())
            && (
                $user->hasFullAdminAccess()
                || (
                    $user->canViewModule('penilaian')
                    && (
                        $user->can('penilaian.homeroom')
                        || $user->can('penilaian.verify')
                        || static::currentUserOwnsAssessmentHomeroom()
                        || $user->hasRole('kepala_sekolah')
                    )
                )
            );
    }

    public function mount(): void
    {
        $periodIds = array_map('intval', array_keys($this->getPeriodOptions()));
        if (! $this->periodId || ! in_array($this->periodId, $periodIds, true)) {
            $this->periodId = $periodIds[0] ?? null;
        }

        $this->selectDefaultHomeroom();
        $this->loadReports();
    }

    public function getTitle(): string|Htmlable
    {
        return 'Rekap Wali Kelas · '.static::$assessmentType->label();
    }

    public function getPeriodOptions(): array
    {
        return AssessmentPeriod::query()
            ->where('type', static::$assessmentType->value)
            ->whereHas('homerooms', fn (Builder $query): Builder => $this->scopeHomerooms($query))
            ->latest('id')
            ->pluck('name', 'id')
            ->all();
    }

    public function getHomeroomOptions(): array
    {
        if (! $this->periodId) {
            return [];
        }

        return $this->homeroomQuery()
            ->orderBy('rombel_name_snapshot')
            ->pluck('rombel_name_snapshot', 'id')
            ->all();
    }

    public function updatedPeriodId(): void
    {
        $this->selectDefaultHomeroom();
        $this->loadReports();
    }

    public function updatedHomeroomId(): void
    {
        $this->loadReports();
    }

    public function updatedBulkField(): void
    {
        $this->bulkValue = '';
        $this->bulkStructuredItem = ['name' => '', 'description' => ''];
        $this->bulkStructuredMode = 'append';
    }

    public function loadReports(): void
    {
        $this->reportRows = [];
        $this->homeroomMeta = null;
        $this->selectedStudentIds = [];

        $homeroom = $this->homeroomId ? $this->homeroomQuery()->with('period')->find($this->homeroomId) : null;
        if (! $homeroom) {
            return;
        }

        $reports = HomeroomReport::query()
            ->where('assessment_period_id', $homeroom->assessment_period_id)
            ->get()
            ->keyBy('assessment_period_student_id');
        $students = $homeroom->period->students()
            ->where('assessment_period_rombel_id', $homeroom->assessment_period_rombel_id)
            ->where('is_active', true)
            ->orderBy('student_name_snapshot')
            ->get();

        foreach ($students as $student) {
            $report = $reports->get($student->getKey());
            $this->reportRows[(int) $student->getKey()] = [
                'student_name' => (string) $student->student_name_snapshot,
                'nis' => (string) ($student->nis_snapshot ?: $student->nisn_snapshot ?: '-'),
                'sick_days' => (int) ($report?->sick_days ?? 0),
                'permission_days' => (int) ($report?->permission_days ?? 0),
                'absent_days' => (int) ($report?->absent_days ?? 0),
                'spiritual_predicate' => $report?->spiritual_predicate,
                'spiritual_description' => $report?->spiritual_description,
                'social_predicate' => $report?->social_predicate,
                'social_description' => $report?->social_description,
                'extracurricular_items' => $this->normalizeStructuredItems($report?->extracurricular_data),
                'achievement_items' => $this->normalizeStructuredItems($report?->achievement_data),
                'homeroom_note' => $report?->homeroom_note,
                'promotion_status' => $report?->promotion_status,
            ];
        }

        $status = $homeroom->period->status instanceof AssessmentPeriodStatus
            ? $homeroom->period->status
            : AssessmentPeriodStatus::from((string) $homeroom->period->status);
        $this->homeroomMeta = [
            'rombel' => (string) $homeroom->rombel_name_snapshot,
            'teacher' => (string) $homeroom->teacher_name_snapshot,
            'status' => $status->value,
            'status_label' => $status->label(),
            'collect_promotion_status' => $this->collectPromotionStatus($homeroom->period),
            'editable' => $this->canEditHomeroom($homeroom)
                && in_array($status, [
                    AssessmentPeriodStatus::OPEN,
                    AssessmentPeriodStatus::ENTRY_CLOSED,
                    AssessmentPeriodStatus::VERIFICATION,
                ], true),
        ];
    }

    /**
     * @return array<string, array{header: string, bulk_label: string, input: string, max: int}>
     */
    public function getRecapFieldDefinitions(): array
    {
        return self::RECAP_FIELD_DEFINITIONS;
    }

    /**
     * @return array<string, string>
     */
    public function getBulkFieldOptions(): array
    {
        return collect($this->getBulkFieldDefinitions())
            ->mapWithKeys(fn (array $definition, string $field): array => [
                $field => $definition['bulk_label'],
            ])
            ->all();
    }

    /**
     * @return array{header: string, bulk_label: string, input: string, max: int}|null
     */
    public function getBulkFieldDefinition(?string $field = null): ?array
    {
        return $this->getBulkFieldDefinitions()[$field ?? $this->bulkField] ?? null;
    }

    /**
     * @return array<string, array{header: string, bulk_label: string, input: string, max: int}>
     */
    protected function getBulkFieldDefinitions(): array
    {
        $definitions = $this->getRecapFieldDefinitions();

        if (data_get($this->homeroomMeta, 'collect_promotion_status')) {
            $definitions['promotion_status'] = self::PROMOTION_STATUS_FIELD_DEFINITION;
        }

        return $definitions;
    }

    /**
     * @return array<string, string>
     */
    public function getAttitudePredicateOptions(): array
    {
        return [
            'Sangat Baik' => 'Sangat Baik',
            'Baik' => 'Baik',
            'Cukup' => 'Cukup',
            'Perlu Bimbingan' => 'Perlu Bimbingan',
        ];
    }

    public function isStructuredBulkField(): bool
    {
        return data_get($this->getBulkFieldDefinition(), 'input') === 'items';
    }

    public function addStructuredItem(int $studentId, string $field): void
    {
        if (! data_get($this->homeroomMeta, 'editable')
            || ! $this->isStructuredField($field)
            || ! array_key_exists($studentId, $this->reportRows)) {
            return;
        }

        $this->reportRows[$studentId][$field] = [
            ...$this->normalizeStructuredItems($this->reportRows[$studentId][$field] ?? []),
            ['name' => '', 'description' => ''],
        ];
    }

    public function removeStructuredItem(int $studentId, string $field, int $index): void
    {
        if (! data_get($this->homeroomMeta, 'editable')
            || ! $this->isStructuredField($field)
            || ! array_key_exists($studentId, $this->reportRows)) {
            return;
        }

        $items = $this->normalizeStructuredItems($this->reportRows[$studentId][$field] ?? []);
        unset($items[$index]);
        $this->reportRows[$studentId][$field] = array_values($items);
        $this->resetErrorBag("rows.{$studentId}.{$field}.{$index}");
    }

    public function selectAllStudents(): void
    {
        $this->selectedStudentIds = array_map('intval', array_keys($this->reportRows));
    }

    public function clearStudentSelection(): void
    {
        $this->selectedStudentIds = [];
    }

    public function applyBulkValue(): void
    {
        if (! data_get($this->homeroomMeta, 'editable')) {
            Notification::make()
                ->title('Rekap kelas ini hanya dapat ditinjau')
                ->warning()
                ->send();

            return;
        }

        $studentIds = collect($this->selectedStudentIds)
            ->map(fn (mixed $id): int => (int) $id)
            ->filter(fn (int $id): bool => array_key_exists($id, $this->reportRows))
            ->unique()
            ->values();

        if ($studentIds->isEmpty()) {
            Notification::make()
                ->title('Pilih siswa terlebih dahulu')
                ->body('Centang siswa yang akan menerima isian massal.')
                ->warning()
                ->send();

            return;
        }

        $fieldDefinition = $this->getBulkFieldDefinition();
        if ($fieldDefinition === null) {
            Notification::make()->title('Kolom bulk tidak valid')->danger()->send();

            return;
        }

        if ($fieldDefinition['input'] === 'items') {
            $this->applyBulkStructuredItem($studentIds->all(), $fieldDefinition);

            return;
        }

        $value = trim($this->bulkValue);
        if ($value === '') {
            Notification::make()
                ->title('Nilai bulk belum diisi')
                ->body('Masukkan angka atau teks yang akan diterapkan.')
                ->warning()
                ->send();

            return;
        }

        if ($fieldDefinition['input'] === 'number') {
            if (! ctype_digit($value) || (int) $value > $fieldDefinition['max']) {
                Notification::make()
                    ->title('Jumlah hari tidak valid')
                    ->body("Masukkan angka bulat antara 0 sampai {$fieldDefinition['max']}.")
                    ->danger()
                    ->send();

                return;
            }

            $value = (int) $value;
        } elseif ($fieldDefinition['input'] === 'predicate') {
            if (! array_key_exists($value, $this->getAttitudePredicateOptions())) {
                Notification::make()
                    ->title('Predikat sikap tidak valid')
                    ->body('Pilih predikat yang tersedia pada form.')
                    ->danger()
                    ->send();

                return;
            }
        } else {
            $maximum = $fieldDefinition['max'];
            if (mb_strlen($value) > $maximum) {
                Notification::make()
                    ->title('Teks terlalu panjang')
                    ->body("Kolom {$fieldDefinition['bulk_label']} maksimal {$maximum} karakter.")
                    ->danger()
                    ->send();

                return;
            }
        }

        $changed = 0;
        foreach ($studentIds as $studentId) {
            $current = data_get($this->reportRows, "{$studentId}.{$this->bulkField}");
            $isEmpty = $fieldDefinition['input'] === 'number'
                ? (int) $current === 0
                : trim((string) $current) === '';

            if ($this->bulkFillEmptyOnly && ! $isEmpty) {
                continue;
            }

            $this->reportRows[$studentId][$this->bulkField] = $value;
            $changed++;
        }

        Notification::make()
            ->title("Diterapkan ke {$changed} siswa")
            ->body('Perubahan masih berada di formulir. Tekan Simpan Rekap Wali Kelas agar tersimpan ke server.')
            ->success()
            ->duration(12000)
            ->send();
    }

    /**
     * @param  array<int, int>  $studentIds
     * @param  array{header:string,bulk_label:string,input:string,max:int}  $fieldDefinition
     */
    protected function applyBulkStructuredItem(array $studentIds, array $fieldDefinition): void
    {
        $name = trim((string) ($this->bulkStructuredItem['name'] ?? ''));
        $description = trim((string) ($this->bulkStructuredItem['description'] ?? ''));

        if ($name === '') {
            Notification::make()
                ->title('Nama poin belum diisi')
                ->body("Isi nama {$fieldDefinition['bulk_label']} sebelum menerapkan ke siswa.")
                ->danger()
                ->persistent()
                ->send();

            return;
        }

        if (mb_strlen($name) > 255 || mb_strlen($description) > 2000) {
            Notification::make()
                ->title('Poin terlalu panjang')
                ->body('Nama maksimal 255 karakter dan keterangan maksimal 2.000 karakter.')
                ->danger()
                ->persistent()
                ->send();

            return;
        }

        if (! in_array($this->bulkStructuredMode, ['append', 'replace'], true)) {
            $this->bulkStructuredMode = 'append';
        }

        $item = ['name' => $name, 'description' => $description];
        $updates = [];

        foreach ($studentIds as $studentId) {
            $current = $this->normalizeStructuredItems(
                $this->reportRows[$studentId][$this->bulkField] ?? [],
                true,
            );

            if ($this->bulkStructuredMode === 'replace') {
                $next = [$item];
            } else {
                $duplicate = collect($current)->contains(
                    fn (array $existing): bool => $existing['name'] === $item['name']
                        && $existing['description'] === $item['description'],
                );
                if ($duplicate) {
                    continue;
                }

                $next = [...$current, $item];
            }

            if ($this->structuredItemsLength($next) > $fieldDefinition['max']) {
                Notification::make()
                    ->title('Daftar terlalu panjang')
                    ->body("Total {$fieldDefinition['bulk_label']} maksimal {$fieldDefinition['max']} karakter per siswa.")
                    ->danger()
                    ->persistent()
                    ->send();

                return;
            }

            $updates[$studentId] = $next;
        }

        foreach ($updates as $studentId => $items) {
            $this->reportRows[$studentId][$this->bulkField] = $items;
        }

        $changed = count($updates);
        $modeLabel = $this->bulkStructuredMode === 'replace' ? 'mengganti daftar' : 'menambahkan poin';
        $this->bulkStructuredItem = ['name' => '', 'description' => ''];
        $this->bulkStructuredMode = 'append';

        Notification::make()
            ->title("Diterapkan ke {$changed} siswa")
            ->body("Form diperbarui dengan {$modeLabel}. Tekan Simpan Rekap Wali Kelas agar tersimpan ke server.")
            ->success()
            ->duration(12000)
            ->send();
    }

    public function saveReports(): void
    {
        $homeroom = $this->homeroomId ? $this->homeroomQuery()->with('period')->findOrFail($this->homeroomId) : null;

        if (! $homeroom || ! $this->canEditHomeroom($homeroom)) {
            Notification::make()->title('Rekap tidak dapat diubah pada status periode ini')->warning()->send();

            return;
        }

        try {
            $this->resetErrorBag();
            $validator = Validator::make(
                ['rows' => $this->rowsForValidation()],
                $this->getReportValidationRules(),
                [
                    'rows.*.sick_days.max' => 'Jumlah hari sakit maksimal 366.',
                    'rows.*.permission_days.max' => 'Jumlah hari izin maksimal 366.',
                    'rows.*.absent_days.max' => 'Jumlah hari alpa maksimal 366.',
                    'rows.*.extracurricular_items.*.name.required' => 'Nama ekstrakurikuler wajib diisi.',
                    'rows.*.achievement_items.*.name.required' => 'Jenis prestasi wajib diisi.',
                    'rows.*.extracurricular_items.*.name.max' => 'Nama ekstrakurikuler maksimal 255 karakter.',
                    'rows.*.achievement_items.*.name.max' => 'Jenis prestasi maksimal 255 karakter.',
                    'rows.*.extracurricular_items.*.description.max' => 'Keterangan ekstrakurikuler maksimal 2.000 karakter.',
                    'rows.*.achievement_items.*.description.max' => 'Keterangan prestasi maksimal 2.000 karakter.',
                ],
            );
            $validator->after(function ($validator): void {
                foreach ($this->rowsForValidation() as $studentId => $row) {
                    foreach (['extracurricular_items', 'achievement_items'] as $field) {
                        $maximum = self::RECAP_FIELD_DEFINITIONS[$field]['max'];
                        if ($this->structuredItemsLength($row[$field] ?? []) > $maximum) {
                            $validator->errors()->add(
                                "rows.{$studentId}.{$field}",
                                "Total teks maksimal {$maximum} karakter.",
                            );
                        }
                    }
                }
            });

            if ($validator->fails()) {
                $this->setErrorBag($validator->errors());
                throw new ValidationException($validator);
            }

            $validatedRows = $validator->validated()['rows'];

            DB::transaction(function () use ($homeroom, $validatedRows): void {
                $period = AssessmentPeriod::query()
                    ->lockForUpdate()
                    ->findOrFail($homeroom->assessment_period_id);
                $freshHomeroom = AssessmentPeriodHomeroom::query()
                    ->whereKey($homeroom->getKey())
                    ->where('assessment_period_id', $period->getKey())
                    ->with('period')
                    ->lockForUpdate()
                    ->firstOrFail();
                $status = $period->status instanceof AssessmentPeriodStatus
                    ? $period->status
                    : AssessmentPeriodStatus::from((string) $period->status);
                $collectPromotionStatus = $this->collectPromotionStatus($period);

                if (! $this->canEditHomeroom($freshHomeroom) || ! in_array($status, [
                    AssessmentPeriodStatus::OPEN,
                    AssessmentPeriodStatus::ENTRY_CLOSED,
                    AssessmentPeriodStatus::VERIFICATION,
                ], true)) {
                    throw ValidationException::withMessages([
                        'period' => 'Rekap wali kelas tidak dapat diubah setelah periode dikunci atau diterbitkan.',
                    ]);
                }

                Gate::forUser(auth()->user())->authorize('view', $freshHomeroom);

                foreach ($validatedRows as $studentId => $row) {
                    $studentExists = $period->students()
                        ->whereKey($studentId)
                        ->where('assessment_period_rombel_id', $freshHomeroom->assessment_period_rombel_id)
                        ->exists();

                    abort_unless($studentExists, 422, 'Salah satu siswa tidak termasuk dalam snapshot wali kelas.');

                    $report = HomeroomReport::query()->firstOrNew([
                        'assessment_period_id' => $freshHomeroom->assessment_period_id,
                        'assessment_period_student_id' => $studentId,
                    ]);

                    if ($report->exists) {
                        Gate::forUser(auth()->user())->authorize(
                            'update',
                            $report->loadMissing(['period', 'student.periodRombel']),
                        );
                    } else {
                        Gate::forUser(auth()->user())->authorize(
                            'create',
                            [HomeroomReport::class, $freshHomeroom],
                        );
                    }

                    $report->fill([
                        'sick_days' => max(0, (int) ($row['sick_days'] ?? 0)),
                        'permission_days' => max(0, (int) ($row['permission_days'] ?? 0)),
                        'absent_days' => max(0, (int) ($row['absent_days'] ?? 0)),
                        'spiritual_predicate' => filled($row['spiritual_predicate'] ?? null)
                            ? trim((string) $row['spiritual_predicate'])
                            : null,
                        'spiritual_description' => filled($row['spiritual_description'] ?? null)
                            ? trim((string) $row['spiritual_description'])
                            : null,
                        'social_predicate' => filled($row['social_predicate'] ?? null)
                            ? trim((string) $row['social_predicate'])
                            : null,
                        'social_description' => filled($row['social_description'] ?? null)
                            ? trim((string) $row['social_description'])
                            : null,
                        'extracurricular_data' => $this->normalizeStructuredItems($row['extracurricular_items'] ?? [], true),
                        'achievement_data' => $this->normalizeStructuredItems($row['achievement_items'] ?? [], true),
                        'homeroom_note' => filled($row['homeroom_note'] ?? null) ? trim($row['homeroom_note']) : null,
                        'promotion_status' => $collectPromotionStatus && filled($row['promotion_status'] ?? null)
                            ? trim($row['promotion_status'])
                            : null,
                        'updated_by' => auth()->id(),
                    ])->save();
                }

                AuditLog::query()->create([
                    'assessment_period_id' => $freshHomeroom->assessment_period_id,
                    'actor_id' => auth()->id(),
                    'event' => 'homeroom.batch_saved',
                    'subject_type' => AssessmentPeriodHomeroom::class,
                    'subject_id' => $freshHomeroom->getKey(),
                    'new_values' => ['student_count' => count($validatedRows)],
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                ]);
            });

            $hasFrozenReports = ReportSnapshot::query()
                ->where('assessment_period_id', $homeroom->assessment_period_id)
                ->exists();
            $notification = Notification::make()->title('Rekap wali kelas tersimpan')->success();
            if ($hasFrozenReports) {
                $notification
                    ->body('Snapshot rapor lama tetap utuh. Gunakan Mulai Ulang dengan Revisi Baru agar perubahan ini masuk ke PDF berikutnya.')
                    ->duration(15000);
            }
            $notification->send();
            $this->loadReports();
        } catch (Throwable $exception) {
            if (! $exception instanceof ValidationException) {
                report($exception);
            }
            AssessmentActionFailureNotification::send(
                $exception,
                'Simpan Rekap Wali Kelas',
                $homeroom?->period,
            );
        }
    }

    protected function selectDefaultHomeroom(): void
    {
        $ids = array_map('intval', array_keys($this->getHomeroomOptions()));
        if (! $this->homeroomId || ! in_array($this->homeroomId, $ids, true)) {
            $this->homeroomId = $ids[0] ?? null;
        }
    }

    protected function homeroomQuery(): Builder
    {
        return $this->scopeHomerooms(
            AssessmentPeriodHomeroom::query()
                ->where('assessment_period_id', $this->periodId)
                ->whereHas('period', fn (Builder $query): Builder => $query->where('type', static::$assessmentType->value)),
        );
    }

    protected function scopeHomerooms(Builder $query): Builder
    {
        $user = auth()->user();
        if (! $user instanceof User) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->hasFullAdminAccess() || $user->can('penilaian.verify') || $user->hasRole('kepala_sekolah')) {
            return $query;
        }

        return $user->guru_tendik_id
            ? $query->where('teacher_id', $user->guru_tendik_id)
            : $query->whereRaw('1 = 0');
    }

    protected function canEditHomeroom(AssessmentPeriodHomeroom $homeroom): bool
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return false;
        }

        if ($user->hasFullAdminAccess()
            || $user->hasRole('kurikulum')
            || $user->can('penilaian.verify')) {
            return true;
        }

        return $user->canViewModule('penilaian')
            && $user->guru_tendik_id !== null
            && (int) $user->guru_tendik_id === (int) $homeroom->teacher_id;
    }

    protected function collectPromotionStatus(AssessmentPeriod $period): bool
    {
        $configured = data_get($period->settings, 'collect_promotion_status');
        if (is_bool($configured)) {
            return $configured;
        }

        $type = $period->type instanceof AssessmentType
            ? $period->type
            : AssessmentType::tryFrom((string) $period->type);

        return $type === AssessmentType::ASAS;
    }

    /**
     * @return array<string, array<int, string>>
     */
    protected function getReportValidationRules(): array
    {
        $rules = ['rows' => ['array']];

        foreach ($this->getRecapFieldDefinitions() as $field => $definition) {
            if ($definition['input'] === 'items') {
                $rules["rows.*.{$field}"] = ['nullable', 'array'];
                $rules["rows.*.{$field}.*.name"] = ['required', 'string', 'max:255'];
                $rules["rows.*.{$field}.*.description"] = ['nullable', 'string', 'max:2000'];

                continue;
            }

            $rules["rows.*.{$field}"] = $definition['input'] === 'number'
                ? ['required', 'integer', 'min:0', "max:{$definition['max']}"]
                : ['nullable', 'string', "max:{$definition['max']}"];
        }

        $rules['rows.*.promotion_status'] = [
            'nullable',
            'string',
            'max:'.self::PROMOTION_STATUS_FIELD_DEFINITION['max'],
        ];

        return $rules;
    }

    /** @return array<int, array<string, mixed>> */
    protected function rowsForValidation(): array
    {
        return collect($this->reportRows)
            ->map(function (array $row): array {
                $row['extracurricular_items'] = $this->normalizeStructuredItems(
                    $row['extracurricular_items'] ?? [],
                    true,
                );
                $row['achievement_items'] = $this->normalizeStructuredItems(
                    $row['achievement_items'] ?? [],
                    true,
                );

                return $row;
            })
            ->all();
    }

    protected function isStructuredField(string $field): bool
    {
        return data_get(self::RECAP_FIELD_DEFINITIONS, "{$field}.input") === 'items';
    }

    /**
     * @return array<int, array{name:string,description:string}>
     */
    protected function normalizeStructuredItems(mixed $value, bool $removeBlank = false): array
    {
        return collect(is_array($value) ? $value : [])
            ->map(function (mixed $item): array {
                if (! is_array($item)) {
                    return ['name' => '', 'description' => trim((string) $item)];
                }

                return [
                    'name' => trim((string) ($item['name'] ?? '')),
                    'description' => trim((string) ($item['description'] ?? $item['grade'] ?? $item['level'] ?? '')),
                ];
            })
            ->when(
                $removeBlank,
                fn ($items) => $items->filter(
                    fn (array $item): bool => $item['name'] !== '' || $item['description'] !== '',
                ),
            )
            ->values()
            ->all();
    }

    /** @param array<int, array{name:string,description:string}> $items */
    protected function structuredItemsLength(array $items): int
    {
        return collect($items)->sum(
            fn (array $item): int => mb_strlen((string) ($item['name'] ?? ''))
                + mb_strlen((string) ($item['description'] ?? '')),
        );
    }
}
