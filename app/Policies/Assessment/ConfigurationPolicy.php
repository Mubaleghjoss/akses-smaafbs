<?php

namespace App\Policies\Assessment;

use App\Models\User;

abstract class ConfigurationPolicy extends AssessmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->canView($user);
    }

    public function view(User $user, mixed $model): bool
    {
        return $this->canView($user);
    }

    public function create(User $user): bool
    {
        return $this->canManage($user);
    }

    public function update(User $user, mixed $model): bool
    {
        return $this->canManage($user);
    }

    public function delete(User $user, mixed $model): bool
    {
        return $this->canManage($user);
    }

    public function restore(User $user, mixed $model): bool
    {
        return false;
    }

    public function forceDelete(User $user, mixed $model): bool
    {
        return false;
    }
}
