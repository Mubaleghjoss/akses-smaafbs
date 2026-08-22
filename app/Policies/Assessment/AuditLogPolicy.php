<?php

namespace App\Policies\Assessment;

use App\Models\Assessment\AuditLog;
use App\Models\User;

class AuditLogPolicy extends AssessmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->isFullAdmin($user)
            || $user->hasAnyRole(['kurikulum', 'kepala_sekolah'])
            || $user->can('penilaian.audit.view');
    }

    public function view(User $user, AuditLog $log): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, AuditLog $log): bool
    {
        return false;
    }

    public function delete(User $user, AuditLog $log): bool
    {
        return false;
    }
}
