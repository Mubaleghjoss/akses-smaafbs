<?php

namespace App\Policies\Assessment;

use App\Enums\Assessment\AssessmentPeriodStatus;
use App\Enums\Assessment\AssignmentStatus;
use App\Models\Assessment\AssessmentPeriodAssignment;
use App\Models\User;

class AssessmentPeriodAssignmentPolicy extends AssessmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->canView($user);
    }

    public function view(User $user, AssessmentPeriodAssignment $assignment): bool
    {
        if ($this->canReadAll($user)) {
            return true;
        }

        if ($this->ownsTeacherId($user, (int) $assignment->teacher_id)) {
            return true;
        }

        return $this->ownsPeriodRombel($user, $assignment->periodRombel);
    }

    public function updateScores(User $user, AssessmentPeriodAssignment $assignment): bool
    {
        return ($this->isFullAdmin($user)
                || ($user->can('penilaian.input')
                    && $this->ownsTeacherId($user, (int) $assignment->teacher_id)))
            && $assignment->period->status === AssessmentPeriodStatus::OPEN
            && $assignment->status->isEditable();
    }

    public function submit(User $user, AssessmentPeriodAssignment $assignment): bool
    {
        return ($this->isFullAdmin($user)
                || ($user->can('penilaian.submit')
                    && $this->ownsTeacherId($user, (int) $assignment->teacher_id)))
            && $assignment->period->status === AssessmentPeriodStatus::OPEN
            && in_array($assignment->status, [
                AssignmentStatus::DRAFT,
                AssignmentStatus::RETURNED,
                AssignmentStatus::SUBMITTED,
            ], true);
    }

    public function return(User $user, AssessmentPeriodAssignment $assignment): bool
    {
        return $this->canVerify($user)
            && in_array($assignment->status, [
                AssignmentStatus::SUBMITTED,
                AssignmentStatus::VERIFIED,
                AssignmentStatus::RETURNED,
            ], true);
    }

    public function verify(User $user, AssessmentPeriodAssignment $assignment): bool
    {
        return $this->canVerify($user)
            && in_array($assignment->status, [
                AssignmentStatus::SUBMITTED,
                AssignmentStatus::VERIFIED,
            ], true);
    }
}
