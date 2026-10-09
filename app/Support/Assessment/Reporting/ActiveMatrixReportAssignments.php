<?php

namespace App\Support\Assessment\Reporting;

use App\Models\Assessment\AssessmentPeriod;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Limits live report subjects to matrix cells that currently have a teacher.
 * Legacy assignments with no corresponding matrix cell remain reportable.
 */
final class ActiveMatrixReportAssignments
{
    /**
     * @param  Collection<int, object>  $assignments
     * @return Collection<int, object>
     */
    public function filter(AssessmentPeriod $period, Collection $assignments): Collection
    {
        if ($assignments->isEmpty()) {
            return $assignments;
        }

        $rombelSourceIds = DB::table('assessment_period_rombels')
            ->where('assessment_period_id', $period->getKey())
            ->whereIn('id', $assignments->pluck('assessment_period_rombel_id')->unique())
            ->pluck('source_rombel_id', 'id');
        $subjectIds = $assignments->pluck('assessment_subject_id')->filter()->unique();
        $matrixByScope = DB::table('assessment_teaching_assignments')
            ->where('assessment_semester_id', $period->assessment_semester_id)
            ->whereIn('rombel_id', $rombelSourceIds->values()->filter()->unique())
            ->whereIn('assessment_subject_id', $subjectIds)
            ->get(['rombel_id', 'assessment_subject_id', 'is_active', 'teacher_id'])
            ->groupBy(fn (object $row): string => $row->rombel_id.'|'.$row->assessment_subject_id);

        return $assignments->filter(function (object $assignment) use ($matrixByScope, $rombelSourceIds): bool {
            $sourceRombelId = $rombelSourceIds->get($assignment->assessment_period_rombel_id);
            $matrixRows = $matrixByScope->get($sourceRombelId.'|'.$assignment->assessment_subject_id);

            // Only hide retained work when the matrix proves the cell is blank/inactive.
            return ! $matrixRows || $matrixRows->contains(
                fn (object $row): bool => (bool) $row->is_active && filled($row->teacher_id),
            );
        })->values();
    }
}
