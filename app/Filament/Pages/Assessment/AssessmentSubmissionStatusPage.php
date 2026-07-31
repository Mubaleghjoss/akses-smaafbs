<?php

namespace App\Filament\Pages\Assessment;

use App\Actions\Assessment\ReturnAssessmentAssignmentAction;
use App\Actions\Assessment\VerifyAssessmentAssignmentAction;
use App\Enums\Assessment\AssessmentType;
use App\Enums\Assessment\AssignmentStatus;
use App\Filament\Pages\Assessment\Concerns\HasAssessmentTypeNavigation;
use App\Models\Assessment\AssessmentPeriod;
use App\Models\Assessment\AssessmentPeriodAssignment;
use App\Models\Assessment\AssessmentPeriodHomeroom;
use App\Models\User;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Url;
use Throwable;

abstract class AssessmentSubmissionStatusPage extends AssessmentPage
{
    use HasAssessmentTypeNavigation;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static string $assessmentPermission = 'penilaian.view';

    protected string $view = 'filament.pages.assessment.submission-status';

    protected static AssessmentType $assessmentType;

    #[Url(as: 'period')]
    public ?int $periodId = null;

    #[Url(as: 'status')]
    public string $statusFilter = 'all';

    /** @var array<int, int|string> */
    public array $selectedAssignmentIds = [];

    /** @var array<int, int> */
    public array $returnTargetIds = [];

    public string $returnReason = '';

    public function mount(): void
    {
        $ids = array_map('intval', array_keys($this->getPeriodOptions()));
        if (! $this->periodId || ! in_array($this->periodId, $ids, true)) {
            $this->periodId = $ids[0] ?? null;
        }
    }

    public function getTitle(): string|Htmlable
    {
        return 'Status Pengumpulan · '.static::$assessmentType->label();
    }

    public function getPeriodOptions(): array
    {
        return $this->scopePeriods(AssessmentPeriod::query())
            ->where('type', static::$assessmentType->value)
            ->latest('id')
            ->pluck('name', 'id')
            ->all();
    }

    public function getStatusOptions(): array
    {
        return ['all' => 'Semua Status'] + AssignmentStatus::options();
    }

    public function updatedPeriodId(): void
    {
        $this->selectedAssignmentIds = [];
    }

    public function updatedStatusFilter(): void
    {
        $this->selectedAssignmentIds = [];
    }

    public function selectAllFilteredAssignments(): void
    {
        $this->selectedAssignmentIds = collect($this->getAssignmentRows())
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();
    }

    public function clearAssignmentSelection(): void
    {
        $this->selectedAssignmentIds = [];
    }

    public function verifySelectedAssignments(): void
    {
        $this->authorizeAssessment('penilaian.verify');

        try {
            $assignments = $this->selectedScopedAssignments();
            DB::transaction(function () use ($assignments): void {
                foreach ($assignments as $assignment) {
                    if ($this->assignmentStatus($assignment) !== AssignmentStatus::SUBMITTED) {
                        throw ValidationException::withMessages([
                            'assignments' => "{$assignment->rombel_name_snapshot} · {$assignment->subject_name_snapshot} belum berstatus Dikirim.",
                        ]);
                    }
                }

                foreach ($assignments as $assignment) {
                    app(VerifyAssessmentAssignmentAction::class)->execute(auth()->user(), $assignment);
                }
            }, 3);

            $count = $assignments->count();
            $this->selectedAssignmentIds = [];
            Notification::make()->title("{$count} penugasan terverifikasi")->success()->send();
        } catch (Throwable $exception) {
            report($exception);
            Notification::make()->title('Verifikasi massal dibatalkan')->body($exception->getMessage())->danger()->send();
        }
    }

    public function prepareReturn(?int $assignmentId = null): void
    {
        $this->authorizeAssessment('penilaian.verify');
        $this->returnReason = '';
        $this->returnTargetIds = $assignmentId
            ? [$assignmentId]
            : collect($this->selectedAssignmentIds)->map(fn (mixed $id): int => (int) $id)->all();

        if ($this->returnTargetIds === []) {
            Notification::make()->title('Pilih minimal satu penugasan')->warning()->send();

            return;
        }

        $this->dispatch('open-modal', id: 'assessment-return-modal');
    }

    public function confirmReturnAssignments(): void
    {
        $this->authorizeAssessment('penilaian.verify');
        $reason = trim($this->returnReason);

        if (mb_strlen($reason) < 10) {
            $this->addError('returnReason', 'Alasan revisi minimal 10 karakter.');

            return;
        }

        try {
            $originalSelection = $this->selectedAssignmentIds;
            try {
                $this->selectedAssignmentIds = $this->returnTargetIds;
                $assignments = $this->selectedScopedAssignments();
            } finally {
                $this->selectedAssignmentIds = $originalSelection;
            }

            DB::transaction(function () use ($assignments, $reason): void {
                foreach ($assignments as $assignment) {
                    if (! in_array($this->assignmentStatus($assignment), [AssignmentStatus::SUBMITTED, AssignmentStatus::VERIFIED], true)) {
                        throw ValidationException::withMessages([
                            'assignments' => "{$assignment->rombel_name_snapshot} · {$assignment->subject_name_snapshot} tidak dapat dikembalikan pada status saat ini.",
                        ]);
                    }
                }

                foreach ($assignments as $assignment) {
                    app(ReturnAssessmentAssignmentAction::class)->execute(auth()->user(), $assignment, $reason);
                }
            }, 3);

            $count = $assignments->count();
            $this->selectedAssignmentIds = array_values(array_diff(
                array_map('intval', $this->selectedAssignmentIds),
                $assignments->modelKeys(),
            ));
            $this->returnTargetIds = [];
            $this->returnReason = '';
            $this->dispatch('close-modal', id: 'assessment-return-modal');
            Notification::make()->title("{$count} penugasan dikembalikan untuk revisi")->success()->send();
        } catch (Throwable $exception) {
            report($exception);
            Notification::make()->title('Pengembalian massal dibatalkan')->body($exception->getMessage())->danger()->send();
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getAssignmentRows(): array
    {
        if (! $this->periodId) {
            return [];
        }

        $period = AssessmentPeriod::query()->find($this->periodId);
        if (! $period || ($period->type instanceof AssessmentType ? $period->type : AssessmentType::from($period->type)) !== static::$assessmentType) {
            return [];
        }

        $studentCounts = $period->students()
            ->selectRaw('assessment_period_rombel_id, COUNT(*) as aggregate')
            ->where('is_active', true)
            ->groupBy('assessment_period_rombel_id')
            ->pluck('aggregate', 'assessment_period_rombel_id');
        $query = $this->scopeAssignments(
            $period->assignments()->getQuery()->withCount([
                'results',
                'results as completed_results_count' => fn (Builder $builder): Builder => $builder->whereNotNull('final_score'),
            ]),
        );

        if ($this->statusFilter !== 'all' && array_key_exists($this->statusFilter, AssignmentStatus::options())) {
            $query->where('status', $this->statusFilter);
        }

        return $query
            ->orderBy('rombel_name_snapshot')
            ->orderBy('subject_name_snapshot')
            ->get()
            ->map(function (AssessmentPeriodAssignment $assignment) use ($studentCounts): array {
                $status = $assignment->status instanceof AssignmentStatus
                    ? $assignment->status
                    : AssignmentStatus::from((string) $assignment->status);
                $studentCount = (int) ($studentCounts[$assignment->assessment_period_rombel_id] ?? 0);

                return [
                    'id' => (int) $assignment->getKey(),
                    'rombel' => (string) $assignment->rombel_name_snapshot,
                    'subject' => (string) $assignment->subject_name_snapshot,
                    'teacher' => (string) $assignment->teacher_name_snapshot,
                    'status' => $status->value,
                    'status_label' => $status->label(),
                    'student_count' => $studentCount,
                    'completed_count' => (int) $assignment->completed_results_count,
                    'completion_percent' => $studentCount > 0
                        ? (int) round(((int) $assignment->completed_results_count / $studentCount) * 100)
                        : 0,
                    'submitted_at' => $assignment->submitted_at?->format('d/m/Y H:i'),
                    'returned_reason' => $assignment->returned_reason,
                    'review_url' => $this->scoreReviewUrl($assignment),
                ];
            })
            ->all();
    }

    public function verifyAssignment(int $assignmentId): void
    {
        $this->authorizeAssessment('penilaian.verify');
        $assignment = $this->scopedAssignment($assignmentId);

        try {
            app(VerifyAssessmentAssignmentAction::class)->execute(auth()->user(), $assignment);
            Notification::make()->title('Penugasan terverifikasi')->success()->send();
        } catch (Throwable $exception) {
            report($exception);
            Notification::make()->title('Verifikasi gagal')->body($exception->getMessage())->danger()->send();
        }
    }

    public function returnAssignment(int $assignmentId, string $reason): void
    {
        $this->authorizeAssessment('penilaian.verify');
        $assignment = $this->scopedAssignment($assignmentId);

        try {
            app(ReturnAssessmentAssignmentAction::class)->execute(auth()->user(), $assignment, trim($reason));
            Notification::make()->title('Penugasan dikembalikan')->success()->send();
        } catch (Throwable $exception) {
            report($exception);
            Notification::make()->title('Pengembalian gagal')->body($exception->getMessage())->danger()->send();
        }
    }

    protected function scopedAssignment(int $id): AssessmentPeriodAssignment
    {
        return $this->scopeAssignments(
            AssessmentPeriodAssignment::query()
                ->where('assessment_period_id', $this->periodId)
                ->whereHas('period', fn (Builder $query): Builder => $query->where('type', static::$assessmentType->value)),
        )->findOrFail($id);
    }

    protected function selectedScopedAssignments()
    {
        $ids = collect($this->selectedAssignmentIds)
            ->map(fn (mixed $id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            throw ValidationException::withMessages([
                'assignments' => 'Pilih minimal satu penugasan.',
            ]);
        }

        $assignments = $this->scopeAssignments(
            AssessmentPeriodAssignment::query()
                ->where('assessment_period_id', $this->periodId)
                ->whereHas('period', fn (Builder $query): Builder => $query->where('type', static::$assessmentType->value)),
        )
            ->whereIn('id', $ids)
            ->orderBy('id')
            ->get();

        if ($assignments->count() !== $ids->count()) {
            throw ValidationException::withMessages([
                'assignments' => 'Sebagian pilihan tidak valid atau tidak berada dalam cakupan Anda.',
            ]);
        }

        return $assignments;
    }

    protected function assignmentStatus(AssessmentPeriodAssignment $assignment): AssignmentStatus
    {
        return $assignment->status instanceof AssignmentStatus
            ? $assignment->status
            : AssignmentStatus::from((string) $assignment->status);
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

        $periodId = (int) ($this->periodId ?? 0);
        $homeroomRombelIds = $periodId > 0
            ? AssessmentPeriodHomeroom::query()
                ->where('assessment_period_id', $periodId)
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

    protected function scopePeriods(Builder $query): Builder
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

        return $query->where(function (Builder $periods) use ($user): void {
            $periods->whereHas(
                'assignments',
                fn (Builder $assignments): Builder => $assignments->where('teacher_id', $user->guru_tendik_id),
            );

            $periods->orWhereHas(
                'homerooms',
                fn (Builder $homerooms): Builder => $homerooms->where('teacher_id', $user->guru_tendik_id),
            );
        });
    }

    protected function scoreReviewUrl(AssessmentPeriodAssignment $assignment): string
    {
        $page = static::$assessmentType === AssessmentType::ASTS
            ? AstsInputScores::class
            : AsasInputScores::class;

        return $page::getUrl([
            'period' => $assignment->assessment_period_id,
            'assignment' => $assignment->getKey(),
        ]);
    }
}
