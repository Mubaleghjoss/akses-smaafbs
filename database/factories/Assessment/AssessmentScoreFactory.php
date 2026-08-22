<?php

namespace Database\Factories\Assessment;

use App\Enums\Assessment\ScoreSource;
use App\Models\Assessment\AssessmentComponent;
use App\Models\Assessment\AssessmentPeriodAssignment;
use App\Models\Assessment\AssessmentPeriodStudent;
use App\Models\Assessment\AssessmentScore;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AssessmentScore> */
class AssessmentScoreFactory extends Factory
{
    protected $model = AssessmentScore::class;

    public function definition(): array
    {
        return [
            'assessment_period_assignment_id' => AssessmentPeriodAssignment::factory(),
            'assessment_period_student_id' => AssessmentPeriodStudent::factory(),
            'assessment_component_id' => AssessmentComponent::factory(),
            'score' => fake()->randomFloat(2, 0, 100),
            'notes' => null,
            'source' => ScoreSource::MANUAL,
            'source_result_id' => null,
            'source_score_snapshot' => null,
            'entered_by' => null,
            'updated_by' => null,
        ];
    }
}
