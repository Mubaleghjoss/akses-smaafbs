<?php

namespace App\Policies\Assessment;

use App\Models\Assessment\AssessmentPeriodRombel;
use App\Models\User;

class AssessmentPeriodRombelPolicy extends AssessmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->canView($user);
    }

    public function view(User $user, AssessmentPeriodRombel $rombel): bool
    {
        if ($this->canReadAll($user)) {
            return true;
        }

        if ($user->guru_tendik_id === null) {
            return false;
        }

        $teacherId = (int) $user->guru_tendik_id;

        return $rombel->assignments()->where('teacher_id', $teacherId)->exists()
            || $rombel->homeroom()->where('teacher_id', $teacherId)->exists();
    }
}
