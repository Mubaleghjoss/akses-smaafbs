<?php

namespace App\Policies\Assessment;

use App\Models\Assessment\AssessmentPeriodHomeroom;
use App\Models\User;

class AssessmentPeriodHomeroomPolicy extends AssessmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->canView($user);
    }

    public function view(User $user, AssessmentPeriodHomeroom $homeroom): bool
    {
        return $this->canReadAll($user)
            || (
                $user->can('penilaian.homeroom')
                && $this->ownsTeacherId($user, (int) $homeroom->teacher_id)
            );
    }
}
