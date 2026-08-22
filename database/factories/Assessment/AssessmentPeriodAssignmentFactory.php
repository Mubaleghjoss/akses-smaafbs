<?php

namespace Database\Factories\Assessment;

use App\Enums\Assessment\AssignmentStatus;
use App\Models\Assessment\AssessmentPeriod;
use App\Models\Assessment\AssessmentPeriodAssignment;
use App\Models\Assessment\AssessmentPeriodRombel;
use App\Models\Assessment\Subject;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AssessmentPeriodAssignment> */
class AssessmentPeriodAssignmentFactory extends Factory
{
    protected $model = AssessmentPeriodAssignment::class;

    public function definition(): array
    {
        return [
            'assessment_period_id' => AssessmentPeriod::factory(),
            'source_teaching_assignment_id' => null,
            'assessment_period_rombel_id' => AssessmentPeriodRombel::factory(),
            'teacher_id' => fake()->numberBetween(1, 100000),
            'assessment_subject_id' => Subject::factory(),
            'teacher_name_snapshot' => fake()->name(),
            'subject_name_snapshot' => fake()->words(2, true),
            'rombel_name_snapshot' => fake()->randomElement(['X 1', 'XI 1', 'XII 1']),
            'status' => AssignmentStatus::DRAFT,
            'lock_version' => 1,
        ];
    }
}
