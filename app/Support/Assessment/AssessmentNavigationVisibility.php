<?php

namespace App\Support\Assessment;

use App\Enums\Assessment\AssessmentPeriodStatus;
use App\Enums\Assessment\AssessmentType;
use App\Models\Assessment\AssessmentPeriod;
use App\Models\User;

final class AssessmentNavigationVisibility
{
    /** @var list<string> */
    private const OPERATIONAL_STATUSES = [
        AssessmentPeriodStatus::OPEN->value,
        AssessmentPeriodStatus::ENTRY_CLOSED->value,
        AssessmentPeriodStatus::VERIFICATION->value,
        AssessmentPeriodStatus::LOCKED->value,
    ];

    public function canManageNavigation(User $user): bool
    {
        return $user->hasFullAdminAccess()
            || $user->canManageModule('penilaian')
            || $user->can('penilaian.manage')
            || $user->can('penilaian.verify')
            || $user->hasRole('kurikulum');
    }

    public function hasRelevantOperationalWork(User $user, AssessmentType $type): bool
    {
        if (! $user->guru_tendik_id) {
            return false;
        }

        return AssessmentPeriod::query()
            ->where('type', $type->value)
            ->whereIn('status', self::OPERATIONAL_STATUSES)
            ->where(function ($periods) use ($user): void {
                $periods
                    ->whereHas('assignments', fn ($assignments) => $assignments->where('teacher_id', $user->guru_tendik_id))
                    ->orWhereHas('homerooms', fn ($homerooms) => $homerooms->where('teacher_id', $user->guru_tendik_id));
            })
            ->exists();
    }
}
