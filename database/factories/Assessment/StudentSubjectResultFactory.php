<?php

namespace Database\Factories\Assessment;

use App\Models\Assessment\AssessmentPeriod;
use App\Models\Assessment\AssessmentPeriodAssignment;
use App\Models\Assessment\AssessmentPeriodStudent;
use App\Models\Assessment\StudentSubjectResult;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<StudentSubjectResult> */
class StudentSubjectResultFactory extends Factory
{
    protected $model = StudentSubjectResult::class;

    public function definition(): array
    {
        $score = fake()->randomFloat(2, 0, 100);

        return [
            'assessment_period_id' => AssessmentPeriod::factory(),
            'assessment_period_student_id' => AssessmentPeriodStudent::factory(),
            'assessment_period_assignment_id' => AssessmentPeriodAssignment::factory(),
            'final_score' => $score,
            'predicate' => $score >= 75 ? 'Tuntas' : 'Belum Tuntas',
            'description' => null,
            'calculation_detail' => [],
            'formula_version' => 'v1',
            'calculated_at' => now(),
        ];
    }
}
