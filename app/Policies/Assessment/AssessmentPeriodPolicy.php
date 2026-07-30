<?php

namespace App\Policies\Assessment;

use App\Enums\Assessment\AssessmentPeriodStatus;
use App\Models\Assessment\AssessmentPeriod;
use App\Models\User;

class AssessmentPeriodPolicy extends AssessmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->canView($user);
    }

    public function view(User $user, AssessmentPeriod $period): bool
    {
        if ($this->canReadAll($user)) {
            return true;
        }

        if ($user->guru_tendik_id === null) {
            return false;
        }

        $teacherId = (int) $user->guru_tendik_id;

        return $period->assignments()->where('teacher_id', $teacherId)->exists()
            || $period->homerooms()->where('teacher_id', $teacherId)->exists();
    }

    public function create(User $user): bool
    {
        return $this->canManage($user);
    }

    public function update(User $user, AssessmentPeriod $period): bool
    {
        return $this->canManage($user)
            && $period->status === AssessmentPeriodStatus::DRAFT;
    }

    public function delete(User $user, AssessmentPeriod $period): bool
    {
        return $this->update($user, $period);
    }

    public function open(User $user, AssessmentPeriod $period): bool
    {
        return $this->canManage($user)
            && in_array($period->status, [
                AssessmentPeriodStatus::DRAFT,
                AssessmentPeriodStatus::OPEN,
            ], true);
    }

    public function closeEntry(User $user, AssessmentPeriod $period): bool
    {
        return $this->canManage($user)
            && in_array($period->status, [
                AssessmentPeriodStatus::OPEN,
                AssessmentPeriodStatus::ENTRY_CLOSED,
            ], true);
    }

    public function lock(User $user, AssessmentPeriod $period): bool
    {
        return $this->canManage($user)
            && in_array($period->status, [
                AssessmentPeriodStatus::VERIFICATION,
                AssessmentPeriodStatus::LOCKED,
            ], true);
    }

    public function startVerification(User $user, AssessmentPeriod $period): bool
    {
        return $this->canManage($user)
            && in_array($period->status, [
                AssessmentPeriodStatus::ENTRY_CLOSED,
                AssessmentPeriodStatus::VERIFICATION,
            ], true);
    }

    public function reopen(User $user, AssessmentPeriod $period): bool
    {
        return $this->canManage($user)
            && in_array($period->status, [
                AssessmentPeriodStatus::LOCKED,
                AssessmentPeriodStatus::PUBLISHED,
                AssessmentPeriodStatus::OPEN,
            ], true);
    }

    public function publish(User $user, AssessmentPeriod $period): bool
    {
        return $this->canPublish($user)
            && in_array($period->status, [
                AssessmentPeriodStatus::LOCKED,
                AssessmentPeriodStatus::PUBLISHED,
            ], true);
    }
}
