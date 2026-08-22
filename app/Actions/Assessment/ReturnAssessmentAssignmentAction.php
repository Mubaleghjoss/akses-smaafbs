<?php

namespace App\Actions\Assessment;

use App\Enums\Assessment\AssessmentPeriodStatus;
use App\Enums\Assessment\AssignmentStatus;
use App\Models\Assessment\AssessmentPeriod;
use App\Models\Assessment\AssessmentPeriodAssignment;
use App\Models\User;
use App\Support\Assessment\AssessmentAuditLogger;
use App\Support\Assessment\AssessmentWorkflowGuard;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ReturnAssessmentAssignmentAction
{
    use AuthorizesAssessmentAction;

    public function __construct(
        private readonly AssessmentWorkflowGuard $guard,
        private readonly AssessmentAuditLogger $audit,
    ) {}

    public function execute(
        User $actor,
        AssessmentPeriodAssignment $assignment,
        string $reason,
    ): AssessmentPeriodAssignment {
        $this->authorizePermission($actor, 'penilaian.verify');

        $reason = trim($reason);

        if (mb_strlen($reason) < 10) {
            throw ValidationException::withMessages([
                'reason' => 'Alasan pengembalian wajib diisi minimal 10 karakter.',
            ]);
        }

        return DB::transaction(function () use ($actor, $assignment, $reason): AssessmentPeriodAssignment {
            /** @var AssessmentPeriodAssignment $locked */
            $locked = AssessmentPeriodAssignment::query()
                ->lockForUpdate()
                ->findOrFail($assignment->getKey());

            if ($this->isStatus($locked, AssignmentStatus::RETURNED)
                && trim((string) $locked->returned_reason) === $reason) {
                $this->authorize($actor, 'view', $locked);

                return $locked->refresh();
            }

            $this->authorize($actor, 'return', $locked);
            $this->guard->assignmentStatus(
                $locked,
                [AssignmentStatus::SUBMITTED, AssignmentStatus::VERIFIED],
                'Hanya nilai yang sudah dikirim atau diverifikasi yang dapat dikembalikan.',
            );
            /** @var AssessmentPeriod $period */
            $period = AssessmentPeriod::query()
                ->lockForUpdate()
                ->findOrFail($locked->assessment_period_id);
            $this->guard->periodStatus(
                $period,
                [AssessmentPeriodStatus::OPEN, AssessmentPeriodStatus::VERIFICATION],
                'Nilai pada periode yang sudah dikunci atau diterbitkan tidak dapat dikembalikan melalui alur koreksi biasa.',
            );
            $oldValues = [
                'status' => $locked->status->value,
                'lock_version' => $locked->lock_version,
                'verified_at' => $locked->verified_at?->toISOString(),
                'verified_by' => $locked->verified_by,
            ];
            $locked->forceFill([
                'status' => AssignmentStatus::RETURNED,
                'returned_at' => now(),
                'returned_by' => $actor->getKey(),
                'returned_reason' => $reason,
                'verified_at' => null,
                'verified_by' => null,
                'lock_version' => (int) $locked->lock_version + 1,
            ])->save();
            $this->audit->record(
                actor: $actor,
                event: 'assignment.returned',
                subject: $locked,
                oldValues: $oldValues,
                newValues: [
                    'status' => AssignmentStatus::RETURNED->value,
                    'lock_version' => $locked->lock_version,
                    'returned_at' => $locked->returned_at?->toISOString(),
                    'returned_by' => $actor->getKey(),
                ],
                reason: $reason,
            );

            if ($this->periodStatus($period) === AssessmentPeriodStatus::VERIFICATION) {
                $period->forceFill(['status' => AssessmentPeriodStatus::OPEN])->save();
                $this->audit->record(
                    actor: $actor,
                    event: 'period.reopened_for_return',
                    subject: $period,
                    oldValues: ['status' => AssessmentPeriodStatus::VERIFICATION->value],
                    newValues: ['status' => AssessmentPeriodStatus::OPEN->value],
                    reason: $reason,
                );
            }

            return $locked->refresh();
        }, 3);
    }

    private function isStatus(AssessmentPeriodAssignment $assignment, AssignmentStatus $status): bool
    {
        return ($assignment->status instanceof AssignmentStatus
            ? $assignment->status
            : AssignmentStatus::tryFrom((string) $assignment->status)) === $status;
    }

    private function periodStatus(AssessmentPeriod $period): ?AssessmentPeriodStatus
    {
        return $period->status instanceof AssessmentPeriodStatus
            ? $period->status
            : AssessmentPeriodStatus::tryFrom((string) $period->status);
    }
}
