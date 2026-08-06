<?php

namespace App\Policies\Assessment;

use App\Models\Assessment\SubjectCategory;
use App\Models\User;

class SubjectCategoryPolicy extends ConfigurationPolicy
{
    public function delete(User $user, mixed $model): bool
    {
        return $model instanceof SubjectCategory
            && ! $model->teachingAssignments()->exists()
            && parent::delete($user, $model);
    }
}
