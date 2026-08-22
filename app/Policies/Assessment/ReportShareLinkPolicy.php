<?php

namespace App\Policies\Assessment;

use App\Models\Assessment\ReportShareLink;
use App\Models\User;

class ReportShareLinkPolicy extends AssessmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->canGenerateReports($user);
    }

    public function view(User $user, ReportShareLink $link): bool
    {
        return $this->canGenerateReports($user);
    }

    public function create(User $user): bool
    {
        return $this->canPublish($user);
    }

    public function update(User $user, ReportShareLink $link): bool
    {
        return $this->canPublish($user);
    }

    public function revoke(User $user, ReportShareLink $link): bool
    {
        return $this->canPublish($user);
    }
}
