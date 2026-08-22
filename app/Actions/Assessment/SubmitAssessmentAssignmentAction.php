<?php

namespace App\Actions\Assessment;

use App\Enums\Assessment\AssessmentPeriodStatus;
use App\Enums\Assessment\AssignmentStatus;
use App\Models\Assessment\AssessmentPeriodAssignment;
use App\Models\User;
use App\Support\Assessment\AssessmentAuditLogger;
use App\Support\Assessment\AssessmentSchemeResolver;
use App\Support\Assessment\AssessmentWorkflowGuard;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class SubmitAssessmentAssignmentAction
{
    use AuthorizesAssessmentAction;

    public function __construct(
        private readonly AssessmentWorkflowGuard $guard,
        private readonly AssessmentSchemeResolver $schemeResolver,
        private readonly CalculateStudentSubjectResultAction $calculateResult,
        private readonly AssessmentAuditLogger $audit,
    ) {}

    public function execute(User $actor, AssessmentPeriodAssignment $assignment): AssessmentPeriodAssignment
    {
        $this->authorizePermission($actor, 'penilaian.submit');

        return DB::transaction(function () use ($actor, $assignment): AssessmentPeriodAssignment {
            /** @var AssessmentPeriodAssignment $locked */
            $locked = AssessmentPeriodAssignment::query()
                ->lockForUpdate()
                ->findOrFail($assignment->getKey());

            if ($this->isStatus($locked, AssignmentStatus::SUBMITTED)) {
                $this->authorize($actor, 'view', $locked);

                return $locked->refresh();
            }

            $this->authorize($actor, 'submit', $locked);
            $locked->load('period');
            $this->guard->periodStatus(
                $locked->period,
                [AssessmentPeriodStatus::OPEN],
                'Nilai hanya dapat dikirim ketika periode berstatus terbuka.',
            );
            if ($locked->status === AssignmentStatus::DRAFT) {
                $this->guard->entryWindow($locked->period);
            }
            $this->guard->assignmentStatus(
                $locked,
                [AssignmentStatus::DRAFT, AssignmentStatus::RETURNED],
                'Penugasan ini tidak dapat dikirim pada status saat ini.',
            );
            $scheme = $this->schemeResolver->forAssignment($locked);
            $students = $locked->period->students()
                ->where('assessment_period_rombel_id', $locked->assessment_period_rombel_id)
                ->where('is_active', true)
                ->orderBy('student_name_snapshot')
                ->get();

            if ($students->isEmpty()) {
                throw ValidationException::withMessages([
                    'students' => 'Snapshot kelas tidak memiliki siswa aktif.',
                ]);
            }

            $incompleteStudents = [];

            foreach ($students as $student) {
                $result = $this->calculateResult->execute($locked, $student);

                if ($result->final_score === null) {
                    $missing = collect(data_get($result->calculation_detail, 'missing_required_component_ids', []));
                    $componentNames = $scheme->components
                        ->whereIn('id', $missing)
                        ->pluck('name')
                        ->implode(', ');
                    $incompleteStudents[] = $student->student_name_snapshot
                        .($componentNames !== '' ? " ({$componentNames})" : '');
                }
            }

            if ($incompleteStudents !== []) {
                throw ValidationException::withMessages([
                    'scores' => 'Komponen wajib belum lengkap untuk: '.collect($incompleteStudents)->take(10)->implode('; ')
                        .(count($incompleteStudents) > 10 ? '; dan siswa lainnya.' : '.'),
                ]);
            }

            $oldValues = [
                'status' => $locked->status->value,
                'lock_version' => $locked->lock_version,
                'returned_reason' => $locked->returned_reason,
            ];
            $locked->forceFill([
                'status' => AssignmentStatus::SUBMITTED,
                'submitted_at' => now(),
                'submitted_by' => $actor->getKey(),
                'verified_at' => null,
                'verified_by' => null,
                'lock_version' => (int) $locked->lock_version + 1,
            ])->save();
            $this->audit->record(
                actor: $actor,
                event: 'assignment.submitted',
                subject: $locked,
                oldValues: $oldValues,
                newValues: [
                    'status' => AssignmentStatus::SUBMITTED->value,
                    'lock_version' => $locked->lock_version,
                    'submitted_at' => $locked->submitted_at?->toISOString(),
                    'submitted_by' => $actor->getKey(),
                ],
            );

            return $locked->refresh();
        }, 3);
    }

    private function isStatus(AssessmentPeriodAssignment $assignment, AssignmentStatus $status): bool
    {
        return ($assignment->status instanceof AssignmentStatus
            ? $assignment->status
            : AssignmentStatus::tryFrom((string) $assignment->status)) === $status;
    }
}
