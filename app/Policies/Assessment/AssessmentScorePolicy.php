<?php

namespace App\Policies\Assessment;

use App\Models\Assessment\AssessmentScore;
use App\Models\User;

class AssessmentScorePolicy extends AssessmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->canView($user);
    }

    public function view(User $user, AssessmentScore $score): bool
    {
        return app(AssessmentPeriodAssignmentPolicy::class)
            ->view($user, $score->assignment);
    }

    public function create(User $user): bool
    {
        return $user->can('penilaian.input');
    }

    public function update(User $user, AssessmentScore $score): bool
    {
        return app(AssessmentPeriodAssignmentPolicy::class)
            ->updateScores($user, $score->assignment);
    }

    public function delete(User $user, AssessmentScore $score): bool
    {
        return false;
    }
}
