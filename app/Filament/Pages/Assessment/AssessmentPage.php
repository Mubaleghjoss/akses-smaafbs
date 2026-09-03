<?php

namespace App\Filament\Pages\Assessment;

use App\Models\User;
use App\Support\Admin\AdminSchoolNavigation;
use App\Support\Assessment\AssessmentPageMap;
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

        if (! $user->canAccessNavigationItem(static::navigationAccessClass())) {
            return false;
        }

        return $user->canViewModule('penilaian')
            && (
                $user->can(static::$assessmentPermission)
                || static::$assessmentPermission === 'penilaian.view'
            );
    }

    protected static function navigationAccessClass(): string
    {
        foreach (AssessmentPageMap::all() as $pages) {
            if (in_array(static::class, $pages, true)) {
                return $pages['hub'];
            }
        }

        return static::class;
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
