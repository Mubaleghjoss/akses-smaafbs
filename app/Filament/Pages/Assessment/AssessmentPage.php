<?php

namespace App\Filament\Pages\Assessment;

use App\Models\User;
use App\Support\Admin\AdminSchoolNavigation;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Schema;

abstract class AssessmentPage extends Page
{
    protected static string|\UnitEnum|null $navigationGroup = 'Manajemen Sekolah';

    protected static string $assessmentPermission = 'penilaian.view';

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess()
            && AdminSchoolNavigation::shouldRegisterAssessmentClass(static::class);
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        if (! config('assessment.enabled') || ! Schema::hasTable('assessment_periods')) {
            return false;
        }

        if (! $user instanceof User) {
            return false;
        }

        if ($user->hasFullAdminAccess()) {
            return true;
        }

        return $user->canViewModule('penilaian')
            && (
                $user->can(static::$assessmentPermission)
                || static::$assessmentPermission === 'penilaian.view'
            );
    }

    protected function authorizeAssessment(string $ability): void
    {
        $user = auth()->user();

        abort_unless(
            config('assessment.enabled')
                && Schema::hasTable('assessment_periods')
                && $user instanceof User
                && (
                    $user->hasFullAdminAccess()
                    || (
                        $user->canViewModule('penilaian')
                        && $user->can($ability)
                    )
                ),
            403,
        );
    }
}
