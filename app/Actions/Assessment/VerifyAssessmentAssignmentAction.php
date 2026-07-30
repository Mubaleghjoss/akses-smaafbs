<?php

namespace App\Actions\Assessment;

use App\Enums\Assessment\AssessmentPeriodStatus;
use App\Enums\Assessment\AssignmentStatus;
use App\Models\Assessment\AssessmentPeriodAssignment;
use App\Models\User;
use App\Support\Assessment\AssessmentAuditLogger;
use App\Support\Assessment\AssessmentWorkflowGuard;
use Illuminate\Support\Facades\DB;

final class VerifyAssessmentAssignmentAction
{
    use AuthorizesAssessmentAction;

    public function __construct(
        private readonly AssessmentWorkflowGuard $guard,
        private readonly AssessmentAuditLogger $audit,
    ) {}

    public function execute(User $actor, AssessmentPeriodAssignment $assignment): AssessmentPeriodAssignment
    {
        $this->authorizePermission($actor, 'penilaian.verify');

        return DB::transaction(function () use ($actor, $assignment): AssessmentPeriodAssignment {
            /** @var AssessmentPeriodAssignment $locked */
            $locked = AssessmentPeriodAssignment::query()
                ->lockForUpdate()
                ->findOrFail($assignment->getKey());

            if ($this->isStatus($locked, AssignmentStatus::VERIFIED)) {
                $this->authorize($actor, 'view', $locked);

                return $locked->refresh();
            }

            $this->authorize($actor, 'verify', $locked);
            $locked->load('period');
            $this->guard->periodStatus(
                $locked->period,
                [AssessmentPeriodStatus::VERIFICATION],
                'Verifikasi hanya dapat dilakukan ketika periode berada pada tahap verifikasi.',
            );
            $this->guard->assignmentStatus(
                $locked,
                [AssignmentStatus::SUBMITTED],
                'Hanya nilai yang telah dikirim yang dapat diverifikasi.',
            );
            $oldVersion = (int) $locked->lock_version;
            $locked->forceFill([
                'status' => AssignmentStatus::VERIFIED,
                'verified_at' => now(),
                'verified_by' => $actor->getKey(),
                'lock_version' => $oldVersion + 1,
            ])->save();
            $this->audit->record(
                actor: $actor,
                event: 'assignment.verified',
                subject: $locked,
                oldValues: [
                    'status' => AssignmentStatus::SUBMITTED->value,
                    'lock_version' => $oldVersion,
                ],
                newValues: [
                    'status' => AssignmentStatus::VERIFIED->value,
                    'lock_version' => $locked->lock_version,
                    'verified_at' => $locked->verified_at?->toISOString(),
                    'verified_by' => $actor->getKey(),
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
