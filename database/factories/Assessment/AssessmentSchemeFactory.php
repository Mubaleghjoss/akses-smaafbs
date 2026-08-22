<?php

namespace Database\Factories\Assessment;

use App\Models\Assessment\AssessmentPeriod;
use App\Models\Assessment\AssessmentScheme;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AssessmentScheme> */
class AssessmentSchemeFactory extends Factory
{
    protected $model = AssessmentScheme::class;

    public function definition(): array
    {
        return [
            'assessment_period_id' => AssessmentPeriod::factory(),
            'assessment_subject_id' => null,
            'source_rombel_id' => null,
            'assessment_period_rombel_id' => null,
            'name' => fake()->unique()->bothify('Skema #### ??'),
            'rounding_precision' => 2,
            'minimum_score' => 0,
            'maximum_score' => 100,
            'settings' => [],
            'is_active' => true,
        ];
    }
}
