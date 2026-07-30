<?php

namespace App\Policies\Assessment;

use App\Models\Assessment\StudentSubjectResult;
use App\Models\User;

class StudentSubjectResultPolicy extends AssessmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->canView($user);
    }

    public function view(User $user, StudentSubjectResult $result): bool
    {
        return app(AssessmentPeriodAssignmentPolicy::class)
            ->view($user, $result->assignment);
    }

    public function update(User $user, StudentSubjectResult $result): bool
    {
        return app(AssessmentPeriodAssignmentPolicy::class)
            ->updateScores($user, $result->assignment);
    }
}
