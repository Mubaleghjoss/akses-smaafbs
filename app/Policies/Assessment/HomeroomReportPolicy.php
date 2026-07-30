<?php

namespace App\Policies\Assessment;

use App\Enums\Assessment\AssessmentPeriodStatus;
use App\Models\Assessment\AssessmentPeriodHomeroom;
use App\Models\Assessment\HomeroomReport;
use App\Models\User;

class HomeroomReportPolicy extends AssessmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->canView($user);
    }

    public function view(User $user, HomeroomReport $report): bool
    {
        return $this->canReadAll($user)
            || (
                $user->can('penilaian.homeroom')
                && $this->ownsPeriodRombel($user, $report->student->periodRombel)
            );
    }

    public function create(User $user, AssessmentPeriodHomeroom $homeroom): bool
    {
        $periodStatus = $homeroom->period->status;
        $periodStatus = $periodStatus instanceof AssessmentPeriodStatus
            ? $periodStatus
            : AssessmentPeriodStatus::from((string) $periodStatus);

        return (
            $this->isFullAdmin($user)
            || (
                $user->can('penilaian.homeroom')
                && $this->ownsTeacherId($user, (int) $homeroom->teacher_id)
            )
        ) && in_array($periodStatus, [
            AssessmentPeriodStatus::OPEN,
            AssessmentPeriodStatus::ENTRY_CLOSED,
            AssessmentPeriodStatus::VERIFICATION,
        ], true);
    }

    public function update(User $user, HomeroomReport $report): bool
    {
        return ($this->isFullAdmin($user)
                || (
                    $user->can('penilaian.homeroom')
                    && $this->ownsPeriodRombel($user, $report->student->periodRombel)
                ))
            && in_array($report->period->status, [
                AssessmentPeriodStatus::OPEN,
                AssessmentPeriodStatus::ENTRY_CLOSED,
                AssessmentPeriodStatus::VERIFICATION,
            ], true);
    }
}
