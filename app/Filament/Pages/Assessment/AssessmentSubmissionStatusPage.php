<?php

namespace App\Filament\Pages\Assessment;

use App\Actions\Assessment\ReturnAssessmentAssignmentAction;
use App\Actions\Assessment\VerifyAssessmentAssignmentAction;
use App\Enums\Assessment\AssessmentType;
use App\Enums\Assessment\AssignmentStatus;
use App\Models\Assessment\AssessmentPeriod;
use App\Models\Assessment\AssessmentPeriodAssignment;
use App\Models\User;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;
use Throwable;

abstract class AssessmentSubmissionStatusPage extends AssessmentPage
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static string $assessmentPermission = 'penilaian.view';

    protected string $view = 'filament.pages.assessment.submission-status';

    protected static AssessmentType $assessmentType;

    #[Url(as: 'period')]
    public ?int $periodId = null;

    #[Url(as: 'status')]
    public string $statusFilter = 'all';

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
            $period->assignments()->withCount([
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
        $homeroomRombelIds = $user->can('penilaian.homeroom') && $periodId > 0
            ? \App\Models\Assessment\AssessmentPeriodHomeroom::query()
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

            if ($user->can('penilaian.homeroom')) {
                $periods->orWhereHas(
                    'homerooms',
                    fn (Builder $homerooms): Builder => $homerooms->where('teacher_id', $user->guru_tendik_id),
                );
            }
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
