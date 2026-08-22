<?php

namespace App\Actions\Assessment;

use App\Models\Assessment\AssessmentPeriodAssignment;
use App\Models\Assessment\AssessmentPeriodStudent;
use App\Models\Assessment\AssessmentScore;
use App\Models\Assessment\StudentSubjectResult;
use App\Support\Assessment\AssessmentCalculator;
use App\Support\Assessment\AssessmentSchemeResolver;
use Illuminate\Validation\ValidationException;

final class CalculateStudentSubjectResultAction
{
    public function __construct(
        private readonly AssessmentSchemeResolver $schemeResolver,
        private readonly AssessmentCalculator $calculator,
    ) {}

    public function execute(
        AssessmentPeriodAssignment $assignment,
        AssessmentPeriodStudent $student,
        ?string $descriptionOverride = null,
        bool $hasDescriptionOverride = false,
    ): StudentSubjectResult {
        if ((int) $student->assessment_period_id !== (int) $assignment->assessment_period_id
            || (int) $student->assessment_period_rombel_id !== (int) $assignment->assessment_period_rombel_id) {
            throw ValidationException::withMessages([
                'student' => 'Siswa tidak termasuk dalam snapshot kelas penugasan ini.',
            ]);
        }

        $scheme = $this->schemeResolver->forAssignment($assignment);
        $scores = AssessmentScore::query()
            ->where('assessment_period_assignment_id', $assignment->getKey())
            ->where('assessment_period_student_id', $student->getKey())
            ->get();
        $calculation = $this->calculator->calculate(
            $scheme->components,
            $scores,
            $scheme,
        );
        $result = StudentSubjectResult::query()->firstOrNew([
            'assessment_period_id' => $assignment->assessment_period_id,
            'assessment_period_student_id' => $student->getKey(),
            'assessment_period_assignment_id' => $assignment->getKey(),
        ]);
        $oldGeneratedDescription = data_get($result->calculation_detail, 'generated_description');
        $descriptionWasEdited = data_get($result->calculation_detail, 'description_overridden');
        if (! is_bool($descriptionWasEdited)) {
            // Infer the state for records created before this marker existed,
            // including a deliberate manual clear to an empty description.
            $descriptionWasEdited = $result->exists
                && $result->description !== $oldGeneratedDescription;
        }
        $submittedDescriptionIsUnchangedGenerated = $hasDescriptionOverride
            && $result->exists
            && ! $descriptionWasEdited
            && $result->description === $oldGeneratedDescription
            && $descriptionOverride === $result->description;
        $descriptionIsOverridden = $hasDescriptionOverride
            ? ! $submittedDescriptionIsUnchangedGenerated
            : $descriptionWasEdited;
        $description = match (true) {
            $hasDescriptionOverride && ! $submittedDescriptionIsUnchangedGenerated => filled($descriptionOverride)
                ? trim((string) $descriptionOverride)
                : null,
            $descriptionWasEdited => $result->description,
            default => $calculation->description,
        };
        $attributes = $calculation->toResultAttributes();
        $calculationDetail = (array) ($attributes['calculation_detail'] ?? []);
        $calculationDetail['description_overridden'] = $descriptionIsOverridden;

        $result->fill([
            ...$attributes,
            'calculation_detail' => $calculationDetail,
            'description' => $description,
        ])->save();

        return $result->refresh();
    }
}
