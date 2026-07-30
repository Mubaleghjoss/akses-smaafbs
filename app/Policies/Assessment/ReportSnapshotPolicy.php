<?php

namespace App\Policies\Assessment;

use App\Models\Assessment\ReportSnapshot;
use App\Models\User;

class ReportSnapshotPolicy extends AssessmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->canView($user);
    }

    public function view(User $user, ReportSnapshot $snapshot): bool
    {
        if ($this->canReadAll($user)) {
            return true;
        }

        return $user->can('penilaian.homeroom')
            && $this->ownsPeriodRombel($user, $snapshot->student->periodRombel);
    }

    public function create(User $user): bool
    {
        return $this->canGenerateReports($user);
    }

    public function generate(User $user, ReportSnapshot $snapshot): bool
    {
        return $this->canGenerateReports($user);
    }

    public function publish(User $user, ReportSnapshot $snapshot): bool
    {
        return $this->canPublish($user);
    }
}
