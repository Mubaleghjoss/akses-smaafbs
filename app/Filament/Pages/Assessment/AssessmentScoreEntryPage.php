<?php

namespace App\Filament\Pages\Assessment;

use App\Actions\Assessment\SaveAssessmentScoresAction;
use App\Actions\Assessment\SubmitAssessmentAssignmentAction;
use App\Enums\Assessment\AssessmentType;
use App\Enums\Assessment\AssignmentStatus;
use App\Filament\Pages\Assessment\Concerns\HasAssessmentTypeNavigation;
use App\Models\Assessment\AssessmentPeriod;
use App\Models\Assessment\AssessmentPeriodAssignment;
use App\Models\Assessment\AssessmentPeriodHomeroom;
use App\Models\Assessment\AssessmentScore;
use App\Models\Assessment\StudentSubjectResult;
use App\Models\User;
use App\Support\Assessment\AssessmentSchemeResolver;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Livewire\Attributes\Url;
use Throwable;

abstract class AssessmentScoreEntryPage extends AssessmentPage
{
    use HasAssessmentTypeNavigation;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-pencil-square';

    protected static string $assessmentPermission = 'penilaian.input';

    protected string $view = 'filament.pages.assessment.score-entry';

    protected static AssessmentType $assessmentType;

    #[Url(as: 'period')]
    public ?int $periodId = null;

    #[Url(as: 'assignment')]
    public ?int $assignmentId = null;

    public int $currentStudentIndex = 0;

    public int $lockVersion = 0;

    /** @var array<int, array<string, mixed>> */
    public array $scoreRows = [];

    /** @var array<int, array<string, mixed>> */
    public array $components = [];

    /** @var array<string, mixed>|null */
    public ?array $assignmentMeta = null;

    /** @var array<int, int|string> */
    public array $selectedStudentIds = [];

    public ?int $bulkComponentId = null;

    public string $bulkScore = '';

    public string $bulkDescription = '';

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
                        $user->can('penilaian.input')
                        || $user->can('penilaian.verify')
                        || $user->can('penilaian.homeroom')
                        || static::currentUserOwnsAssessmentHomeroom()
                        || $user->hasRole('kepala_sekolah')
                    )
                )
            );
    }

    public static function shouldRegisterNavigation(): bool
    {
        $user = auth()->user();

        return parent::shouldRegisterNavigation()
            && $user instanceof User
            && (
                $user->hasFullAdminAccess()
                || (
                    $user->canViewModule('penilaian')
                    && $user->can('penilaian.input')
                )
            );
    }

    public function mount(): void
    {
        $periodIds = array_map('intval', array_keys($this->getPeriodOptions()));

        if (! $this->periodId || ! in_array($this->periodId, $periodIds, true)) {
            $this->periodId = $periodIds[0] ?? null;
        }

        $this->selectDefaultAssignment();
        $this->loadAssignment();
    }

    public function getTitle(): string|Htmlable
    {
        return ($this->canEnterScores() ? 'Input Nilai Saya' : 'Tinjau Nilai')
            .' · '.static::$assessmentType->label();
    }

    public function getSubheading(): string|Htmlable|null
    {
        return $this->canEnterScores()
            ? 'Nilai disimpan sekaligus melalui tombol Simpan Draf. Perubahan di browser dipulihkan jika koneksi terputus.'
            : 'Mode baca-saja untuk memeriksa nilai per siswa, komponen, nilai akhir, dan deskripsi sebelum verifikasi.';
    }

    /**
     * @return array<int, string>
     */
    public function getPeriodOptions(): array
    {
        return AssessmentPeriod::query()
            ->where('type', static::$assessmentType->value)
            ->whereHas('assignments', fn (Builder $query): Builder => $this->scopeAssignments($query))
            ->latest('id')
            ->pluck('name', 'id')
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public function getAssignmentOptions(): array
    {
        if (! $this->periodId) {
            return [];
        }

        return $this->assignmentQuery()
            ->orderByRaw("CASE status
                WHEN 'returned' THEN 0
                WHEN 'draft' THEN 1
                WHEN 'submitted' THEN 2
                WHEN 'verified' THEN 3
                WHEN 'locked' THEN 4
                ELSE 5 END")
            ->orderBy('rombel_name_snapshot')
            ->orderBy('subject_name_snapshot')
            ->get()
            ->each(function (AssessmentPeriodAssignment $assignment): void {
                $status = $assignment->status instanceof AssignmentStatus
                    ? $assignment->status
                    : AssignmentStatus::from((string) $assignment->status);
                $assignment->subject_name_snapshot .= ' — '.$status->label();
            })
            ->mapWithKeys(fn (AssessmentPeriodAssignment $assignment): array => [
                $assignment->getKey() => "{$assignment->rombel_name_snapshot} · {$assignment->subject_name_snapshot}",
            ])
            ->all();
    }

    /**
     * @return array<string, int>
     */
    public function getAssignmentProgress(): array
    {
        if (! $this->periodId) {
            return ['total' => 0, 'sent' => 0, 'remaining' => 0];
        }

        $query = $this->assignmentQuery();
        $total = (clone $query)->count();
        $sent = (clone $query)
            ->whereIn('status', [
                AssignmentStatus::SUBMITTED->value,
                AssignmentStatus::VERIFIED->value,
                AssignmentStatus::LOCKED->value,
            ])
            ->count();

        return [
            'total' => $total,
            'sent' => $sent,
            'remaining' => max(0, $total - $sent),
        ];
    }

    public function updatedPeriodId(): void
    {
        $this->selectDefaultAssignment();
        $this->loadAssignment();
    }

    public function updatedAssignmentId(): void
    {
        $this->currentStudentIndex = 0;
        $this->loadAssignment();
    }

    public function loadAssignment(): void
    {
        $this->scoreRows = [];
        $this->components = [];
        $this->assignmentMeta = null;
        $this->lockVersion = 0;
        $this->selectedStudentIds = [];

        if (! $this->assignmentId) {
            return;
        }

        /** @var AssessmentPeriodAssignment|null $assignment */
        $assignment = $this->assignmentQuery()
            ->with(['period', 'periodRombel'])
            ->find($this->assignmentId);

        if (! $assignment) {
            $this->assignmentId = null;

            return;
        }

        $scheme = app(AssessmentSchemeResolver::class)->forAssignment($assignment);
        $scheme->load('components');
        $students = $assignment->period->students()
            ->where('assessment_period_rombel_id', $assignment->assessment_period_rombel_id)
            ->where('is_active', true)
            ->orderBy('student_name_snapshot')
            ->get();
        $scores = AssessmentScore::query()
            ->where('assessment_period_assignment_id', $assignment->getKey())
            ->get()
            ->keyBy(fn (AssessmentScore $score): string => $score->assessment_period_student_id.'|'.$score->assessment_component_id);
        $results = StudentSubjectResult::query()
            ->where('assessment_period_assignment_id', $assignment->getKey())
            ->get()
            ->keyBy('assessment_period_student_id');

        $this->components = $scheme->components
            ->map(fn ($component): array => [
                'id' => (int) $component->getKey(),
                'code' => (string) $component->code,
                'name' => (string) $component->name,
                'weight' => (float) $component->weight,
                'minimum_score' => (float) data_get(
                    $component->settings,
                    'minimum_score',
                    $scheme->minimum_score,
                ),
                'maximum_score' => (float) $component->maximum_score,
                'is_required' => (bool) $component->is_required,
                'score_source' => $component->score_source instanceof \BackedEnum
                    ? $component->score_source->value
                    : (string) $component->score_source,
            ])
            ->values()
            ->all();
        $this->bulkComponentId = collect($this->components)
            ->firstWhere('score_source', 'manual')['id'] ?? null;

        foreach ($students as $student) {
            $rowScores = [];
            foreach ($this->components as $component) {
                $rowScores[$component['id']] = $scores->get($student->getKey().'|'.$component['id'])?->score;
            }

            $this->scoreRows[(int) $student->getKey()] = [
                'student_id' => (int) $student->getKey(),
                'student_name' => (string) $student->student_name_snapshot,
                'nis' => (string) ($student->nis_snapshot ?: $student->nisn_snapshot ?: '-'),
                'scores' => $rowScores,
                'description' => $results->get($student->getKey())?->description,
                'final_score' => $results->get($student->getKey())?->final_score,
            ];
        }

        $status = $assignment->status instanceof AssignmentStatus
            ? $assignment->status
            : AssignmentStatus::from((string) $assignment->status);
        $this->lockVersion = (int) $assignment->lock_version;
        $this->assignmentMeta = [
            'id' => (int) $assignment->getKey(),
            'period' => (string) $assignment->period->name,
            'subject' => (string) $assignment->subject_name_snapshot,
            'rombel' => (string) $assignment->rombel_name_snapshot,
            'teacher' => (string) $assignment->teacher_name_snapshot,
            'status' => $status->value,
            'status_label' => $status->label(),
            'editable' => $status->isEditable()
                && Gate::forUser(auth()->user())->allows('updateScores', $assignment),
            'returned_reason' => $assignment->returned_reason,
        ];
    }

    public function saveDraft(): bool
    {
        $this->authorizeAssessment('penilaian.input');
        $assignment = $this->selectedAssignment();

        if (! $assignment) {
            Notification::make()->title('Pilih penugasan terlebih dahulu')->warning()->send();

            return false;
        }

        try {
            $saved = app(SaveAssessmentScoresAction::class)->execute(
                auth()->user(),
                $assignment,
                $this->rowsForAction(),
                $this->lockVersion,
            );
            $this->lockVersion = (int) $saved->lock_version;
            $this->dispatch('assessment-draft-cleared', key: $this->draftKey());
            $this->loadAssignment();
            Notification::make()
                ->title('Draf nilai tersimpan')
                ->body('Satu batch kelas berhasil disimpan. Nilai belum dikirim untuk verifikasi.')
                ->success()
                ->send();

            return true;
        } catch (Throwable $exception) {
            report($exception);
            Notification::make()
                ->title('Draf belum tersimpan')
                ->body($exception->getMessage())
                ->danger()
                ->duration(15000)
                ->send();

            return false;
        }
    }

    public function submitAssignment(): void
    {
        $this->authorizeAssessment('penilaian.submit');

        if (! $this->saveDraft()) {
            return;
        }

        $assignment = $this->selectedAssignment();
        if (! $assignment) {
            return;
        }

        try {
            app(SubmitAssessmentAssignmentAction::class)->execute(auth()->user(), $assignment);
            $this->loadAssignment();
            Notification::make()
                ->title('Nilai dikirim untuk verifikasi')
                ->body('Nilai tidak dapat diedit lagi kecuali dikembalikan oleh kurikulum.')
                ->success()
                ->duration(12000)
                ->send();
        } catch (Throwable $exception) {
            report($exception);
            Notification::make()
                ->title('Nilai belum dapat dikirim')
                ->body($exception->getMessage())
                ->danger()
                ->duration(15000)
                ->send();
        }
    }

    public function selectAllStudents(): void
    {
        $this->selectedStudentIds = array_map('intval', array_keys($this->scoreRows));
    }

    public function clearStudentSelection(): void
    {
        $this->selectedStudentIds = [];
    }

    public function applyBulkValues(): void
    {
        if (! data_get($this->assignmentMeta, 'editable')) {
            Notification::make()
                ->title('Penugasan ini hanya dapat ditinjau')
                ->body('Pilih penugasan berstatus Draf atau Dikembalikan untuk mengubah nilai.')
                ->warning()
                ->send();

            return;
        }

        $studentIds = collect($this->selectedStudentIds)
            ->map(fn (mixed $id): int => (int) $id)
            ->filter(fn (int $id): bool => array_key_exists($id, $this->scoreRows))
            ->unique()
            ->values();

        if ($studentIds->isEmpty()) {
            Notification::make()
                ->title('Pilih siswa terlebih dahulu')
                ->body('Centang siswa yang akan menerima nilai atau deskripsi massal.')
                ->warning()
                ->send();

            return;
        }

        $component = $this->bulkComponentId
            ? collect($this->components)->firstWhere('id', $this->bulkComponentId)
            : null;
        $hasScore = trim($this->bulkScore) !== '';
        $description = trim($this->bulkDescription);

        if (! $hasScore && $description === '') {
            Notification::make()
                ->title('Nilai atau deskripsi belum diisi')
                ->body('Isi minimal salah satu sebelum menerapkan ke siswa terpilih.')
                ->warning()
                ->send();

            return;
        }

        if ($hasScore) {
            if (! $component || $component['score_source'] !== 'manual') {
                Notification::make()->title('Pilih komponen nilai manual')->danger()->send();

                return;
            }

            if (! is_numeric($this->bulkScore)) {
                Notification::make()->title('Nilai massal harus berupa angka')->danger()->send();

                return;
            }

            $score = (float) $this->bulkScore;
            if ($score < (float) $component['minimum_score'] || $score > (float) $component['maximum_score']) {
                Notification::make()
                    ->title('Nilai di luar batas komponen')
                    ->body("Nilai {$component['name']} harus antara {$component['minimum_score']} dan {$component['maximum_score']}.")
                    ->danger()
                    ->send();

                return;
            }
        }

        $changed = 0;
        foreach ($studentIds as $studentId) {
            $rowChanged = false;

            if ($hasScore && $component) {
                $currentScore = data_get($this->scoreRows, "{$studentId}.scores.{$component['id']}");
                if (! $this->bulkFillEmptyOnly || $currentScore === null || $currentScore === '') {
                    $this->scoreRows[$studentId]['scores'][$component['id']] = (float) $this->bulkScore;
                    $rowChanged = true;
                }
            }

            if ($description !== '') {
                $currentDescription = trim((string) data_get($this->scoreRows, "{$studentId}.description", ''));
                if (! $this->bulkFillEmptyOnly || $currentDescription === '') {
                    $this->scoreRows[$studentId]['description'] = $description;
                    $rowChanged = true;
                }
            }

            if ($rowChanged) {
                $changed++;
            }
        }

        $this->dispatch('assessment-bulk-applied', key: $this->draftKey());
        Notification::make()
            ->title("Diterapkan ke {$changed} siswa")
            ->body('Perubahan baru berada di formulir. Tekan Simpan Draf untuk menyimpannya ke server.')
            ->success()
            ->duration(12000)
            ->send();
    }

    public function draftKey(): string
    {
        return 'assessment-draft-'.((int) auth()->id()).'-'.($this->assignmentId ?: 'none');
    }

    protected function selectDefaultAssignment(): void
    {
        $options = array_map('intval', array_keys($this->getAssignmentOptions()));
        if (! $this->assignmentId || ! in_array($this->assignmentId, $options, true)) {
            $this->assignmentId = $options[0] ?? null;
        }
    }

    protected function selectedAssignment(): ?AssessmentPeriodAssignment
    {
        return $this->assignmentId ? $this->assignmentQuery()->find($this->assignmentId) : null;
    }

    protected function assignmentQuery(): Builder
    {
        return $this->scopeAssignments(
            AssessmentPeriodAssignment::query()
                ->where('assessment_period_id', $this->periodId)
                ->whereHas('period', fn (Builder $query): Builder => $query->where('type', static::$assessmentType->value)),
        );
    }

    protected function scopeAssignments(Builder $query): Builder
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->hasFullAdminAccess() || $user->can('penilaian.verify') || $user->hasRole('kepala_sekolah')) {
            return $query;
        }

        if (! $user->guru_tendik_id) {
            return $query->whereRaw('1 = 0');
        }

        $homeroomRombelIds = $this->periodId
            ? AssessmentPeriodHomeroom::query()
                ->where('assessment_period_id', $this->periodId)
                ->where('teacher_id', $user->guru_tendik_id)
                ->pluck('assessment_period_rombel_id')
                ->all()
            : [];

        return $query->where(function (Builder $assignments) use ($user, $homeroomRombelIds): void {
            $assignments->where('teacher_id', $user->guru_tendik_id);

            if ($homeroomRombelIds !== []) {
                $assignments->orWhereIn('assessment_period_rombel_id', $homeroomRombelIds);
            }
        });
    }

    protected function canEnterScores(): bool
    {
        $user = auth()->user();

        return $user instanceof User
            && (
                $user->hasFullAdminAccess()
                || (
                    $user->canViewModule('penilaian')
                    && $user->can('penilaian.input')
                )
            );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function rowsForAction(): array
    {
        return collect($this->scoreRows)
            ->map(fn (array $row, int|string $studentId): array => [
                'assessment_period_student_id' => (int) $studentId,
                'scores' => collect($row['scores'] ?? [])
                    ->map(fn (mixed $score, int|string $componentId): array => [
                        'assessment_component_id' => (int) $componentId,
                        'score' => $score,
                    ])
                    ->all(),
                'description' => $row['description'] ?? null,
            ])
            ->values()
            ->all();
    }
}
