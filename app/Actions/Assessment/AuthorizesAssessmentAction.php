<?php

namespace App\Actions\Assessment;

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;

trait AuthorizesAssessmentAction
{
    protected function authorizePermission(User $actor, string $permission): void
    {
        abort_unless(
            config('assessment.enabled') && Schema::hasTable('assessment_periods'),
            404,
        );

        if (! $actor->hasFullAdminAccess() && (
            ! $actor->canViewModule('penilaian')
            || ! $actor->can($permission)
        )) {
            throw new AuthorizationException('Akun tidak memiliki izin untuk tindakan Penilaian ini.');
        }
    }

    protected function authorize(User $actor, string $ability, Model $subject): Response
    {
        abort_unless(
            config('assessment.enabled') && Schema::hasTable('assessment_periods'),
            404,
        );

        return Gate::forUser($actor)->authorize($ability, $subject);
    }
}
