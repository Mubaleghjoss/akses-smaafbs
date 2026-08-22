<?php

namespace App\Support\Assessment;

use App\Enums\Assessment\AssessmentPeriodStatus;
use App\Enums\Assessment\AssessmentType;
use App\Models\Assessment\AssessmentComponent;
use App\Models\Assessment\AssessmentPeriod;
use App\Models\Assessment\AssessmentPeriodAssignment;
use App\Models\Assessment\AssessmentPeriodStudent;
use App\Models\Assessment\StudentSubjectResult;

final class AssessmentAstsSourceResolver
{
    public function forStudent(
        AssessmentPeriodAssignment $assignment,
        AssessmentPeriodStudent $student,
        AssessmentComponent $component,
    ): ?StudentSubjectResult {
        $period = AssessmentPeriod::query()->find($assignment->assessment_period_id);
        $periodType = $period?->type instanceof AssessmentType
            ? $period->type
            : AssessmentType::tryFrom((string) $period?->type);

        if (! $period || $periodType !== AssessmentType::ASAS) {
            return null;
        }

        $sourcePeriodId = AssessmentPeriod::query()
            ->where('assessment_academic_year_id', $period->assessment_academic_year_id)
            ->where('assessment_semester_id', $period->assessment_semester_id)
            ->where('type', AssessmentType::ASTS->value)
            ->whereIn('status', [
                AssessmentPeriodStatus::LOCKED->value,
                AssessmentPeriodStatus::PUBLISHED->value,
            ])
            ->latest('id')
            ->value('id');

        if (! $sourcePeriodId) {
            return null;
        }

        return StudentSubjectResult::query()
            ->select('assessment_student_subject_results.*')
            ->join(
                'assessment_period_students as source_students',
                'source_students.id',
                '=',
                'assessment_student_subject_results.assessment_period_student_id',
            )
            ->join(
                'assessment_period_assignments as source_assignments',
                'source_assignments.id',
                '=',
                'assessment_student_subject_results.assessment_period_assignment_id',
            )
            ->where('assessment_student_subject_results.assessment_period_id', $sourcePeriodId)
            ->where('source_students.student_id', $student->student_id)
            ->where('source_assignments.assessment_subject_id', $assignment->assessment_subject_id)
            ->whereNotNull('assessment_student_subject_results.final_score')
            ->latest('assessment_student_subject_results.id')
            ->first();
    }
}
