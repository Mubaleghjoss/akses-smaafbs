<?php

namespace App\Policies\Assessment;

use App\Models\Assessment\AssessmentPeriodStudent;
use App\Models\User;

class AssessmentPeriodStudentPolicy extends AssessmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->canView($user);
    }

    public function view(User $user, AssessmentPeriodStudent $student): bool
    {
        if ($this->canReadAll($user)) {
            return true;
        }

        if ($user->guru_tendik_id === null) {
            return false;
        }

        $teacherId = (int) $user->guru_tendik_id;

        return $student->periodRombel->assignments()
            ->where('teacher_id', $teacherId)
            ->exists()
            || $student->periodRombel->homeroom()
                ->where('teacher_id', $teacherId)
                ->exists();
    }
}
