<?php

namespace App\Filament\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;

trait HasAssessmentPermissions
{
    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny();
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return config('assessment.enabled')
            && Schema::hasTable('assessment_periods')
            && $user instanceof User
            && (
                $user->hasFullAdminAccess()
                || (
                    $user->canViewModule('penilaian')
                    && $user->can(static::$assessmentManagePermission)
                )
            );
    }

    public static function canViewAny(): bool
    {
        return static::canAccess()
            && Gate::allows('viewAny', static::getModel());
    }

    public static function canCreate(): bool
    {
        return static::canAccess()
            && static::canManageAssessment()
            && Gate::allows('create', static::getModel());
    }

    public static function canEdit(Model $record): bool
    {
        return static::canAccess()
            && static::canManageAssessment()
            && Gate::allows('update', $record);
    }

    public static function canDelete(Model $record): bool
    {
        return static::canAccess()
            && static::canManageAssessment()
            && Gate::allows('delete', $record);
    }

    protected static function canManageAssessment(): bool
    {
        $user = auth()->user();

        return $user instanceof User
            && (
                $user->hasFullAdminAccess()
                || (
                    $user->canManageModule('penilaian')
                    && $user->can(static::$assessmentManagePermission)
                )
            );
    }
}
