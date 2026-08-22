<?php

namespace App\Policies\Assessment;

use App\Models\Assessment\AssessmentPeriodRombel;
use App\Models\User;

abstract class AssessmentPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        if ($this->isFullAdmin($user)) {
            return null;
        }

        if (! $user->canViewModule('penilaian')) {
            return false;
        }

        return null;
    }

    protected function isFullAdmin(User $user): bool
    {
        return $user->hasAnyRole(['super_admin', 'admin', 'guru_admin']);
    }

    protected function canView(User $user): bool
    {
        return $this->isFullAdmin($user)
            || $user->hasAnyRole([
            'kurikulum',
            'guru_mapel',
            'guru',
            'wali_kelas',
            'kepala_sekolah',
        ]) || $user->can('penilaian.view');
    }

    protected function canManage(User $user): bool
    {
        return $this->isFullAdmin($user)
            || $user->hasRole('kurikulum')
            || $user->can('penilaian.period.manage');
    }

    protected function canVerify(User $user): bool
    {
        return $this->isFullAdmin($user)
            || $user->hasRole('kurikulum')
            || $user->can('penilaian.verify');
    }

    protected function canGenerateReports(User $user): bool
    {
        return $this->isFullAdmin($user)
            || $user->hasRole('kurikulum')
            || $user->can('penilaian.report.generate');
    }

    protected function canPublish(User $user): bool
    {
        return $this->isFullAdmin($user)
            || $user->hasRole('kurikulum')
            || $user->can('penilaian.publish');
    }

    protected function canReadAll(User $user): bool
    {
        return $this->isFullAdmin($user)
            || $user->hasAnyRole(['kurikulum', 'kepala_sekolah'])
            || $user->can('penilaian.verify');
    }

    protected function ownsTeacherId(User $user, int $teacherId): bool
    {
        return $user->guru_tendik_id !== null
            && (int) $user->guru_tendik_id === $teacherId;
    }

    protected function ownsPeriodRombel(User $user, AssessmentPeriodRombel $periodRombel): bool
    {
        if ($user->guru_tendik_id === null) {
            return false;
        }

        return $periodRombel->homeroom()
            ->where('teacher_id', (int) $user->guru_tendik_id)
            ->exists();
    }
}
