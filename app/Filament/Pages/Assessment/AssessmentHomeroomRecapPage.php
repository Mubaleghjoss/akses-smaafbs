<?php

namespace App\Filament\Pages\Assessment;

use App\Enums\Assessment\AssessmentPeriodStatus;
use App\Enums\Assessment\AssessmentType;
use App\Filament\Pages\Assessment\Concerns\HasAssessmentTypeNavigation;
use App\Models\Assessment\AssessmentPeriod;
use App\Models\Assessment\AssessmentPeriodHomeroom;
use App\Models\Assessment\AuditLog;
use App\Models\Assessment\HomeroomReport;
use App\Models\User;
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
        'extracurricular' => [
            'header' => 'Ekstrakurikuler',
            'bulk_label' => 'Ekstrakurikuler',
            'input' => 'text',
            'max' => 4000,
        ],
        'achievement' => [
            'header' => 'Prestasi',
            'bulk_label' => 'Prestasi',
            'input' => 'text',
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

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return config('assessment.enabled')
            && Schema::hasTable('assessment_periods')
            && $user instanceof User
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
                'extracurricular' => $this->listToText($report?->extracurricular_data),
                'achievement' => $this->listToText($report?->achievement_data),
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

    public function saveReports(): void
    {
        $homeroom = $this->homeroomId ? $this->homeroomQuery()->with('period')->findOrFail($this->homeroomId) : null;

        if (! $homeroom || ! $this->canEditHomeroom($homeroom)) {
            Notification::make()->title('Rekap tidak dapat diubah pada status periode ini')->warning()->send();

            return;
        }

        try {
            $validatedRows = Validator::make(
                ['rows' => $this->reportRows],
                $this->getReportValidationRules(),
                [
                    'rows.*.sick_days.max' => 'Jumlah hari sakit maksimal 366.',
                    'rows.*.permission_days.max' => 'Jumlah hari izin maksimal 366.',
                    'rows.*.absent_days.max' => 'Jumlah hari alpa maksimal 366.',
                ],
            )->validate()['rows'];

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
                        'extracurricular_data' => $this->textToList($row['extracurricular'] ?? null),
                        'achievement_data' => $this->textToList($row['achievement'] ?? null),
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

            Notification::make()->title('Rekap wali kelas tersimpan')->success()->send();
            $this->loadReports();
        } catch (Throwable $exception) {
            report($exception);
            Notification::make()->title('Rekap belum tersimpan')->body($exception->getMessage())->danger()->send();
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

        if ($user->hasFullAdminAccess()) {
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

    protected function textToList(mixed $value): array
    {
        return collect(preg_split('/\r\n|\r|\n/', (string) $value))
            ->map(fn (string $line): string => trim($line))
            ->filter()
            ->map(fn (string $line): array => ['description' => $line])
            ->values()
            ->all();
    }

    protected function listToText(mixed $value): string
    {
        return collect(is_array($value) ? $value : [])
            ->map(fn (mixed $item): string => is_array($item)
                ? (string) ($item['description'] ?? $item['name'] ?? '')
                : (string) $item)
            ->filter()
            ->implode("\n");
    }
}
