<?php

namespace App\Policies\Assessment;

use App\Models\Assessment\ClassReportArtifact;
use App\Models\User;

class ClassReportArtifactPolicy extends AssessmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->canView($user);
    }

    public function view(User $user, ClassReportArtifact $artifact): bool
    {
        return $this->canReadAll($user)
            || (
                $user->can('penilaian.homeroom')
                && $this->ownsPeriodRombel($user, $artifact->periodRombel)
            );
    }

    public function create(User $user): bool
    {
        return $this->canGenerateReports($user);
    }

    public function generate(User $user, ClassReportArtifact $artifact): bool
    {
        return $this->canGenerateReports($user);
    }
}
